<?php

declare(strict_types=1);

namespace App\Domain\Evidencia;

use App\Domain\Evidencia\Models\Evidencia;
use App\Http\Resources\Definicion\ValorEtiquetado;
use Illuminate\Support\Carbon;

/**
 * Hasta cuándo prueba una evidencia, dicho de una vez para la tabla y la ficha.
 *
 * Vivía en `EvidenciaRecurso` como un badge, y la ficha pintaba el suyo con
 * otra regla: «Sin caducidad» para una evidencia renovada cada seis meses a la
 * que el seeder no le había calculado la fecha. Aquí se decide el estado y el
 * periodo, y la tabla y la ficha lo leen igual.
 *
 * **Cinco estados, y dos no son «vigente»**: «sin caducidad» es que nadie ha
 * dicho cuándo deja de valer —colapsarlo con vigente escondería justo las que
 * nadie ha revisado—, y «renovada» es que ya tiene sustituta, así que su
 * vencimiento no le pide nada a nadie.
 */
final readonly class Vigencia
{
    /** Lo mismo que cuenta `Evidencia::porCaducar()`: el aviso y el badge no discuten. */
    public const DIAS_DE_AVISO = 30;

    private function __construct(
        public string $estado,
        public string $etiqueta,
        public string $tono,
        public string $icono,
        public string $desde,
        public ?string $hasta,
        public ?int $diasTotales,
        public ?int $diasTranscurridos,
        public ?int $diasRestantes,
    ) {}

    public static function de(Evidencia $evidencia): self
    {
        $desde = $evidencia->fecha_obtencion->toDateString();
        $caducidad = $evidencia->fecha_caducidad;

        if ($evidencia->estaRenovada()) {
            return new self('renovada', 'Renovada', 'no_aplica', 'Repeat', $desde, $caducidad?->toDateString(), null, null, null);
        }

        if ($caducidad === null) {
            return new self('sin_caducidad', 'Sin caducidad', 'no_iniciado', 'CircleHelp', $desde, null, null, null, null);
        }

        $hoy = Carbon::today();
        $total = max((int) $evidencia->fecha_obtencion->diffInDays($caducidad), 1);
        $transcurridos = min(max((int) $evidencia->fecha_obtencion->diffInDays($hoy, absolute: false), 0), $total);
        $restantes = (int) $hoy->diffInDays($caducidad, absolute: false);

        [$estado, $etiqueta, $tono, $icono] = match (true) {
            $restantes < 0 => ['caducada', 'Caducada', 'caducada', 'TriangleAlert'],
            $restantes <= self::DIAS_DE_AVISO => ['por_caducar', self::caducaEn($restantes), 'en_progreso', 'Clock'],
            default => ['vigente', 'Vigente', 'implantado', 'CircleCheck'],
        };

        return new self($estado, $etiqueta, $tono, $icono, $desde, $caducidad->toDateString(), $total, $transcurridos, $restantes);
    }

    public function badge(): ValorEtiquetado
    {
        return new ValorEtiquetado($this->hasta, $this->etiqueta, $this->tono, $this->icono);
    }

    /**
     * @return array{estado: string, etiqueta: string, tono: string, icono: string, desde: string, hasta: ?string, diasTotales: ?int, diasTranscurridos: ?int, diasRestantes: ?int}
     */
    public function toArray(): array
    {
        return [
            'estado' => $this->estado,
            'etiqueta' => $this->etiqueta,
            'tono' => $this->tono,
            'icono' => $this->icono,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'diasTotales' => $this->diasTotales,
            'diasTranscurridos' => $this->diasTranscurridos,
            'diasRestantes' => $this->diasRestantes,
        ];
    }

    private static function caducaEn(int $dias): string
    {
        return match ($dias) {
            0 => 'Caduca hoy',
            1 => 'Caduca mañana',
            default => "Caduca en {$dias} días",
        };
    }
}
