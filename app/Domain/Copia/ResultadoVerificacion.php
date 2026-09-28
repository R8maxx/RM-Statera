<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use Illuminate\Support\Carbon;

/**
 * Lo que salió de restaurar una copia y compararla.
 *
 * Una copia es buena si las dos comparaciones cuadran: **cada tabla con el
 * número de filas que tenía al volcarse**, y **cada fichero que la base nombra
 * con su huella**. Una base restaurada que apunta a evidencias que no están no
 * es una copia, es un índice.
 */
final readonly class ResultadoVerificacion
{
    /**
     * @param  array<string, array{esperadas: int|null, restauradas: int|null}>  $tablasDistintas
     * @param  list<string>  $ficherosAusentes
     * @param  list<string>  $ficherosDistintos
     */
    public function __construct(
        public Manifiesto $manifiesto,
        public Carbon $verificada_en,
        public int $tablas,
        public array $tablasDistintas,
        public int $ficherosComprobados,
        public array $ficherosAusentes,
        public array $ficherosDistintos,
    ) {}

    public function correcta(): bool
    {
        return $this->tablasDistintas === []
            && $this->ficherosAusentes === []
            && $this->ficherosDistintos === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'copia' => $this->manifiesto->nombre,
            'verificada_en' => $this->verificada_en->toIso8601String(),
            'correcta' => $this->correcta(),
            'tablas' => $this->tablas,
            'tablas_distintas' => $this->tablasDistintas,
            'ficheros_comprobados' => $this->ficherosComprobados,
            'ficheros_ausentes' => $this->ficherosAusentes,
            'ficheros_distintos' => $this->ficherosDistintos,
        ];
    }
}
