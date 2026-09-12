<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Lo que acota un calendario de vencimientos.
 *
 * **No son los filtros de tareas, y es deliberado.** El calendario enseña
 * vencimientos: la mitad de lo que sale son evidencias, que no tienen prioridad
 * ni origen ni estado de tarea. Filtrar por «prioridad: crítica» o dejaría las
 * evidencias intactas —y el filtro estaría mintiendo— o las haría desaparecer
 * sin que nadie entendiera por qué.
 *
 * Los tres que quedan significan lo mismo para las dos fuentes: de qué es,
 * de quién es, y si ya se pasó.
 *
 * Cuando § 4.16 traiga el resto de lo periódico —revisión por la dirección,
 * auditoría interna, pruebas de continuidad— seguirán valiendo los tres.
 */
final readonly class FiltrosVencimiento
{
    /**
     * @param  list<Fuente>  $fuentes  vacío es «todas»
     */
    private function __construct(
        public array $fuentes,
        public ?int $responsableId,
        public bool $soloVencidos,
    ) {}

    public static function ninguno(): self
    {
        return new self([], null, false);
    }

    /**
     * Lo que llega por la query string, con el mismo criterio que el resto del
     * producto: lo que no se entiende se ignora, no se aplica a ciegas ni
     * revienta la petición.
     */
    public static function desde(Request $peticion): self
    {
        /** @var array<string, mixed> $recibidos */
        $recibidos = $peticion->array('filter');

        $fuentes = [];

        foreach (explode(',', (string) ($recibidos['fuente'] ?? '')) as $valor) {
            $fuente = Fuente::tryFrom(trim($valor));

            if ($fuente instanceof Fuente) {
                $fuentes[] = $fuente;
            }
        }

        $responsable = $recibidos['responsable_id'] ?? null;

        return new self(
            fuentes: $fuentes,
            responsableId: is_numeric($responsable) ? (int) $responsable : null,
            soloVencidos: filter_var($recibidos['vencidos'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }

    public function quiere(Fuente $fuente): bool
    {
        return $this->fuentes === [] || in_array($fuente, $this->fuentes, true);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    public function acotar(Builder $consulta, string $columnaFecha): Builder
    {
        return $consulta
            ->when(
                $this->responsableId !== null,
                fn (Builder $q): Builder => $q->where('responsable_id', $this->responsableId),
            )
            ->when(
                $this->soloVencidos,
                // Estrictamente anterior a hoy: lo que vence hoy todavía no se ha
                // pasado, igual que en `Tarea::vencidas()` y en `caducadas()`.
                fn (Builder $q): Builder => $q->whereDate($columnaFecha, '<', Carbon::today()),
            );
    }

    public function hayAlguno(): bool
    {
        return $this->fuentes !== [] || $this->responsableId !== null || $this->soloVencidos;
    }
}
