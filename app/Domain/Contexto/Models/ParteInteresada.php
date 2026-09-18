<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Models;

use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\TipoParteInteresada;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Contexto\ParteInteresadaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Quién tiene algo que decir sobre la seguridad de la organización, y qué espera
 * de ella. Cláusula 4.2 de ISO.
 *
 * Vive entre análisis igual que una cuestión, y por lo mismo: sus requisitos se
 * atan a implantaciones concretas, y una parte que se copiara en cada revisión
 * dejaría esos vínculos apuntando a filas muertas.
 *
 * **El ámbito va en columna y no se deduce del tipo**, a diferencia de lo que pasa
 * con una cuestión: un empleado es interno y un regulador externo, pero un socio o
 * un accionista son lo que cada organización decida. `TipoParteInteresada` lo
 * propone y no lo impone.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $nombre
 * @property TipoParteInteresada $tipo
 * @property Ambito $ambito
 * @property ?string $descripcion
 * @property ?int $responsable_id
 * @property int $analisis_alta_id
 * @property ?int $analisis_baja_id
 * @property ?string $motivo_baja
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ParteInteresada extends Model
{
    /** @use HasFactory<ParteInteresadaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'partes_interesadas';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'nombre',
        'tipo',
        'ambito',
        'descripcion',
        'responsable_id',
        'analisis_alta_id',
        'analisis_baja_id',
        'motivo_baja',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<AnalisisContexto, $this> */
    public function analisisAlta(): BelongsTo
    {
        return $this->belongsTo(AnalisisContexto::class, 'analisis_alta_id');
    }

    /** @return BelongsTo<AnalisisContexto, $this> */
    public function analisisBaja(): BelongsTo
    {
        return $this->belongsTo(AnalisisContexto::class, 'analisis_baja_id');
    }

    /**
     * Lo que esta parte espera o exige.
     *
     * En cascada, a diferencia de casi todo lo demás: un requisito de una parte
     * interesada no significa nada sin la parte, y no hay nadie a quien
     * reasignarlo.
     *
     * @return HasMany<RequisitoInteresado, $this>
     */
    public function requisitos(): HasMany
    {
        return $this->hasMany(RequisitoInteresado::class);
    }

    /** @param  Builder<$this>  $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('partes_interesadas.analisis_baja_id');
    }

    /**
     * Las que tienen algo que obliga —legal o contractual— sin ninguna
     * implantación que lo cubra.
     *
     * Es el indicador que contesta a «¿sabemos qué nos exigen y qué estamos
     * haciendo al respecto?», que es la pregunta entera de la cláusula 4.2.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeConObligacionSinCubrir(Builder $query): void
    {
        $query->vigentes()->whereHas('requisitos', static function (Builder $requisitos): void {
            /** @var Builder<RequisitoInteresado> $requisitos */
            $requisitos->queObligan()->sinCubrir();
        });
    }

    public function estaVigente(): bool
    {
        return $this->analisis_baja_id === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoParteInteresada::class,
            'ambito' => Ambito::class,
        ];
    }

    protected static function newFactory(): ParteInteresadaFactory
    {
        return ParteInteresadaFactory::new();
    }
}
