<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Enums\OrigenCambioSuscripcion;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Models\TransicionSuscripcion;
use Illuminate\Support\Carbon;

/**
 * La suscripción de una organización tal y como la lee ella misma (punto 51):
 * qué plan, en qué estado, hasta cuándo, cuánto usa de lo que permite y qué
 * ha cambiado. Es el bloque de arriba de `/organizacion`.
 *
 * Los días se cuentan **de calendario**, igual que los avisos de
 * `HitoSuscripcion`: el correo de las 07:15 y esta pantalla tienen que decir
 * el mismo número.
 *
 * Con el contexto de la organización puesto: los sistemas se cuentan por su
 * scope.
 */
final class ResumenSuscripcion
{
    /** Cuántos cambios del histórico se enseñan. El resto está en la traza. */
    public const HISTORICO = 5;

    public function __construct(private readonly LimitesDelPlan $limites) {}

    /**
     * @return array{
     *     plan: ?string,
     *     descripcion: ?string,
     *     estado: array{valor: string, etiqueta: string, tono: string, icono: string},
     *     periodo: ?string,
     *     precioPeriodoCentimos: ?int,
     *     iniciaEn: ?string,
     *     venceEn: ?string,
     *     finGracia: ?string,
     *     diasGracia: ?int,
     *     dias: ?int,
     *     limiteCuentas: ?int,
     *     limiteSistemas: ?int,
     *     uso: array{cuentas: int, sistemas: int},
     *     puedeContratar: bool,
     *     historico: list<array{id: int, fecha: string, que: string, quien: string, importeCentimos: ?int}>,
     * }
     */
    public function __invoke(Organizacion $organizacion, ?Carbon $ahora = null): array
    {
        $ahora ??= Carbon::now();
        $plan = $organizacion->plan;
        $estado = EstadoSuscripcion::de($organizacion, $ahora);
        $finGracia = EstadoSuscripcion::finDeGracia($organizacion);
        $periodo = $organizacion->suscripcion_periodo;

        return [
            'plan' => $plan?->nombre,
            'descripcion' => $plan?->descripcion,
            'estado' => [
                'valor' => $estado->value,
                'etiqueta' => $estado->etiqueta(),
                'tono' => $estado->tono(),
                'icono' => $estado->icono(),
            ],
            'periodo' => $periodo?->etiqueta(),
            'precioPeriodoCentimos' => $periodo === null ? null : $plan?->precioDelPeriodo($periodo),
            'iniciaEn' => $organizacion->suscripcion_inicia_en?->toIso8601String(),
            'venceEn' => $organizacion->suscripcion_vence_en?->toIso8601String(),
            'finGracia' => $finGracia?->toIso8601String(),
            'diasGracia' => $plan?->dias_gracia,
            'dias' => $this->dias($estado, $organizacion->suscripcion_vence_en, $finGracia, $ahora),
            'limiteCuentas' => $plan?->limite_cuentas,
            'limiteSistemas' => $plan?->limite_sistemas,
            'uso' => $this->limites->uso($organizacion),
            'puedeContratar' => Plan::query()->contratables()->exists(),
            'historico' => $this->historico($organizacion),
        ];
    }

    /**
     * El número grande del bloque: cuántos días faltan para vencer, en
     * vigente; cuántos para pasar a sólo lectura, en gracia; cuántos lleva en
     * sólo lectura, después. Nulo si no vence.
     */
    private function dias(EstadoSuscripcion $estado, ?Carbon $vence, ?Carbon $finGracia, Carbon $ahora): ?int
    {
        $hoy = $ahora->copy()->startOfDay();

        return match (true) {
            $vence === null || $finGracia === null => null,
            $estado === EstadoSuscripcion::Vigente => (int) $hoy->diffInDays($vence->copy()->startOfDay()),
            $estado === EstadoSuscripcion::EnGracia => (int) $hoy->diffInDays($finGracia->copy()->startOfDay()),
            default => (int) $finGracia->copy()->startOfDay()->diffInDays($hoy),
        };
    }

    /**
     * Lo último que ha cambiado, en frases. De quien lo cambió desde la
     * plataforma no se da el nombre: para el cliente es el equipo de Statera.
     *
     * @return list<array{id: int, fecha: string, que: string, quien: string, importeCentimos: ?int}>
     */
    private function historico(Organizacion $organizacion): array
    {
        return TransicionSuscripcion::query()
            ->with(['planAnterior', 'planNuevo', 'usuario'])
            ->where('organizacion_afectada_id', $organizacion->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORICO)
            ->get()
            ->map(static fn (TransicionSuscripcion $cambio): array => [
                'id' => $cambio->id,
                'fecha' => $cambio->created_at->toIso8601String(),
                'que' => self::frase($cambio),
                'quien' => $cambio->origen === OrigenCambioSuscripcion::Organizacion && $cambio->usuario !== null
                    ? $cambio->usuario->name
                    : $cambio->origen->etiqueta(),
                'importeCentimos' => $cambio->importe_centimos,
            ])
            ->values()
            ->all();
    }

    private static function frase(TransicionSuscripcion $cambio): string
    {
        $anterior = $cambio->planAnterior?->nombre;
        $nuevo = $cambio->planNuevo?->nombre;
        $periodo = $cambio->periodo_nuevo === null ? '' : ', '.mb_strtolower($cambio->periodo_nuevo->etiqueta());
        $hasta = $cambio->vence_en_nuevo === null ? '' : ' hasta el '.$cambio->vence_en_nuevo->locale('es')->isoFormat('D MMM YYYY');

        return match (true) {
            $nuevo === null => 'Sin plan',
            $anterior === null => "Alta con el plan {$nuevo}{$periodo}",
            $anterior === $nuevo && $cambio->periodo_anterior === $cambio->periodo_nuevo => "Renovación del plan {$nuevo}{$hasta}",
            $anterior === $nuevo => "Plan {$nuevo}: pasa a facturación ".mb_strtolower((string) $cambio->periodo_nuevo?->etiqueta()),
            default => "Cambio de plan: {$anterior} → {$nuevo}{$periodo}",
        };
    }
}
