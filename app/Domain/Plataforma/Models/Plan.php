<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lo que se le vende a una organización (punto 43).
 *
 * Es dato de la plataforma, sin `organizacion_id`, igual que el catálogo: el
 * mismo plan lo tienen varios clientes. Lo que el producto necesita saber de
 * un plan es qué límites pone, cuánto aguanta un impago antes de pasar a sólo
 * lectura y, desde el punto 51, cuánto cuesta y si la organización puede
 * contratarlo por su cuenta. **El precio se modela y no se cobra**: no hay
 * pasarela todavía.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $descripcion
 * @property ?int $limite_cuentas nulo, sin límite
 * @property ?int $limite_sistemas nulo, sin límite
 * @property int $dias_gracia
 * @property bool $activo
 * @property ?int $precio_mensual_centimos sin IVA; nulo, sin precio
 * @property int $descuento_anual por ciento, al pagar el año de una vez
 * @property bool $contratable si la organización puede elegirlo ella misma
 * @property ?Carbon $created_at
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $table = 'planes';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'limite_cuentas',
        'limite_sistemas',
        'dias_gracia',
        'activo',
        'precio_mensual_centimos',
        'descuento_anual',
        'contratable',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'limite_cuentas' => 'integer',
            'limite_sistemas' => 'integer',
            'dias_gracia' => 'integer',
            'activo' => 'boolean',
            'precio_mensual_centimos' => 'integer',
            'descuento_anual' => 'integer',
            'contratable' => 'boolean',
        ];
    }

    /**
     * Lo que se paga por un periodo entero, en céntimos y sin IVA. El año lleva
     * el descuento anual; el mes, no. Nulo si el plan no tiene precio.
     */
    public function precioDelPeriodo(PeriodoFacturacion $periodo): ?int
    {
        if ($this->precio_mensual_centimos === null) {
            return null;
        }

        return match ($periodo) {
            PeriodoFacturacion::Mensual => $this->precio_mensual_centimos,
            PeriodoFacturacion::Anual => intdiv($this->precio_mensual_centimos * 12 * (100 - $this->descuento_anual) + 50, 100),
        };
    }

    /**
     * Lo que sale al mes en ese periodo, que es la cifra grande de la tarjeta:
     * comparar un precio mensual con uno anual no se lee.
     */
    public function precioMensualEn(PeriodoFacturacion $periodo): ?int
    {
        $total = $this->precioDelPeriodo($periodo);

        return $total === null ? null : intdiv($total + intdiv($periodo->meses(), 2), $periodo->meses());
    }

    /**
     * Los planes que una organización puede elegir: activos y contratables.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeContratables(Builder $consulta): void
    {
        $consulta->where('activo', true)->where('contratable', true);
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
