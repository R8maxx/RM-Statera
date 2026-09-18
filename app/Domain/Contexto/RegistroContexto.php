<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\Reparto;
use App\Http\Resources\Panel\ResumenContextoPanel;
use Illuminate\Support\Carbon;

/**
 * Las cifras del contexto: la tira de los dos registros y la tarjeta del panel.
 *
 * **La clave de cada indicador ES la clave de su filtro, y el scope ES el tercer
 * argumento de su `Filtro::porScope()`.** No es una coincidencia que haya que
 * mantener: es lo que hace que pulsar el número enseñe exactamente ese número. Con
 * la condición escrita dos veces, el día que cambie una el panel dice 12 y la lista
 * enseña 9, y a partir de ahí nadie se fía del panel.
 *
 * `alertas()` está vacío en los dos registros, y es deliberado: `TiraIndicadores`
 * pinta las alertas en rojo, y el rojo tiene tres dueños declarados —evidencia
 * caducada, tarea vencida y riesgo muy alto—. Nada de este módulo es eso. Una
 * amenaza sin riesgo detrás es una pregunta pendiente, no un incumplimiento.
 */
final readonly class RegistroContexto
{
    public function __construct(private AnalisisEnCurso $analisis) {}

    public function totalCuestiones(): int
    {
        return CuestionContexto::query()->vigentes()->count();
    }

    public function totalPartes(): int
    {
        return ParteInteresada::query()->vigentes()->count();
    }

    /** @return list<Indicador> */
    public function alertasCuestiones(): array
    {
        return [];
    }

    /** @return list<Indicador> */
    public function pendientesCuestiones(): array
    {
        return [
            $this->indicadorCuestion(
                'sin_riesgo',
                'Sin riesgo vinculado',
                'sinRiesgo',
                'Debilidades y amenazas que no han acabado en ningún riesgo. ISO 6.1.1 pide que la apreciación de riesgos se haga considerando estas cuestiones.',
            ),
            $this->indicadorCuestion(
                'sin_trabajo',
                'Sin nada en marcha',
                'sinTrabajo',
                'Debilidades y amenazas sin ninguna tarea abierta detrás.',
            ),
        ];
    }

    /** @return list<Indicador> */
    public function alertasPartes(): array
    {
        return [];
    }

    /** @return list<Indicador> */
    public function pendientesPartes(): array
    {
        return [
            new Indicador(
                clave: 'obligacion_sin_cubrir',
                etiqueta: 'Con obligaciones sin cubrir',
                valor: ParteInteresada::query()->conObligacionSinCubrir()->count(),
                tono: 'en_progreso',
                filtro: 'filter[obligacion_sin_cubrir]=1',
                base: '/partes-interesadas',
                ayuda: 'Partes con algún requisito legal o contractual que no tiene ninguna implantación detrás.',
            ),
        ];
    }

    /**
     * El reparto del DAFO, en el orden en que se lee la matriz.
     *
     * **Con los cuadrantes vacíos dentro**, a diferencia de casi todos los repartos
     * del producto, que filtran los tramos a cero. Aquí el cero dice algo: un DAFO
     * sin ninguna oportunidad no es un reparto incompleto, es una organización que
     * sólo ha mirado lo que le puede salir mal, y esconder la barra vacía esconde
     * justo eso.
     *
     * @return list<Reparto>
     */
    public function porTipo(): array
    {
        $cuentas = CuestionContexto::query()
            ->vigentes()
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $tramos = [];

        foreach (TipoCuestion::enOrdenDeMatriz() as $tipo) {
            $tramos[] = new Reparto(
                clave: $tipo->value,
                etiqueta: $tipo->etiqueta(),
                valor: (int) $cuentas->get($tipo->value, 0),
                tono: $tipo->tono(),
                filtro: 'filter[tipo]='.$tipo->value,
            );
        }

        return $tramos;
    }

    public function paraElPanel(): ResumenContextoPanel
    {
        $vigente = $this->analisis->vigente();

        return new ResumenContextoPanel(
            analisisVigente: $vigente?->etiqueta(),
            fechaAnalisis: $vigente?->fecha_analisis->toDateString(),
            mesesDesdeElAnalisis: $vigente instanceof AnalisisContexto
                ? (int) $vigente->fecha_analisis->diffInMonths(Carbon::today())
                : null,
            hayBorrador: $this->analisis->borrador() instanceof AnalisisContexto,
            cuestiones: $this->totalCuestiones(),
            porTipo: $this->porTipo(),
            sinRiesgo: CuestionContexto::query()->sinRiesgo()->count(),
            partes: $this->totalPartes(),
            requisitosQueObligan: RequisitoInteresado::query()->queObligan()->count(),
            obligacionesSinCubrir: RequisitoInteresado::query()->obligacionSinCubrir()->count(),
            climaPertinente: $vigente?->clima_pertinente,
        );
    }

    private function indicadorCuestion(string $clave, string $etiqueta, string $scope, string $ayuda): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: CuestionContexto::query()->{$scope}()->count(),
            tono: 'en_progreso',
            filtro: "filter[{$clave}]=1",
            base: '/contexto/cuestiones',
            ayuda: $ayuda,
        );
    }
}
