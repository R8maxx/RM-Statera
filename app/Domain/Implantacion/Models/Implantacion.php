<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Models;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\ImplantacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El centro del modelo: une un requisito del catálogo global con un sistema de
 * una organización.
 *
 * Contesta las tres preguntas de cualquier auditoría — qué aplica, cómo se
 * cumple y dónde está la prueba — y tanto la Declaración de Aplicabilidad de ISO
 * como la del ENS son consultas sobre esta tabla, no documentos mantenidos a
 * mano.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $sistema_id
 * @property int $requisito_id
 * @property bool $aplica
 * @property ?string $justificacion
 * @property EstadoImplantacion $estado
 * @property ?NivelMadurez $nivel_madurez
 * @property ?OrigenExigencia $origen_exigencia
 * @property ?Dimension $dimension_moduladora
 * @property ?int $responsable_id
 * @property ?Carbon $fecha_objetivo
 * @property ?string $notas
 */
class Implantacion extends Model
{
    /** @use HasFactory<ImplantacionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'implantaciones';

    protected $fillable = [
        'organizacion_id',
        'sistema_id',
        'requisito_id',
        'aplica',
        'justificacion',
        'exigencia_calculada',
        'origen_exigencia',
        'dimension_moduladora',
        'estado',
        'nivel_madurez',
        'responsable_id',
        'fecha_objetivo',
        'notas',
    ];

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    /** @return BelongsTo<Requisito, $this> */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Las pruebas de que este requisito se cumple.
     *
     * N:M y en los dos sentidos (invariante 6): una misma captura puede probar
     * este control de ISO y tres medidas del ENS, y se registra una sola vez.
     *
     * @return BelongsToMany<Evidencia, $this>
     */
    public function evidencias(): BelongsToMany
    {
        return $this->belongsToMany(Evidencia::class, 'evidencia_implantacion')
            ->withPivot(['nota', 'vinculada_por_id', 'created_at'])
            ->orderByDesc('evidencias.fecha_obtencion');
    }

    /**
     * Lo que se está haciendo para cumplirlo.
     *
     * N:M por lo mismo que las evidencias: «revisar la política de contraseñas»
     * hace avanzar este control de ISO y tres medidas del ENS a la vez.
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'implantacion_tarea')
            ->withPivot(['vinculada_por_id', 'created_at'])
            ->orderByRaw("CASE WHEN tareas.estado IN ('hecha', 'descartada') THEN 1 ELSE 0 END")
            ->orderByRaw('tareas.fecha_limite NULLS LAST');
    }

    /**
     * Los riesgos que este control trata.
     *
     * La inversa de `Riesgo::salvaguardas()`, y es lo que permite que la SoA
     * justifique la inclusión de un control desde el análisis de riesgos —que es
     * lo que ISO 6.1.3 d) espera— sin registrar el vínculo por segunda vez.
     *
     * @return BelongsToMany<Riesgo, $this>
     */
    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'riesgo_implantacion')
            ->withPivot(['nota', 'vinculada_por_id', 'created_at'])
            ->orderBy('riesgos.codigo');
    }

    /** @return HasMany<ImplantacionTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(ImplantacionTransicion::class)->orderBy('created_at')->orderBy('id');
    }

    /*
     * Las columnas van cualificadas —`implantaciones.estado`, no `estado`—
     * porque quien invoca estos scopes suele venir con `requisitos` unida:
     * la consulta de la tabla, la de los documentos y la del panel. Hoy no
     * chocaría con nada, pero `marcos` ya tiene una columna `estado` y el
     * catálogo va a crecer; el error que sale entonces es un «column reference
     * is ambiguous» que no menciona el scope.
     */

    /** @param Builder<$this> $query */
    public function scopeAplicables(Builder $query): void
    {
        $query->where('implantaciones.aplica', true);
    }

    /** @param Builder<$this> $query */
    public function scopeDelSistema(Builder $query, int $sistemaId): void
    {
        $query->where('implantaciones.sistema_id', $sistemaId);
    }

    /**
     * Lo que se le exige al sistema y todavía no está implantado.
     *
     * Es **la** consulta del plan de adecuación, y a la vez la cifra
     * «Pendientes» del panel y el filtro de la tabla: los tres invocan este
     * scope y no repiten la condición, que es lo que evita que el panel diga 12
     * y la lista enseñe 9.
     *
     * No hace falta excluir `no_aplica`: un `CHECK` de la tabla acopla
     * `(estado = 'no_aplica') = (NOT aplica)`, así que lo aplicable nunca está
     * en ese estado. Añadirlo «por si acaso» sugeriría que la base puede
     * incumplirlo.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePendientes(Builder $query): void
    {
        $query->aplicables()->where('implantaciones.estado', '!=', EstadoImplantacion::Implantado->value);
    }

    /**
     * Pendientes a las que se les pasó la fecha que alguien se puso.
     *
     * `fecha_objetivo` existía desde la primera migración y **no se comparaba
     * con hoy en ningún punto del producto**: se podía escribir, filtrar por
     * rango y nada más. Aquí empieza a significar algo.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeObjetivoVencido(Builder $query): void
    {
        $query->pendientes()->whereDate('implantaciones.fecha_objetivo', '<', now()->toDateString());
    }

    /** @param Builder<$this> $query */
    public function scopeSinFechaObjetivo(Builder $query): void
    {
        $query->pendientes()->whereNull('implantaciones.fecha_objetivo');
    }

    /**
     * Pendientes sin ninguna tarea abierta detrás.
     *
     * El hallazgo que el plan de adecuación existe para enseñar: un requisito
     * que se exige, que no está puesto y del que nadie ha apuntado qué va a
     * hacer. `whereDoesntHave` y no un `join`, por lo mismo que en el inventario:
     * con dos tareas la implantación saldría dos veces y la cuenta mentiría.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinTrabajo(Builder $query): void
    {
        $query->pendientes()->whereDoesntHave('tareas', static function (Builder $tareas): void {
            /** @var Builder<Tarea> $tareas */
            // El mismo scope que cuentan el aviso diario y el tablero.
            $tareas->abiertas();
        });
    }

    /**
     * El estado que tenía justo antes de marcarse `no_aplica`.
     *
     * Cuando una medida vuelve a exigirse tras una revaloración, se restaura
     * aquí: para eso se guarda el histórico. Devuelve `null` si nunca estuvo en
     * otro estado.
     */
    public function estadoPrevioANoAplica(): ?EstadoImplantacion
    {
        $transicion = $this->transiciones()
            ->where('estado_nuevo', EstadoImplantacion::NoAplica->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $transicion?->estado_anterior;
    }

    /** @return Attribute<?Exigencia, Exigencia|string|null> */
    protected function exigenciaCalculada(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor): ?Exigencia => $valor === null ? null : Exigencia::desde($valor),
            set: fn (Exigencia|string|null $valor): ?string => $valor === null ? null : (string) $valor,
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aplica' => 'boolean',
            'estado' => EstadoImplantacion::class,
            'nivel_madurez' => NivelMadurez::class,
            'origen_exigencia' => OrigenExigencia::class,
            'dimension_moduladora' => Dimension::class,
            'fecha_objetivo' => 'date',
        ];
    }

    protected static function newFactory(): ImplantacionFactory
    {
        return ImplantacionFactory::new();
    }
}
