<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Fuentes;

use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Lo que se sabe fuera de un CVE, para rellenar el alta: NVD para los datos y
 * CISA KEV para saber si se está explotando.
 *
 * **No escribe nada.** Devuelve lo que el formulario puede rellenar, y quien lo
 * registra lo revisa antes de guardar. Tampoco puntúa: la severidad la sigue
 * derivando `Severidad::desdeCvss()` de la puntuación que acabe en el campo.
 *
 * **Las dos fuentes caen por separado.** Sin NVD no hay nada que rellenar y se
 * dice; sin KEV se rellena igual y se avisa de que la marca de explotación no
 * se ha podido comprobar, que no es lo mismo que «no se explota».
 *
 * Y mira si ese CVE ya está registrado en la organización: lo que evita, más
 * que ninguna otra cosa, es la misma vulnerabilidad dada de alta dos veces.
 */
final readonly class ConsultarCve
{
    private const TITULO = 120;

    public function __construct(private ConsultaNvd $nvd, private CatalogoKev $kev) {}

    /**
     * @return array{estado: string, mensaje: string|null, datos: array<string, mixed>|null, yaRegistrada: array{id: int, codigo: string}|null}
     */
    public function consultar(string $cve, ?int $excepto = null): array
    {
        $cve = strtoupper(trim($cve));
        $yaRegistrada = $this->yaRegistrada($cve, $excepto);

        if (! (bool) config('services.nvd.activa')) {
            return $this->respuesta('desactivada', 'La consulta de CVE está apagada en la configuración de esta instalación.', null, $yaRegistrada);
        }

        try {
            $nvd = $this->nvd->consultar($cve);
        } catch (FuenteNoDisponible $e) {
            return $this->respuesta('no_disponible', "{$e->getMessage()} Se puede seguir rellenando a mano.", null, $yaRegistrada);
        }

        if ($nvd === null) {
            return $this->respuesta('no_encontrado', "NVD no conoce {$cve}. Revisa el identificador; si es reciente, puede que aún no esté publicado.", null, $yaRegistrada);
        }

        try {
            $kev = $this->kev->buscar($cve);
            $kevConsultado = true;
        } catch (FuenteNoDisponible) {
            $kev = null;
            $kevConsultado = false;
        }

        return $this->respuesta('encontrado', $nvd['rechazada'] ? "NVD marca {$cve} como rechazado: no es una vulnerabilidad válida." : null, [
            ...$nvd,
            'titulo' => $kev['nombre'] ?? $this->titulo($nvd['descripcion']),
            'kev' => $kev,
            'kevConsultado' => $kevConsultado,
            'consultadoEl' => Carbon::today()->toDateString(),
        ], $yaRegistrada);
    }

    /**
     * Un título sugerido desde la descripción: su primera frase, cortada por
     * palabra. KEV trae un nombre de verdad y, cuando lo hay, manda ése.
     */
    private function titulo(?string $descripcion): ?string
    {
        if ($descripcion === null) {
            return null;
        }

        $frase = preg_split('/(?<=[.!?])\s/u', $descripcion, 2)[0] ?? $descripcion;

        return Str::limit(rtrim(trim($frase), '.'), self::TITULO, '…', preserveWords: true);
    }

    /** @return array{id: int, codigo: string}|null */
    private function yaRegistrada(string $cve, ?int $excepto): ?array
    {
        $existente = Vulnerabilidad::query()
            ->where('cve', $cve)
            ->when($excepto !== null, fn ($consulta) => $consulta->whereKeyNot($excepto))
            ->orderBy('id')
            ->first(['id', 'codigo']);

        return $existente === null ? null : ['id' => $existente->id, 'codigo' => $existente->codigo];
    }

    /**
     * @param  array<string, mixed>|null  $datos
     * @param  array{id: int, codigo: string}|null  $yaRegistrada
     * @return array{estado: string, mensaje: string|null, datos: array<string, mixed>|null, yaRegistrada: array{id: int, codigo: string}|null}
     */
    private function respuesta(string $estado, ?string $mensaje, ?array $datos, ?array $yaRegistrada): array
    {
        return ['estado' => $estado, 'mensaje' => $mensaje, 'datos' => $datos, 'yaRegistrada' => $yaRegistrada];
    }
}
