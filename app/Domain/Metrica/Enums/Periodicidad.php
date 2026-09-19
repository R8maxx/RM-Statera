<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cada cuánto se mide un indicador. Cláusula 9.1 c).
 *
 * **No es `Evidencia\PeriodicidadRenovacion`, y no se reutiliza**, aunque la
 * palabra sea la misma y cuatro de los casos coincidan. Aquélla **suma meses a
 * una fecha** —«esta captura la obtuve el 3 de marzo, caduca el 3 de junio»— y
 * ésta **parte el calendario en cubos** —«el 3 de marzo cae en el primer
 * trimestre»—. Son dos operaciones distintas sobre la misma palabra, y la
 * segunda es la que ordena una serie histórica.
 *
 * Y falta `Bienal` a propósito: existe allí porque la conformidad del ENS se
 * renueva cada dos años (§ 4.16), y a esa cadencia no hay serie, hay dos puntos.
 *
 * El motivo de fondo es la dirección de la dependencia: compartirla haría que
 * `Domain\Metrica` supiera de `Domain\Evidencia`, y ofrecería «bienal» en un
 * cuadro de mando donde no significa nada.
 */
#[TypeScript]
enum Periodicidad: string
{
    case Mensual = 'mensual';
    case Trimestral = 'trimestral';
    case Semestral = 'semestral';
    case Anual = 'anual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Trimestral => 'Trimestral',
            self::Semestral => 'Semestral',
            self::Anual => 'Anual',
        };
    }

    public function meses(): int
    {
        return match ($this) {
            self::Mensual => 1,
            self::Trimestral => 3,
            self::Semestral => 6,
            self::Anual => 12,
        };
    }

    /**
     * El periodo en el que cae una fecha, con sus dos extremos.
     *
     * Devuelve siempre los dos, y por eso `mediciones` tiene dos columnas y no
     * una: una medición fechada «3 de marzo» no dice a qué trimestre pertenece
     * si se registró tarde, y la serie se desordena sola.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodoDe(CarbonInterface $fecha): array
    {
        $dia = CarbonImmutable::parse($fecha->toDateString())->startOfDay();

        $inicio = match ($this) {
            self::Mensual => $dia->startOfMonth(),
            self::Trimestral => $dia->startOfQuarter(),
            // Carbon no tiene semestre: sale del mes, que dice en qué mitad cae.
            self::Semestral => $dia->startOfYear()->addMonths($dia->month <= 6 ? 0 : 6),
            self::Anual => $dia->startOfYear(),
        };

        return [$inicio, $inicio->addMonths($this->meses())->subDay()];
    }

    /**
     * El periodo anterior al que contiene esa fecha.
     *
     * Es el que mide el comando programado: un periodo se cierra cuando ha
     * terminado, y medir el que está en curso daría una cifra a medias que
     * además habría que corregir al día siguiente.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodoAnteriorA(CarbonInterface $fecha): array
    {
        [$inicio] = $this->periodoDe($fecha);

        return $this->periodoDe($inicio->subDay());
    }

    /**
     * Cómo se nombra un periodo en una tabla y en un acta.
     *
     * «T1 2026» y no «01/01/2026 – 31/03/2026»: la serie se lee en columna y el
     * rango entero la vuelve ilegible. Las fechas exactas las lleva la fila.
     */
    public function etiquetaDe(CarbonInterface $inicio): string
    {
        return match ($this) {
            self::Mensual => ucfirst($inicio->locale('es')->isoFormat('MMMM [de] YYYY')),
            self::Trimestral => 'T'.$inicio->quarter.' '.$inicio->year,
            self::Semestral => 'S'.($inicio->month <= 6 ? 1 : 2).' '.$inicio->year,
            self::Anual => (string) $inicio->year,
        };
    }
}
