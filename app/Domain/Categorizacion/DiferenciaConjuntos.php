<?php

declare(strict_types=1);

namespace App\Domain\Categorizacion;

/**
 * Diferencia entre dos conjuntos exigibles.
 *
 * Cuando cambia una valoración, el sistema NO borra implantaciones: marca las
 * que dejan de aplicar, crea las nuevas en `no_iniciado` y avisa de la
 * diferencia (§3 de la especificación). Esto es esa diferencia, calculada sin
 * tocar la base de datos, para que el punto 3 sólo tenga que persistirla.
 *
 * @phpstan-type Cambio array{codigo: string, anterior: MedidaExigible, nueva: MedidaExigible}
 */
final readonly class DiferenciaConjuntos
{
    /**
     * @param  list<MedidaExigible>  $nuevas  Medidas que antes no se exigían.
     * @param  list<MedidaExigible>  $dejanDeAplicar  Medidas que ya no se exigen; sus implantaciones se marcan, no se borran.
     * @param  list<Cambio>  $cambianDeExigencia  Siguen exigiéndose, pero a otro nivel de refuerzo.
     */
    private function __construct(
        public array $nuevas,
        public array $dejanDeAplicar,
        public array $cambianDeExigencia,
    ) {}

    public static function entre(ConjuntoExigible $anterior, ConjuntoExigible $nuevo): self
    {
        $nuevas = [];
        $cambian = [];

        foreach ($nuevo as $codigo => $medida) {
            $previa = $anterior->paraCodigo($codigo);

            if ($previa === null) {
                $nuevas[] = $medida;

                continue;
            }

            if (! $previa->exigencia->equivale($medida->exigencia)) {
                $cambian[] = ['codigo' => $codigo, 'anterior' => $previa, 'nueva' => $medida];
            }
        }

        $dejan = [];

        foreach ($anterior as $codigo => $medida) {
            if (! $nuevo->exige($codigo)) {
                $dejan[] = $medida;
            }
        }

        return new self($nuevas, $dejan, $cambian);
    }

    public function hayCambios(): bool
    {
        return $this->nuevas !== [] || $this->dejanDeAplicar !== [] || $this->cambianDeExigencia !== [];
    }

    /** @return array{nuevas: int, dejan_de_aplicar: int, cambian_de_exigencia: int} */
    public function resumen(): array
    {
        return [
            'nuevas' => count($this->nuevas),
            'dejan_de_aplicar' => count($this->dejanDeAplicar),
            'cambian_de_exigencia' => count($this->cambianDeExigencia),
        ];
    }
}
