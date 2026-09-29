<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Conformidad\RequisitosDeDeclaracion;
use App\Domain\Implantacion\CorrespondenciasCruzadas;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Asistencia;
use App\Domain\Persona\Models\Persona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Lo que la ficha de una sesión necesita saber de la plantilla y del catálogo.
 *
 * **La vigencia de cada persona sin contar esta sesión**, que es lo que permite
 * contestar «si falta, ¿queda al descubierto?». Con la sesión dentro la
 * pregunta no se puede hacer: quien asistió estaría cubierto por construcción,
 * y al desmarcar la casilla en pantalla no se sabría a qué volver. El cliente
 * combina las dos —su vigencia previa y la fecha de esta sesión— y así la
 * columna sigue a las casillas sin otra petición.
 *
 * Y **qué requisitos cubre**: la medida del ENS que declara el tipo y lo que el
 * catálogo mapea desde ella. Leído del mapeo, no escrito aquí: el catálogo es
 * datos (invariante 3).
 */
final readonly class ConvocatoriaDeSesion
{
    public function __construct(private CorrespondenciasCruzadas $correspondencias) {}

    /**
     * La plantilla activa y quien, ya de baja, estaba convocado.
     *
     * Las personas dadas de baja sólo aparecen si ya estaban convocadas:
     * asistieron de verdad, y borrarlas de la pantalla reescribiría el registro.
     *
     * @return list<array{id: int, codigo: string, nombre: string, puesto: ?string, activa: bool, convocada: bool, asistio: bool, ausencia: ?string, motivo: ?string, renovacion_previa: ?string, ultima_sesion: ?string}>
     */
    public function personas(AccionFormativa $accion): array
    {
        $convocadas = $accion->asistencias()->get()->keyBy('persona_id');
        $meses = Persona::MESES_DE_VIGENCIA_FORMATIVA;

        $personas = Persona::query()
            ->where(static function (Builder $consulta) use ($accion): void {
                /** @var Builder<Persona> $consulta */
                $consulta->whereNull('personas.fecha_baja')
                    ->orWhereHas('asistencias', static function (Builder $asistencias) use ($accion): void {
                        /** @var Builder<Asistencia> $asistencias */
                        $asistencias->where('accion_formativa_id', $accion->id);
                    });
            })
            ->select('personas.*')
            ->selectRaw(
                "(select max(af.fecha) + make_interval(months => {$meses})
                    from asistencias a join acciones_formativas af on af.id = a.accion_formativa_id
                    where a.persona_id = personas.id and a.asistio = true and a.accion_formativa_id <> ?) as renovacion_previa",
                [$accion->id],
            )
            ->selectRaw(
                '(select af.codigo
                    from asistencias a join acciones_formativas af on af.id = a.accion_formativa_id
                    where a.persona_id = personas.id and a.asistio = true and a.accion_formativa_id <> ?
                    order by af.fecha desc, af.id desc limit 1) as ultima_sesion',
                [$accion->id],
            )
            ->orderBy('nombre')
            ->get();

        return $personas
            ->map(static function (Persona $persona) use ($convocadas): array {
                /** @var ?Asistencia $asistencia */
                $asistencia = $convocadas->get($persona->id);
                $renovacion = $persona->getAttribute('renovacion_previa');
                $ultima = $persona->getAttribute('ultima_sesion');

                return [
                    'id' => $persona->id,
                    'codigo' => $persona->codigo,
                    'nombre' => $persona->nombre,
                    'puesto' => $persona->puestoVigente()?->titulo,
                    'activa' => $persona->estaActiva(),
                    'convocada' => $asistencia !== null,
                    'asistio' => (bool) $asistencia?->asistio,
                    'ausencia' => $asistencia?->ausencia?->value,
                    'motivo' => $asistencia?->motivo_ausencia,
                    'renovacion_previa' => is_string($renovacion) ? Carbon::parse($renovacion)->toDateString() : null,
                    'ultima_sesion' => is_string($ultima) ? $ultima : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * La medida del ENS que declara el tipo y lo que el catálogo mapea desde ella.
     *
     * Vacío si el catálogo no está importado: la ficha sigue enseñando la medida
     * en texto, que es lo que ya enseñaba.
     *
     * @return list<array{marco: string, codigo: string, titulo: string}>
     */
    public function cubre(AccionFormativa $accion): array
    {
        $medida = Requisito::query()
            ->delMarco(RequisitosDeDeclaracion::CODIGO_MARCO_ENS)
            ->vigentes()
            ->where('codigo', $accion->tipo->medida())
            ->first();

        if ($medida === null) {
            return [];
        }

        $correspondencias = $this->correspondencias->paraRequisito($medida->id);

        /** @var array<string, string> $nombres */
        $nombres = Marco::query()
            ->whereIn('codigo', [RequisitosDeDeclaracion::CODIGO_MARCO_ENS, ...array_filter(array_map(static fn ($c): ?string => $c->marco, $correspondencias))])
            ->pluck('nombre', 'codigo')
            ->all();

        $cubre = [[
            'marco' => $nombres[RequisitosDeDeclaracion::CODIGO_MARCO_ENS] ?? RequisitosDeDeclaracion::CODIGO_MARCO_ENS,
            'codigo' => $medida->codigo,
            'titulo' => $medida->titulo,
        ]];

        foreach ($correspondencias as $correspondencia) {
            $cubre[] = [
                'marco' => $nombres[$correspondencia->marco ?? ''] ?? (string) $correspondencia->marco,
                'codigo' => $correspondencia->codigo,
                'titulo' => $correspondencia->titulo,
            ];
        }

        return $cubre;
    }
}
