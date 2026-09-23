<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Persona\Models\Persona;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;

/**
 * Qué vence en la organización activa.
 *
 * **Quién construye cada fila es `CalendarioVencimientos`**, que es el único
 * sitio donde se decide qué es un vencimiento: si cada uno consultara por su
 * cuenta, el correo y el calendario acabarían discrepando y el que se mira menos
 * es el que se queda mal. Aquí sólo se eligen los grupos.
 *
 * **Se cuenta con los mismos scopes que usan el panel y los filtros de cada
 * tabla** —`Evidencia::caducadas()`, `Tarea::vencidas()`,
 * `Persona::formacionCaducada()`, `Compromiso::vencidos()`—, por el mismo motivo
 * por el que los indicadores del inventario usan `Filtro::porScope()`: con la
 * condición escrita dos veces, el día que cambie una el correo dirá 12 y la
 * pantalla enseñará 9, y a partir de ahí nadie se fía de ninguno de los dos.
 *
 * **Recorre `Fuente::cases()`**, así que una fuente nueva entra declarando su par
 * de consultas y nada más. No decide a quién se avisa ni cómo: eso es del comando
 * y de la notificación.
 */
final readonly class ResumenVencimientos
{
    /**
     * Cuánto se mira hacia delante.
     *
     * Treinta días es lo que ya enseña el panel, y es margen suficiente para
     * renovar un certificado o para hacer algo con una tarea antes de que venza.
     * La misma ventana para todas: listas con plazos distintos no se pueden leer
     * seguidas.
     *
     * **Las obligaciones son la excepción declarada.** Su propia ventana es de
     * noventa días —contratar a quien audita no se hace en un mes— y aquí se
     * respeta la del correo: lo que este resumen contesta es «qué hay para los
     * próximos treinta días», y una obligación que asome a ochenta no es eso. El
     * aviso largo vive en `/obligaciones` y en el panel.
     */
    public const DIAS = 30;

    public function __construct(private CalendarioVencimientos $calendario) {}

    public function __invoke(int $dias = self::DIAS): Vencimientos
    {
        $porFuente = [];

        foreach (Fuente::cases() as $fuente) {
            $porFuente[$fuente->value] = [
                'pasados' => $this->pasados($fuente),
                'proximos' => $this->proximos($fuente, $dias),
            ];
        }

        return new Vencimientos(porFuente: $porFuente, dias: $dias);
    }

    /**
     * Lo que ya se pasó de fecha, por el scope del módulo dueño.
     *
     * @return list<Vencimiento>
     */
    private function pasados(Fuente $fuente): array
    {
        return match ($fuente) {
            Fuente::Evidencia => $this->calendario->deEvidencias(Evidencia::query()->caducadas()),
            Fuente::Tarea => $this->calendario->deTareas(Tarea::query()->vencidas()),
            /*
             * La revisión documental va en su propio par por lo mismo que
             * evidencias y tareas van aparte: una revisión vencida no se arregla
             * como una tarea que no se hizo — se arregla volviendo a mirar el
             * documento y aprobándolo otra vez, y lo hace quien firma.
             */
            Fuente::Documento => $this->calendario->deDocumentos(Documento::query()->revisionVencida()),
            Fuente::Formacion => $this->calendario->deFormacion(Persona::query()->formacionCaducada()),
            Fuente::Indicador => $this->calendario->deIndicadores(
                Carbon::today()->subYears(5),
                Carbon::today(),
                FiltrosVencimiento::ninguno(),
            ),
            Fuente::Implantacion => $this->calendario->deImplantaciones(Implantacion::query()->objetivoVencido()),
            Fuente::Obligacion => $this->calendario->deObligaciones(Compromiso::query()->vencidos()),
            Fuente::PruebaContinuidad => $this->calendario->dePruebas(PruebaContinuidad::query()->vencidas()),
            Fuente::Bia => $this->calendario->deBias(BiaServicio::query()->revisionVencida()),
        };
    }

    /**
     * Lo que vence dentro de la ventana.
     *
     * **El indicador no tiene mitad próxima, y es deliberado.** Avisar de que el
     * trimestre en curso va a cerrar sería inventar un plazo al que nadie se
     * comprometió; lo que se mide es el periodo que ya cerró sin medición, y eso
     * es siempre pasado. Va declarado en `Fuente::Indicador`.
     *
     * @return list<Vencimiento>
     */
    private function proximos(Fuente $fuente, int $dias): array
    {
        $hoy = Carbon::today();
        $hasta = $hoy->copy()->addDays($dias);

        return match ($fuente) {
            Fuente::Evidencia => $this->calendario->deEvidencias(Evidencia::query()->porCaducar($dias)),
            Fuente::Tarea => $this->calendario->deTareas(Tarea::query()->porVencer($dias)),
            Fuente::Documento => $this->calendario->deDocumentos(Documento::query()->porRevisar($dias)),
            Fuente::Formacion => $this->calendario->deFormacion(Persona::query()->formacionPorCaducar($dias)),
            Fuente::Indicador => [],
            Fuente::Implantacion => $this->calendario->deImplantaciones(Implantacion::query()->objetivoPorVencer($dias)),
            Fuente::Obligacion => $this->calendario->deObligaciones(Compromiso::query()->proximaEntre($hoy, $hasta)),
            Fuente::PruebaContinuidad => $this->calendario->dePruebas(PruebaContinuidad::query()->porVencer($dias)),
            Fuente::Bia => $this->calendario->deBias(BiaServicio::query()->revisionPorVencer($dias)),
        };
    }
}
