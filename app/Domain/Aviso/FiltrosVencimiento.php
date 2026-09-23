<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Lo que acota un calendario de vencimientos.
 *
 * **No son los filtros de tareas, y es deliberado.** El calendario enseña
 * vencimientos de muchas fuentes: filtrar por «prioridad: crítica» o dejaría
 * las demás intactas —y el filtro estaría mintiendo— o las haría desaparecer
 * sin que nadie entendiera por qué.
 *
 * Los tres que quedan significan lo mismo para todas: de qué es, de quién es, y
 * si ya se pasó.
 *
 * **Aquí vive además la guarda por permiso**, y no en `CalendarioVencimientos`.
 * Un `Recurso` describe y no autoriza, y esto es lo mismo un nivel más abajo: lo
 * que decide qué se consulta es este objeto, así que es donde tiene que estar la
 * frontera. La rejilla enseña registros de seis módulos con una sola llave, y sin
 * esto `calendario.ver` sería una puerta lateral a los seis.
 */
final readonly class FiltrosVencimiento
{
    /**
     * @param  list<Fuente>  $fuentes  vacío es «todas»
     * @param  list<Fuente>|null  $visibles  nulo es «sin cuenta detrás»: el comando
     */
    private function __construct(
        public array $fuentes,
        public ?int $responsableId,
        public bool $soloVencidos,
        private ?array $visibles = null,
    ) {}

    public static function ninguno(): self
    {
        return new self([], null, false);
    }

    /**
     * Lo que llega por la query string, con el mismo criterio que el resto del
     * producto: lo que no se entiende se ignora, no se aplica a ciegas ni
     * revienta la petición.
     *
     * Sin usuario —el comando de avisos, que corre en cola y no tiene sesión— no
     * hay guarda que aplicar: el destinatario del correo diario ya lo decide
     * `EnviarAvisosCommand`, que manda al responsable de seguridad.
     */
    public static function desde(Request $peticion, ?User $usuario = null): self
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
        $usuario ??= $peticion->user();

        return new self(
            fuentes: $fuentes,
            responsableId: is_numeric($responsable) ? (int) $responsable : null,
            soloVencidos: filter_var($recibidos['vencidos'] ?? false, FILTER_VALIDATE_BOOL),
            visibles: $usuario instanceof User ? Fuente::visiblesPara($usuario) : null,
        );
    }

    /**
     * Si esta fuente entra en la consulta.
     *
     * Tres condiciones, y la tercera es la que no se ve venir: **con un filtro de
     * responsable puesto, las fuentes que no tienen responsable se excluyen**. La
     * alternativa —dejarlas intactas— es literalmente el fallo que esta clase
     * declara inaceptable: el filtro estaría mintiendo, porque las sesiones de
     * formación seguirían saliendo al filtrar por una persona que no es la suya.
     * Excluirlas se puede explicar en el estado vacío; lo otro, no.
     */
    public function quiere(Fuente $fuente): bool
    {
        if ($this->visibles !== null && ! in_array($fuente, $this->visibles, true)) {
            return false;
        }

        if ($this->responsableId !== null && ! $this->tieneResponsable($fuente)) {
            return false;
        }

        return $this->fuentes === [] || in_array($fuente, $this->fuentes, true);
    }

    /**
     * Si la fuente sabe de quién es.
     *
     * Sólo la formación no lo sabe, y no es un descuido del modelo: lo que vence
     * es que a una persona le toca renovarla, y esa persona **es** la fila. Poner
     * un `responsable_id` en `personas` sería inventar un capataz por cada
     * empleado.
     */
    private function tieneResponsable(Fuente $fuente): bool
    {
        return $fuente !== Fuente::Formacion;
    }

    /**
     * Qué fuentes quedan fuera **por no tener responsable**, no por no pedirse.
     *
     * Lo lee la pantalla para decirlo: excluir la formación al filtrar por
     * responsable es defendible sólo si se explica, y el docblock de `quiere()`
     * prometía esa explicación desde el primer día sin que existiera en ningún
     * sitio. Ahora sale en el estado vacío y al pie de la rejilla.
     *
     * @return list<Fuente>
     */
    public function excluidasPorResponsable(): array
    {
        if ($this->responsableId === null) {
            return [];
        }

        return array_values(array_filter(
            Fuente::cases(),
            fn (Fuente $fuente): bool => ! $this->tieneResponsable($fuente),
        ));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @param  string|null  $relacionFecha  cuando la fecha no está en la tabla consultada
     * @return Builder<TModel>
     */
    public function acotar(Builder $consulta, string $columnaFecha, ?string $relacionFecha = null): Builder
    {
        // Estrictamente anterior a hoy: lo que vence hoy todavía no se ha pasado,
        // igual que en `Tarea::vencidas()` y en `Evidencia::caducadas()`.
        $vencido = fn (Builder $q): Builder => $q->whereDate($columnaFecha, '<', Carbon::today());

        return $consulta
            ->when(
                $this->responsableId !== null,
                fn (Builder $q): Builder => $q->where('responsable_id', $this->responsableId),
            )
            ->when(
                $this->soloVencidos,
                /*
                 * Un documento no tiene fecha propia: lo que vence es la revisión
                 * de su versión aprobada, así que la condición baja a la relación
                 * en vez de buscar una columna que no existe en `documentos`. Es
                 * la misma razón por la que el recuento va por `whereHas` y no
                 * por `join`.
                 */
                fn (Builder $q): Builder => $relacionFecha === null
                    ? $vencido($q)
                    : $q->whereHas($relacionFecha, $vencido),
            );
    }

    /**
     * Lo mismo para las fuentes cuya fecha es una expresión y no una columna: la
     * renovación de una formación y la próxima de un compromiso se calculan.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    public function acotarCalculado(Builder $consulta, string $expresionFecha): Builder
    {
        return $consulta
            ->when(
                $this->responsableId !== null,
                fn (Builder $q): Builder => $q->where('responsable_id', $this->responsableId),
            )
            ->when(
                $this->soloVencidos,
                fn (Builder $q): Builder => $q->whereRaw($expresionFecha.' < ?', [Carbon::today()->toDateString()]),
            );
    }

    public function hayAlguno(): bool
    {
        return $this->fuentes !== [] || $this->responsableId !== null || $this->soloVencidos;
    }
}
