<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cómo van las copias, para la salud del servicio (punto 55).
 *
 * Lee lo que dejan `copias:hacer` y `copias:verificar` en el disco `copias`:
 * los manifiestos de `bases/` y los informes de `verificaciones/`. No hay
 * tabla, y no hace falta: los informes son la fuente, y viven junto a la copia.
 *
 * **Si el disco no responde, lo dice en vez de reventar.** Una pantalla de
 * salud que da un 500 cuando el almacenamiento cae es justo la que no sirve.
 */
final class EstadoDeLasCopias
{
    /** Una copia diaria: más de esto es que el planificador no la hizo. */
    public const HORAS_MAXIMAS_SIN_COPIA = 26;

    /** Una verificación semanal, con un día de margen. */
    public const DIAS_MAXIMOS_SIN_VERIFICAR = 8;

    /**
     * @return array{disponible: bool, ultimaCopia: ?string, copiaAlDia: bool, ultimaVerificacion: ?string, verificacionCorrecta: ?bool, verificacionAlDia: bool, detalleVerificacion: ?array<string, mixed>}
     */
    public function resumen(?Carbon $ahora = null): array
    {
        $ahora ??= Carbon::now();

        try {
            $disco = Storage::disk((string) config('copias.disco'));
            $copias = HacerCopia::copias($disco);
            $verificaciones = $disco->files(VerificarCopia::PREFIJO);
        } catch (Throwable) {
            return [
                'disponible' => false,
                'ultimaCopia' => null,
                'copiaAlDia' => false,
                'ultimaVerificacion' => null,
                'verificacionCorrecta' => null,
                'verificacionAlDia' => false,
                'detalleVerificacion' => null,
            ];
        }

        $ultimaCopia = isset($copias[0]) ? $this->fechaDelNombre($copias[0]) : null;

        rsort($verificaciones);
        $informe = null;

        foreach ($verificaciones as $ruta) {
            if (str_ends_with($ruta, '.json')) {
                $informe = json_decode((string) $disco->get($ruta), true);
                break;
            }
        }

        $verificadaEn = is_array($informe) && is_string($informe['verificada_en'] ?? null)
            ? Carbon::parse($informe['verificada_en'])
            : null;

        return [
            'disponible' => true,
            'ultimaCopia' => $ultimaCopia?->toIso8601String(),
            'copiaAlDia' => $ultimaCopia !== null && $ultimaCopia->gt($ahora->copy()->subHours(self::HORAS_MAXIMAS_SIN_COPIA)),
            'ultimaVerificacion' => $verificadaEn?->toIso8601String(),
            'verificacionCorrecta' => is_array($informe) ? (bool) ($informe['correcta'] ?? false) : null,
            'verificacionAlDia' => $verificadaEn !== null
                && $verificadaEn->gt($ahora->copy()->subDays(self::DIAS_MAXIMOS_SIN_VERIFICAR))
                && (bool) ($informe['correcta'] ?? false),
            'detalleVerificacion' => is_array($informe) ? $informe : null,
        ];
    }

    /** Las copias se llaman con su instante: `2026-10-08T020000`. */
    private function fechaDelNombre(string $nombre): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d\THis', $nombre) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
