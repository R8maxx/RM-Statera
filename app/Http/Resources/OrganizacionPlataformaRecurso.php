<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Organizacion\Models\Organizacion;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\ValorEnlace;
use App\Http\Resources\Definicion\ValorEtiquetado;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las organizaciones cliente, vistas desde la plataforma (punto 41).
 *
 * **Sólo la ficha comercial.** Lo que se lee aquí sale de `organizaciones` y de
 * `users`, las dos tablas que ya están fuera de las tres capas a propósito. Un
 * recuento de cualquier tabla del tenant —sistemas, riesgos, evidencias—
 * exigiría cruzar organizaciones con `comoMantenimiento()`, que sigue prohibido
 * en una petición web, y además le enseñaría al administrador el SGSI de un
 * cliente que no le ha abierto la puerta.
 *
 * @extends Recurso<Organizacion>
 */
final class OrganizacionPlataformaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'plataforma-organizaciones';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Organización',
            plural: 'Organizaciones',
            descripcion: 'Los clientes de Statera: quién es cada uno y cuántas cuentas tiene. Lo que cada uno guarda dentro no se ve desde aquí.',
            vacio: 'Todavía no hay ninguna organización. Da de alta la primera.',
        );
    }

    /** @return Builder<Organizacion> */
    public function consulta(): Builder
    {
        return Organizacion::query()
            ->with('plan')
            ->select('organizaciones.*')
            ->selectRaw('(select planes.nombre from planes where planes.id = organizaciones.plan_id) as plan_nombre')
            // Por subconsulta acotada a la fila: `users` no tiene RLS.
            ->selectRaw(<<<'SQL'
                (select count(*) from users
                    where users.organizacion_id = organizaciones.id
                      and users.activada_en is not null
                      and users.desactivada_en is null) as cuentas_activas
            SQL);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            // El nombre abre la ficha con un clic; el doble clic sobre la fila, también.
            Columna::enlace('nombre', 'Nombre')
                ->ordenable()
                ->anclada()
                ->formato(fn (Organizacion $fila): ValorEnlace => new ValorEnlace($fila->nombre, "/plataforma/organizaciones/{$fila->id}")),
            Columna::texto('razon_social', 'Razón social')->ordenable(),
            Columna::texto('cif', 'CIF')->ancho('9rem'),
            // Activa o de baja (punto 46). Sin rojo: una baja es una decisión,
            // no algo que vaya mal.
            Columna::badge('estado', 'Estado')
                ->ancho('8rem')
                ->formato(fn (Organizacion $fila): ValorEtiquetado => $fila->estaDeBaja()
                    ? new ValorEtiquetado('baja', 'De baja', 'no_aplica', 'Archive')
                    : new ValorEtiquetado('activa', 'Activa', 'implantado', 'CircleCheck')),
            Columna::texto('plan_nombre', 'Plan')
                ->ancho('11rem')
                ->formato(fn (Organizacion $fila): string => (string) ($fila->getAttribute('plan_nombre') ?? 'Sin plan')),
            Columna::badge('suscripcion', 'Suscripción')
                ->ancho('12rem')
                ->formato(function (Organizacion $fila): ValorEtiquetado {
                    $estado = $fila->estadoSuscripcion();

                    return new ValorEtiquetado($estado->value, $estado->etiqueta(), $estado->tono(), $estado->icono());
                }),
            Columna::fecha('suscripcion_vence_en', 'Vence')
                ->ordenable()
                ->ayuda('El último día del contrato. Vacío, no vence.'),
            Columna::numero('cuentas_activas', 'Cuentas activas')
                ->ancho('9rem')
                ->ayuda('Cuentas que ya aceptaron su invitación y no están desactivadas.'),
            Columna::fecha('created_at', 'Alta')->ordenable(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'nombre' => 'nombre',
                'razon_social' => 'razon_social',
                'cif' => 'cif',
            ])->placeholder('Buscar por nombre, razón social o CIF…'),

            // Los destinos de las cifras del cuadro de mando (punto 53).
            Filtro::porScope('vence_pronto', 'Vence en 30 días', 'vencePronto')->enColumna('suscripcion'),
            Filtro::porScope('en_gracia', 'En periodo de gracia', 'enGracia')->enColumna('suscripcion'),
            Filtro::porScope('solo_lectura', 'En sólo lectura', 'enSoloLectura')->enColumna('suscripcion'),
            Filtro::porScope('sobre_su_plan', 'Con más cuentas que su plan', 'sobreSuPlan')->enColumna('cuentas_activas'),
            Filtro::porScope('de_baja', 'De baja', 'deBaja')->enColumna('estado'),
            Filtro::porScope('soporte_abierto', 'Con soporte abierto', 'conSoporteAbierto')->enColumna('estado'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'nombre';
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/plataforma/organizaciones/{id}'),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('alta', 'Dar de alta una organización', '/plataforma/organizaciones/crear'))
                ->icono('Building2'),
        ];
    }
}
