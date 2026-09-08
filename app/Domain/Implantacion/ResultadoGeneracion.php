<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

/**
 * Diff de una generación o recálculo: qué entra, qué deja de exigirse, qué
 * cambia de nivel y qué se queda igual.
 *
 * Mismo papel que ResultadoImportacion en el catálogo, y a propósito la misma
 * forma: el recálculo, como la importación, informa de a qué afecta y no
 * modifica nada en silencio.
 */
final class ResultadoGeneracion
{
    /** @var list<string> */
    public array $creadas = [];

    /** @var list<string> */
    public array $reactivadas = [];

    /** @var list<string> */
    public array $dejanDeAplicar = [];

    /** @var list<array{codigo: string, anterior: string, nueva: string}> */
    public array $cambianExigencia = [];

    public int $sinCambios = 0;

    public function __construct(
        public readonly int $sistemaId,
        public readonly string $sistema,
        public readonly string $marco,
        public readonly ?string $categoria,
        public readonly bool $simulacion = false,
    ) {}

    public function hayCambios(): bool
    {
        return $this->creadas !== []
            || $this->reactivadas !== []
            || $this->dejanDeAplicar !== []
            || $this->cambianExigencia !== [];
    }

    /** @return array{creadas: int, reactivadas: int, dejan_de_aplicar: int, cambian_exigencia: int, sin_cambios: int} */
    public function resumen(): array
    {
        return [
            'creadas' => count($this->creadas),
            'reactivadas' => count($this->reactivadas),
            'dejan_de_aplicar' => count($this->dejanDeAplicar),
            'cambian_exigencia' => count($this->cambianExigencia),
            'sin_cambios' => $this->sinCambios,
        ];
    }
}
