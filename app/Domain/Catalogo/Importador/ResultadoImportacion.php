<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Importador;

/**
 * Diff de una importación: qué entra nuevo, qué cambia, qué desaparece y a
 * cuántas implantaciones afectaría. Se rellena igual en modo simulación que en
 * modo real; lo único que cambia es si se confirma la transacción.
 */
final class ResultadoImportacion
{
    /** @var list<string> */
    public array $nuevos = [];

    /** @var list<array{codigo: string, cambios: list<string>}> */
    public array $modificados = [];

    /** @var list<string> */
    public array $retirados = [];

    /** @var list<string> */
    public array $reactivados = [];

    public int $sinCambios = 0;

    public int $refuerzos = 0;

    public int $celdasAplicabilidad = 0;

    public int $mapeosNuevos = 0;

    public int $mapeosActualizados = 0;

    public int $perfiles = 0;

    public int $implantacionesAfectadas = 0;

    public function __construct(
        public readonly string $fichero,
        public readonly string $tipo,
        public readonly bool $simulacion,
        public readonly ?string $marco = null,
    ) {}

    public function hayCambios(): bool
    {
        return $this->nuevos !== []
            || $this->modificados !== []
            || $this->retirados !== []
            || $this->reactivados !== []
            || $this->mapeosNuevos > 0
            || $this->mapeosActualizados > 0;
    }

    /** @return array{nuevos: int, modificados: int, retirados: int, reactivados: int, sin_cambios: int} */
    public function resumen(): array
    {
        return [
            'nuevos' => count($this->nuevos),
            'modificados' => count($this->modificados),
            'retirados' => count($this->retirados),
            'reactivados' => count($this->reactivados),
            'sin_cambios' => $this->sinCambios,
        ];
    }
}
