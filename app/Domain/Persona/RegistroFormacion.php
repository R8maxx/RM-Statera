<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Models\AccionFormativa;
use App\Http\Resources\Panel\Indicador;

/**
 * Lo que pide atención en el registro de sesiones formativas.
 *
 * **Sin alertas, y es la decisión del módulo.** Formación no gasta rojo: quien
 * no está formado es una persona, no una sesión, y esa cifra vive en
 * `/personas` con su filtro. Repetirla aquí sobre otro denominador —sesiones en
 * vez de personas— daría dos números que parecen el mismo y no lo son.
 *
 * Lo que sí es de aquí es la **convocatoria vacía**: una sesión registrada a la
 * que nadie apuntó a nadie no prueba nada, y hasta ahora esa cifra se pintaba
 * como texto plano sin poder pulsarse, pese a que su filtro existía. Eso es
 * exactamente el callejón sin salida que `Indicador` existe para cerrar.
 *
 * Vive en el dominio y no en el controlador por lo mismo que sus hermanos: son
 * preguntas del negocio, no de una pantalla, y la clave del indicador **es** la
 * del filtro, que es lo que garantiza que pulsar la cifra enseñe exactamente
 * esa cifra.
 *
 * **Y no declara `alertas()`, a propósito.** `AlertasTest` recorre el dominio
 * buscando registros que la tengan y exige que estén en
 * `AlertasDelPanel::FUENTES`; declararla aquí devolviendo una lista vacía
 * obligaría a registrar en esa lista —cuyo orden **es** el de la tira del
 * panel— una fuente que no puede aportar nada nunca. Que el método no exista
 * dice lo mismo y no cuesta una entrada. La tira de `/formacion` recibe su
 * lista vacía desde el controlador, con el motivo escrito al lado.
 */
final readonly class RegistroFormacion
{
    public function total(): int
    {
        return AccionFormativa::query()->count();
    }

    /**
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            new Indicador(
                clave: 'sin_asistencia',
                etiqueta: 'Sin nadie convocado',
                valor: AccionFormativa::query()->sinAsistencia()->count(),
                tono: 'en_progreso',
                filtro: 'filter[sin_asistencia]=1',
                base: '/formacion',
                ayuda: 'Sesiones registradas a las que todavía no se ha apuntado a nadie: una convocatoria vacía no prueba que se impartiera.',
            ),
        ];
    }
}
