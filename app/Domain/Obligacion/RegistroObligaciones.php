<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Models\Compromiso;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\ProximaObligacion;
use App\Http\Resources\Panel\ResumenObligacionesPanel;
use Illuminate\Support\Carbon;

/**
 * Lo que pide atención en el registro de obligaciones.
 *
 * Igual que `RegistroIndicadores`, `RegistroAuditorias` y `RegistroRiesgos`, vive
 * en el dominio y no en `Http/Resources`: son preguntas del negocio, y las
 * contesta el mismo scope que luego filtra la tabla. **La clave del indicador es
 * la del filtro y el scope es el tercer argumento de su `Filtro::porScope()`**,
 * que es lo que impide que el panel diga 3 y la tabla enseñe 5.
 */
final readonly class RegistroObligaciones
{
    public function total(): int
    {
        return Compromiso::query()->activos()->count();
    }

    /**
     * Lo que va mal de verdad, y **es una sola cosa**.
     *
     * Haberse comprometido a algo y pasarse de fecha es un incumplimiento, y de
     * los que un auditor comprueba el primer día. Lo demás pide atención sin
     * serlo, y meterlo aquí pondría punto rojo en la pestaña del panel de forma
     * permanente: con un compromiso sin responsable y otro recién asumido, el
     * aviso dejaría de significar nada.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'vencidas',
                'Fuera de plazo',
                'vencidos',
                'caducada',
                'Compromisos cuya fecha ya pasó sin registrar que se cumplieran. Es lo que un auditor comprueba antes que nada.',
            ),
        ];
    }

    /**
     * Lo que está declarado a medias. Ninguna gasta rojo.
     *
     * «Nunca cumplidas» no es un incumplimiento por sí solo —un compromiso
     * asumido esta semana todavía no ha tenido ocasión—, pero es la pregunta que
     * conviene hacerse: una obligación declarada y nunca cumplida es una promesa,
     * no un control.
     *
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'por_vencer',
                'Vence en 90 días',
                'porVencer',
                'en_progreso',
                'Lo que toca dentro del próximo trimestre. La ventana es de noventa días y no de treinta: contratar a quien audita o abrir la ventana del INES no se hace en un mes.',
            ),
            $this->indicador(
                'nunca_cumplidas',
                'Nunca cumplidas',
                'nuncaCumplidos',
                'no_iniciado',
                'Asumidas y sin un solo cumplimiento registrado: una promesa, no un control.',
            ),
            $this->indicador(
                'sin_responsable',
                'Sin responsable',
                'sinResponsable',
                'no_aplica',
                'Nadie declarado como responsable de que esto se haga a tiempo.',
            ),
        ];
    }

    /** El registro de obligaciones, tal y como lo lee el panel. */
    public function paraElPanel(): ResumenObligacionesPanel
    {
        return new ResumenObligacionesPanel(
            total: $this->total(),
            vencidas: Compromiso::query()->vencidos()->count(),
            porVencer: Compromiso::query()->porVencer()->count(),
            nuncaCumplidas: Compromiso::query()->nuncaCumplidos()->count(),
            sinResponsable: Compromiso::query()->sinResponsable()->count(),
            proxima: $this->proxima(),
        );
    }

    /**
     * La que toca antes, vencida o no.
     *
     * **Vencida también**, y a propósito: si hay algo fuera de plazo, eso es lo
     * que toca antes de nada. Enseñar la siguiente en plazo mientras hay una
     * pasada de fecha sería contestar otra pregunta.
     */
    private function proxima(): ?ProximaObligacion
    {
        $compromiso = Compromiso::query()
            ->activos()
            ->orderByRaw(Compromiso::expresionProxima())
            ->first();

        if ($compromiso === null) {
            return null;
        }

        $fecha = $compromiso->proximaFecha();
        $dias = (int) Carbon::today()->diffInDays($fecha, false);

        return new ProximaObligacion(
            id: $compromiso->id,
            titulo: $compromiso->titulo,
            fecha: $fecha->format('d/m/Y'),
            dias: $dias,
            cuando: match (true) {
                $dias < 0 => 'venció hace '.abs($dias).' '.($dias === -1 ? 'día' : 'días'),
                $dias === 0 => 'vence hoy',
                default => 'vence en '.$dias.' '.($dias === 1 ? 'día' : 'días'),
            },
        );
    }

    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Compromiso::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/obligaciones',
            ayuda: $ayuda,
        );
    }
}
