<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion\Models;

use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Enums\TipoRetroalimentacion;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Comunicacion\ComunicacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Algo que se comunicó, o que se recibió.
 *
 * **Lo emitido** cumple una línea del plan cuando la tiene, y entonces congela
 * hasta cuándo la cubre. **Lo recibido** es la retroalimentación de las partes
 * interesadas —quejas, encuestas, sugerencias— que la 9.3.2 e) pide revisar, con
 * lo que se respondió.
 *
 * Es un hecho, no una previsión: no se registra con fecha futura.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property SentidoComunicacion $sentido
 * @property ?int $comunicacion_prevista_id
 * @property Carbon $fecha
 * @property ?Carbon $cubre_hasta
 * @property string $asunto
 * @property ?string $resumen
 * @property CanalComunicacion $canal
 * @property ?int $parte_interesada_id
 * @property ?TipoRetroalimentacion $tipo_recibida
 * @property ?string $respuesta
 * @property ?int $evidencia_id
 * @property ?int $registrada_por_id
 */
class Comunicacion extends Model
{
    /** @use HasFactory<ComunicacionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'comunicaciones';

    protected $fillable = [
        'organizacion_id',
        'sentido',
        'comunicacion_prevista_id',
        'fecha',
        'cubre_hasta',
        'asunto',
        'resumen',
        'canal',
        'parte_interesada_id',
        'tipo_recibida',
        'respuesta',
        'evidencia_id',
        'registrada_por_id',
    ];

    /** @return BelongsTo<ComunicacionPrevista, $this> */
    public function prevista(): BelongsTo
    {
        return $this->belongsTo(ComunicacionPrevista::class, 'comunicacion_prevista_id');
    }

    /** @return BelongsTo<ParteInteresada, $this> */
    public function parteInteresada(): BelongsTo
    {
        return $this->belongsTo(ParteInteresada::class);
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_id');
    }

    /** @param Builder<$this> $query */
    public function scopeEmitidas(Builder $query): void
    {
        $query->where('comunicaciones.sentido', SentidoComunicacion::Emitida->value);
    }

    /** @param Builder<$this> $query */
    public function scopeRecibidas(Builder $query): void
    {
        $query->where('comunicaciones.sentido', SentidoComunicacion::Recibida->value);
    }

    /**
     * Lo recibido que nadie ha contestado todavía.
     *
     * No es rojo: no hay plazo para contestar una sugerencia. Es lo que conviene
     * cerrar antes de la revisión por la dirección.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinRespuesta(Builder $query): void
    {
        $query->recibidas()->where(static function (Builder $sin): void {
            $sin->whereNull('comunicaciones.respuesta')
                ->orWhereRaw("trim(comunicaciones.respuesta) = ''");
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sentido' => SentidoComunicacion::class,
            'canal' => CanalComunicacion::class,
            'tipo_recibida' => TipoRetroalimentacion::class,
            'fecha' => 'date',
            'cubre_hasta' => 'date',
        ];
    }

    protected static function newFactory(): ComunicacionFactory
    {
        return ComunicacionFactory::new();
    }
}
