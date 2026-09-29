<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use Illuminate\Support\Collection;

/**
 * Cuánto de lo que exige una parte está cubierto, y dónde se nota.
 *
 * **Tres escalones y no dos**: obliga, tiene una medida atada y esa medida está
 * implantada. El indicador del registro sólo mira el segundo, y es a propósito
 * —atar es lo que la cláusula 4.2 pide—, pero una obligación atada a una medida sin
 * iniciar sale como cubierta sin estarlo, y la ficha es donde eso tiene que verse.
 *
 * **La SoA sólo cita controles de ISO.** `DeclaracionAplicabilidadIso` lista las
 * implantaciones de tipo `control`, así que una medida del ENS atada aquí no
 * aparece como «exigido por»: la DdA justifica desde la categoría. Decirlo en la
 * ficha evita que alguien ate una medida del ENS esperando verla en la SoA.
 *
 * Trabaja sobre los requisitos ya cargados con sus implantaciones y su requisito
 * del catálogo; la única consulta propia es la cifra del registro, que es de toda
 * la organización.
 */
final readonly class CoberturaParteInteresada
{
    /**
     * @return array{
     *     obligan: int,
     *     legales: int,
     *     contractuales: int,
     *     conMedida: int,
     *     conMedidaImplantada: int,
     *     sinMedida: int,
     *     pendientesDeImplantar: list<string>,
     *     citadasEnSoa: list<string>,
     *     medidasEnsAtadas: list<string>,
     *     partesConObligacionSinCubrir: int,
     *     cuentaEnElIndicador: bool,
     * }
     */
    public function __invoke(ParteInteresada $parte): array
    {
        /** @var Collection<int, RequisitoInteresado> $queObligan */
        $queObligan = $parte->requisitos
            ->filter(static fn (RequisitoInteresado $requisito): bool => $requisito->naturaleza->obliga())
            ->values();

        $atadas = $queObligan
            ->flatMap(static fn (RequisitoInteresado $requisito): Collection => $requisito->implantaciones)
            ->unique('id')
            ->values();

        $sinMedida = $queObligan->filter(static fn (RequisitoInteresado $requisito): bool => $requisito->estaSinCubrir())->count();

        return [
            'obligan' => $queObligan->count(),
            'legales' => $queObligan->where('naturaleza', NaturalezaRequisito::Legal)->count(),
            'contractuales' => $queObligan->where('naturaleza', NaturalezaRequisito::Contractual)->count(),
            'conMedida' => $queObligan->count() - $sinMedida,
            'conMedidaImplantada' => $queObligan
                ->filter(static fn (RequisitoInteresado $requisito): bool => $requisito->implantaciones
                    ->contains(static fn (Implantacion $implantacion): bool => $implantacion->estado === EstadoImplantacion::Implantado))
                ->count(),
            'sinMedida' => $sinMedida,
            'pendientesDeImplantar' => $this->codigos($atadas->filter(
                static fn (Implantacion $implantacion): bool => $implantacion->estado !== EstadoImplantacion::Implantado,
            )),
            'citadasEnSoa' => $this->codigos($atadas->filter(
                static fn (Implantacion $implantacion): bool => $implantacion->requisito?->tipo === TipoRequisito::Control,
            )),
            'medidasEnsAtadas' => $this->codigos($atadas->filter(
                static fn (Implantacion $implantacion): bool => $implantacion->requisito?->tipo !== TipoRequisito::Control,
            )),
            'partesConObligacionSinCubrir' => ParteInteresada::query()->conObligacionSinCubrir()->count(),
            'cuentaEnElIndicador' => $parte->estaVigente() && $sinMedida > 0,
        ];
    }

    /**
     * @param  Collection<int, Implantacion>  $implantaciones
     * @return list<string>
     */
    private function codigos(Collection $implantaciones): array
    {
        return $implantaciones
            ->map(static fn (Implantacion $implantacion): string => $implantacion->requisito->codigo ?? '—')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
