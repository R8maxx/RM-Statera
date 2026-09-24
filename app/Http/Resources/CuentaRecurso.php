<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las cuentas de la organización (§ 4.19).
 *
 * **`users` está fuera de las tres capas**, así que la consulta se acota a mano
 * y la organización sale del contexto: aquí no hay fila de la que tomarla. Es
 * el único `Recurso` del producto al que le hace falta, y lo vigila
 * `ConsultasDeUsuarioAcotadasTest`.
 *
 * El rol y la persona llegan por subconsulta y no por join, por lo mismo que en
 * puestos: un join contra `model_has_roles` repetiría la cuenta si alguien le
 * diera dos roles a mano.
 *
 * **Sin rojo**: una invitación sin aceptar o un acceso caducado son lo que se
 * esperaba, no un incumplimiento.
 *
 * @extends Recurso<User>
 */
final class CuentaRecurso extends Recurso
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    public function clave(): string
    {
        return 'cuentas';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Cuenta',
            plural: 'Cuentas',
            descripcion: 'Quién entra en Statera, con qué rol y, para el auditor externo, qué sistemas ve y hasta cuándo.',
            vacio: 'No hay cuentas que coincidan.',
        );
    }

    /** @return Builder<User> */
    public function consulta(): Builder
    {
        return User::query()
            ->where('users.organizacion_id', $this->contexto->idObligatorio())
            ->select('users.*')
            ->selectRaw(<<<'SQL'
                (select roles.name from model_has_roles
                    join roles on roles.id = model_has_roles.role_id
                    where model_has_roles.model_id = users.id
                      and model_has_roles.model_type = ?
                      and model_has_roles.organizacion_id = users.organizacion_id
                    limit 1) as rol
            SQL, [(new User)->getMorphClass()])
            ->selectRaw(<<<'SQL'
                (select personas.nombre from personas where personas.user_id = users.id) as persona
            SQL);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('name', 'Nombre')->ordenable()->anclada(),

            Columna::texto('email', 'Correo')->ordenable(),

            Columna::texto('rol', 'Rol')
                ->ancho('13rem')
                ->formato(fn (User $fila): ?string => Rol::tryFrom((string) $fila->getAttribute('rol'))?->etiqueta()),

            Columna::badge('estado', 'Estado')
                ->ancho('9rem')
                ->formato(function (User $fila): ValorEtiquetado {
                    $estado = $fila->estadoCuenta();

                    return new ValorEtiquetado($estado->value, $estado->etiqueta(), $estado->tono(), $estado->icono());
                }),

            Columna::booleano('dos_factores', 'Dos pasos')
                ->ancho('7rem')
                ->ayuda('Si la cuenta tiene activada la verificación en dos pasos. Es obligatoria para escribir.')
                ->formato(fn (User $fila): bool => $fila->dosFactoresConfirmado()),

            Columna::fecha('acceso_hasta', 'Acceso hasta')
                ->ordenable()
                ->ayuda('Sólo el auditor externo tiene fecha de fin.'),

            Columna::fechaHora('ultimo_acceso_en', 'Último acceso')->ordenable(),

            Columna::texto('persona', 'Persona')->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'name' => 'name',
                'email' => 'email',
            ])->placeholder('Buscar por nombre o correo…'),

            Filtro::porScope('invitadas', 'Sin aceptar la invitación', 'invitadas')->enColumna('estado'),
            Filtro::porScope('desactivadas', 'Desactivadas', 'desactivadas')->enColumna('estado'),
            Filtro::porScope('con_fecha_de_fin', 'Con fecha de fin', 'conFechaDeFin')->enColumna('acceso_hasta'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'name';
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/cuentas/{id}'),
            Accion::editar('/cuentas/{id}/editar')->permiso(Permiso::CuentasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('invitar', 'Invitar una cuenta', '/cuentas/crear'))
                ->icono('UserPlus')
                ->permiso(Permiso::CuentasGestionar->value),
        ];
    }
}
