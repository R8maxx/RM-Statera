<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Tarea\Models\Tarea;

/**
 * Qué vence en la organización activa.
 *
 * **Quién construye cada fila es `CalendarioVencimientos`**, que es el único
 * sitio donde se decide qué es un vencimiento: si cada uno consultara por su
 * cuenta, el correo y el calendario acabarían discrepando y el que se mira menos
 * es el que se queda mal. Aquí sólo se eligen los cuatro grupos.
 *
 * **Se cuenta con los mismos scopes que usan el panel y los filtros de cada
 * tabla** —`Evidencia::caducadas()`, `porCaducar()`, `Tarea::vencidas()`,
 * `porVencer()`—, por el mismo motivo por el que los indicadores del inventario
 * usan `Filtro::porScope()`: con la condición escrita dos veces, el día que
 * cambie una el correo dirá 12 y la pantalla enseñará 9, y a partir de ahí nadie
 * se fía de ninguno de los dos.
 *
 * No decide a quién se avisa ni cómo: eso es del comando y de la notificación.
 * Aquí sólo se contesta a la pregunta.
 */
final readonly class ResumenVencimientos
{
    /**
     * Cuánto se mira hacia delante.
     *
     * Treinta días es lo que ya enseña el panel, y es margen suficiente para
     * renovar un certificado o para hacer algo con una tarea antes de que venza.
     * La misma ventana para las dos cosas: dos listas con plazos distintos no se
     * pueden leer seguidas.
     */
    public const DIAS = 30;

    public function __construct(private CalendarioVencimientos $calendario) {}

    public function __invoke(int $dias = self::DIAS): Vencimientos
    {
        return new Vencimientos(
            evidenciasCaducadas: $this->calendario->deEvidencias(Evidencia::query()->caducadas()),
            evidenciasPorCaducar: $this->calendario->deEvidencias(Evidencia::query()->porCaducar($dias)),
            tareasVencidas: $this->calendario->deTareas(Tarea::query()->vencidas()),
            tareasPorVencer: $this->calendario->deTareas(Tarea::query()->porVencer($dias)),
            dias: $dias,
        );
    }
}
