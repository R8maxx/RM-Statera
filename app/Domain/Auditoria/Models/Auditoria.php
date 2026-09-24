<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Models;

use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Documento\Models\Documento;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Auditoria\AuditoriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Una auditoría sobre un sistema: § 4.12 y la cláusula 9.2 de ISO.
 *
 * **Cuelga de un sistema y es obligatorio.** El SGSI es un `sistema` —la § 2.2 lo
 * llama «la unidad de alcance y de certificación»—, así que una auditoría de
 * certificación ISO es la auditoría de ese sistema. Sin él no hay checklist que
 * precargar, que es la mitad del módulo.
 *
 * **Cerrarla es lo que la congela**, y no es una preferencia: un trigger de
 * PostgreSQL deja de admitir cambios en sus puntos y en sus hallazgos a partir de
 * ese momento. Lo que se le enseña al auditor siguiente tiene que poder
 * demostrarse tal cual se cerró, exactamente igual que la versión de un documento
 * emitido y la valoración de un riesgo aceptado.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $sistema_id
 * @property string $codigo
 * @property TipoAuditoria $tipo
 * @property EstadoAuditoria $estado
 * @property ?string $alcance
 * @property ?string $criterios
 * @property ?string $metodo
 * @property Carbon $fecha
 * @property ?string $auditor
 * @property ?string $equipo
 * @property ?string $entidad_certificadora
 * @property ?string $resultado
 * @property ?string $conclusiones
 * @property ?Carbon $fecha_cierre
 * @property ?int $cerrada_por_id
 */
class Auditoria extends Model
{
    /** @use HasFactory<AuditoriaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'auditorias';

    protected $fillable = [
        'organizacion_id',
        'sistema_id',
        'codigo',
        'tipo',
        'estado',
        'alcance',
        'criterios',
        'metodo',
        'fecha',
        'auditor',
        'equipo',
        'entidad_certificadora',
        'resultado',
        'conclusiones',
        'fecha_cierre',
        'cerrada_por_id',
    ];

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por_id');
    }

    /**
     * La checklist.
     *
     * **Sin joins y sin orden**, a propósito. El orden del marco —`requisitos.orden`,
     * porque por código en texto `op.acc.10` iría antes que `op.acc.2`— lo pone
     * `ChecklistRecurso`, que es quien la pinta.
     *
     * Aquí tuvo joins durante un rato y fue un error concreto: el *route model
     * binding* acotado resuelve `{punto}` a través de esta relación con un
     * `where` **sin cualificar**, así que con `implantaciones` y `requisitos`
     * unidas la consulta muere con «column reference "id" is ambiguous» — un
     * error que no menciona ni la ruta ni la relación. Una relación es de quién
     * cuelga de quién; cómo se ordena es de quien consulta.
     *
     * @return HasMany<AuditoriaPunto, $this>
     */
    public function puntos(): HasMany
    {
        return $this->hasMany(AuditoriaPunto::class);
    }

    /**
     * Su informe, si ya se ha preparado.
     *
     * El vínculo vive en `documentos.auditoria_id` y no aquí: la fila de una
     * auditoría cerrada es inmutable, y el informe se prepara justo después de
     * cerrarla.
     *
     * @return HasOne<Documento, $this>
     */
    public function informe(): HasOne
    {
        return $this->hasOne(Documento::class);
    }

    /** @return HasMany<Hallazgo, $this> */
    public function hallazgos(): HasMany
    {
        return $this->hasMany(Hallazgo::class)->orderBy('tipo')->orderBy('id');
    }

    /** @param  Builder<$this>  $query */
    public function scopeAbiertas(Builder $query): void
    {
        $query->where('auditorias.estado', '!=', EstadoAuditoria::Cerrada->value);
    }

    /** @param  Builder<$this>  $query */
    public function scopeCerradas(Builder $query): void
    {
        $query->where('auditorias.estado', EstadoAuditoria::Cerrada->value);
    }

    /**
     * Cerradas con alguna no conformidad entre sus hallazgos.
     *
     * El scope que de verdad importa —cuáles de ésas siguen sin tratar— llega con
     * el § 4.13, que es la otra mitad del módulo: hasta que exista
     * `no_conformidades` no hay nada contra lo que restar.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeConNoConformidades(Builder $query): void
    {
        $query->cerradas()->whereHas('hallazgos', function (Builder $hallazgos): void {
            /** @var Builder<Hallazgo> $hallazgos */
            $hallazgos->noConformidades();
        });
    }

    /** Si la base admite todavía cambios en su checklist y sus hallazgos. */
    public function admiteCambios(): bool
    {
        return $this->estado->admiteCambios();
    }

    /**
     * Cuántos puntos hay revisados y cuántos en total.
     *
     * Es el denominador de la auditoría, y la razón entera por la que existe la
     * checklist: «3 hallazgos» no dice nada y «3 hallazgos sobre 52 medidas
     * revisadas» sí.
     *
     * @return array{revisados: int, total: int}
     */
    public function avance(): array
    {
        $puntos = $this->relationLoaded('puntos') ? $this->puntos : $this->puntos()->get();

        return [
            'revisados' => $puntos
                ->filter(static fn (AuditoriaPunto $punto): bool => $punto->resultado->estaRevisado())
                ->count(),
            'total' => $puntos->count(),
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoAuditoria::class,
            'estado' => EstadoAuditoria::class,
            'fecha' => 'date',
            'fecha_cierre' => 'date',
        ];
    }

    /**
     * El resultado por defecto de un punto recién precargado.
     *
     * Vive aquí y no en `PrecargarChecklist` porque lo leen los dos: quien
     * precarga y quien cuenta cuánto queda por revisar.
     */
    public static function resultadoInicial(): ResultadoPunto
    {
        return ResultadoPunto::Pendiente;
    }

    protected static function newFactory(): AuditoriaFactory
    {
        return AuditoriaFactory::new();
    }
}
