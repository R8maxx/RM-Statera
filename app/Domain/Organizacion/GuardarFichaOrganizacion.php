<?php

declare(strict_types=1);

namespace App\Domain\Organizacion;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Proveedor\RecalcularReevaluacion;
use App\Domain\Vulnerabilidad\PlazoRemediacion;

/**
 * Escribe la ficha de la organización.
 *
 * Vive en el dominio y no en el controlador porque las dos normalizaciones que
 * hace valen igual para un importador o para el seeder, que es el criterio de
 * siempre en este repositorio.
 *
 * **La cadena vacía se convierte en nulo, y eso no es cosmética.**
 * `GeneradorEtiquetas` resolvía la base de las etiquetas con `??`: mientras la
 * columna sólo la escribían los seeders nunca llegaba a ser `''`, pero con un
 * formulario delante vaciar el campo manda cadena vacía, que **atraviesa el
 * `??`** y produce QR contra `/activos/3` — sin host, en una pegatina que
 * alguien imprime y pega durante años. Aquí se tapa en origen; en
 * `GeneradorEtiquetas` se tapó además el `??`, porque una sola capa se olvida.
 *
 * **El CIF se normaliza a mayúsculas y sin separadores**, igual que el NIF de
 * una persona: sin eso «b-1234 5678» y «B12345678» son dos CIF distintos frente
 * al índice único. No se valida la letra, y tampoco por el mismo motivo que
 * allí: un NIF de autónomo y un CIF extranjero no siguen la misma regla, y
 * rechazarlos sería impedir dar de alta a un cliente real.
 */
final readonly class GuardarFichaOrganizacion
{
    /** Los campos donde una cadena vacía significa «no hay dato», no «cadena». */
    private const OPCIONALES = [
        'razon_social',
        'cif',
        'sector',
        'domicilio',
        'codigo_postal',
        'municipio',
        'provincia',
        'url_base_etiquetas',
    ];

    /**
     * @param  array<string, mixed>  $datos
     */
    public function __invoke(Organizacion $organizacion, array $datos): Organizacion
    {
        foreach (self::OPCIONALES as $campo) {
            if (array_key_exists($campo, $datos)) {
                $datos[$campo] = self::oNulo($datos[$campo]);
            }
        }

        if (($datos['cif'] ?? null) !== null) {
            $datos['cif'] = self::normalizarCif((string) $datos['cif']);
        }

        $organizacion->update($datos);

        /*
         * Cambiar los meses mueve la próxima evaluación de todos sus
         * proveedores, que es una copia derivada: sin esto el calendario
         * seguiría con los plazos de la política anterior.
         */
        if ($organizacion->wasChanged([
            'reevaluacion_proveedor_alta_meses',
            'reevaluacion_proveedor_media_meses',
            'reevaluacion_proveedor_baja_meses',
        ])) {
            app(RecalcularReevaluacion::class)->todos();
        }

        // Lo mismo con el plazo de remediación de las vulnerabilidades.
        if ($organizacion->wasChanged([
            'plazo_vulnerabilidad_critica_dias',
            'plazo_vulnerabilidad_alta_dias',
            'plazo_vulnerabilidad_media_dias',
            'plazo_vulnerabilidad_baja_dias',
        ])) {
            app(PlazoRemediacion::class)->todas();
        }

        return $organizacion;
    }

    /** Mayúsculas y sólo letras y dígitos: ni guiones, ni puntos, ni espacios. */
    public static function normalizarCif(string $cif): string
    {
        return mb_strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $cif));
    }

    private static function oNulo(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        return trim($valor) === '' ? null : trim($valor);
    }
}
