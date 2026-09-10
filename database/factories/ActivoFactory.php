<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Datos sintéticos. Ni un activo ni una ubicación real de ningún cliente.
 *
 * Por defecto el activo nace **sin valorar** —las cinco dimensiones en `na`— y
 * no con niveles al azar: un test de propagación que arranque de valores
 * aleatorios no distingue lo que hereda de lo que ya traía.
 *
 * Por el mismo motivo el cifrado y la copia nacen en `no_aplica` y no en `no`:
 * un activo de fábrica no debe contar en los indicadores de control. Los tests
 * que miden esos indicadores declaran el valor que están probando.
 *
 * @extends Factory<Activo>
 */
class ActivoFactory extends Factory
{
    protected $model = Activo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'ACT-'.fake()->unique()->numberBetween(1000, 9999),
            'nombre' => 'Activo '.fake()->unique()->numberBetween(1, 9999),
            'descripcion' => null,
            'tipo' => TipoActivo::Hardware->value,
            'subtipo' => null,
            'marca_modelo' => null,
            'especificaciones' => null,
            'sistema_operativo' => null,
            'fin_soporte_so' => null,
            'identificador' => null,
            'propietario_id' => null,
            'custodio_id' => null,
            'departamento' => null,
            'ubicacion' => null,
            'fin_garantia' => null,
            'clasificacion' => Clasificacion::NoAplica->value,
            'cifrado' => EstadoControl::NoAplica->value,
            'copia_seguridad' => EstadoControl::NoAplica->value,
            'ultima_revision' => null,
            'etiquetado_en' => null,
            'observaciones' => null,
            'estado_ciclo_vida' => EstadoCicloVida::EnProduccion->value,
            'valor_c' => NivelDimension::Na->value,
            'valor_i' => NivelDimension::Na->value,
            'valor_d' => NivelDimension::Na->value,
            'valor_a' => NivelDimension::Na->value,
            'valor_t' => NivelDimension::Na->value,
            'fecha_alta' => Carbon::today()->subYear(),
            'fecha_baja' => null,
            'borrado_seguro_en' => null,
            'nota_baja' => null,
        ];
    }

    public function de(Organizacion $organizacion): self
    {
        return $this->state(fn (): array => ['organizacion_id' => $organizacion->id]);
    }

    public function deTipo(TipoActivo $tipo): self
    {
        return $this->state(fn (): array => ['tipo' => $tipo->value]);
    }

    public function enEstado(EstadoCicloVida $estado): self
    {
        return $this->state(fn (): array => ['estado_ciclo_vida' => $estado->value]);
    }

    /** Las cinco dimensiones al mismo nivel, para no repetir cinco claves. */
    public function valorado(NivelDimension $nivel): self
    {
        return $this->state(fn (): array => array_combine(
            array_values(Activo::columnasDeValoracion()),
            array_fill(0, count(Dimension::cases()), $nivel->value),
        ));
    }

    /** Una sola dimensión, que es lo que distingue un test de propagación. */
    public function conNivel(Dimension $dimension, NivelDimension $nivel): self
    {
        return $this->state(fn (): array => [
            Activo::columnasDeValoracion()[$dimension->value] => $nivel->value,
        ]);
    }

    public function conControles(EstadoControl $cifrado, ?EstadoControl $copia = null): self
    {
        return $this->state(fn (): array => [
            'cifrado' => $cifrado->value,
            'copia_seguridad' => ($copia ?? $cifrado)->value,
        ]);
    }

    public function clasificado(Clasificacion $clasificacion): self
    {
        return $this->state(fn (): array => ['clasificacion' => $clasificacion->value]);
    }

    public function revisado(Carbon $cuando): self
    {
        return $this->state(fn (): array => ['ultima_revision' => $cuando->toDateString()]);
    }

    /** Retirado sin constancia del borrado seguro: el caso que es un hallazgo. */
    public function retirado(): self
    {
        return $this->state(fn (): array => [
            'estado_ciclo_vida' => EstadoCicloVida::Retirado->value,
            'fecha_baja' => Carbon::today()->subMonth(),
        ]);
    }

    public function dadoDeBaja(): self
    {
        return $this->state(fn (): array => [
            'estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value,
            'fecha_baja' => Carbon::today()->subMonth(),
            'borrado_seguro_en' => Carbon::today()->subMonth(),
            'nota_baja' => 'Borrado criptográfico verificado y disco desmagnetizado.',
        ]);
    }
}
