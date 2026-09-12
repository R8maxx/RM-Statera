<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * El repositorio de pruebas.
 *
 * La columna que de verdad se mira aquí es «Requisitos»: cuántos requisitos
 * prueba cada evidencia. Un dos o un cuatro es exactamente el trabajo que la
 * herramienta ahorra frente a las hojas de cálculo duplicadas.
 *
 * @extends Recurso<Evidencia>
 */
final class EvidenciaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'evidencias';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Evidencia',
            plural: 'Evidencias',
            descripcion: 'Las pruebas de que los requisitos se cumplen. Se registran una vez y cuentan en todos los marcos donde apliquen.',
            vacio: 'Todavía no hay ninguna evidencia. La primera se sube desde aquí o desde la ficha de un requisito.',
        );
    }

    /** @return Builder<Evidencia> */
    public function consulta(): Builder
    {
        return Evidencia::query()
            ->with('responsable')
            ->withCount('implantaciones as requisitos_count');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('titulo', 'Título')->ordenable()->anclada(),
            Columna::badge('tipo', 'Tipo')
                ->ordenable()
                ->formato(fn (Evidencia $evidencia): ValorEtiquetado => new ValorEtiquetado(
                    $evidencia->tipo->value,
                    $evidencia->tipo->etiqueta(),
                    'marco',
                )),
            Columna::numero('requisitos', 'Requisitos')
                ->ayuda('Cuántos requisitos prueba, de cualquier marco. Es el trabajo que no hay que repetir.')
                ->formato(fn (Evidencia $evidencia): int => (int) $evidencia->getAttribute('requisitos_count')),
            Columna::fecha('fecha_obtencion', 'Obtenida')->ordenable(),
            // La fecha sola no dice si hay un problema: hay que leerla y
            // compararla con hoy. El badge lo dice de un vistazo, que es lo que
            // se le pide a una columna que se recorre.
            Columna::badge('vigencia', 'Vigencia')
                ->ordenable('fecha_caducidad')
                ->ayuda('Una evidencia sin fecha de caducidad no vale para siempre: es que nadie ha dicho cuándo deja de valer.')
                ->formato(fn (Evidencia $evidencia): ValorEtiquetado => $this->vigencia($evidencia)),
            Columna::fecha('fecha_caducidad', 'Caduca')->ordenable()->oculta(),
            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Evidencia $evidencia): ?string => $evidencia->responsable?->name),
            Columna::texto('origen', 'Origen')
                ->oculta()
                ->formato(fn (Evidencia $evidencia): string => $evidencia->esFichero() ? 'Fichero' : 'Enlace'),
            Columna::texto('periodicidad_renovacion', 'Renovación')
                ->oculta()
                ->formato(fn (Evidencia $evidencia): ?string => $evidencia->periodicidad_renovacion?->etiqueta()),
            Columna::fechaHora('created_at', 'Alta')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'titulo' => 'titulo',
                'descripcion' => 'titulo',
                'nombre_fichero' => 'titulo',
            ])->placeholder('Buscar por título, descripción o fichero…'),
            Filtro::texto('titulo', 'Título'),
            Filtro::multiSelect('tipo', 'Tipo', array_map(
                static fn (TipoEvidencia $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoEvidencia::cases(),
            )),
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),
            Filtro::rangoFechas('fecha_obtencion', 'Obtenida'),
            Filtro::rangoFechas('fecha_caducidad', 'Caduca')->enColumna('vigencia'),
            Filtro::multiSelect('periodicidad_renovacion', 'Renovación', array_map(
                static fn (PeriodicidadRenovacion $periodicidad): Opcion => new Opcion(
                    $periodicidad->value,
                    $periodicidad->etiqueta(),
                ),
                PeriodicidadRenovacion::cases(),
            )),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/evidencias/{id}'),
            Accion::eliminar(
                '/evidencias/{id}',
                '¿Eliminar la evidencia y todos sus vínculos? El fichero no se borra del almacén, pero los requisitos que probaba se quedan sin prueba.',
            )->permiso(Permiso::EvidenciasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva evidencia', '/evidencias/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::EvidenciasGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha_obtencion';
    }

    /**
     * Cuatro estados, y «sin caducidad» es uno de ellos a propósito: no es lo
     * mismo que vigente, y colapsarlos escondería justo las que nadie ha
     * revisado nunca.
     */
    private function vigencia(Evidencia $evidencia): ValorEtiquetado
    {
        if ($evidencia->fecha_caducidad === null) {
            return new ValorEtiquetado(null, 'Sin caducidad', 'no_iniciado', 'CircleHelp');
        }

        if ($evidencia->haCaducado()) {
            return new ValorEtiquetado(
                $evidencia->fecha_caducidad->toDateString(),
                'Caducada',
                'caducada',
                'TriangleAlert',
            );
        }

        $dias = (int) now()->startOfDay()->diffInDays($evidencia->fecha_caducidad, absolute: false);

        return new ValorEtiquetado(
            $evidencia->fecha_caducidad->toDateString(),
            $dias <= 30 ? "Caduca en {$dias} días" : 'Vigente',
            $dias <= 30 ? 'en_progreso' : 'implantado',
            $dias <= 30 ? 'Clock' : 'CircleCheck',
        );
    }
}
