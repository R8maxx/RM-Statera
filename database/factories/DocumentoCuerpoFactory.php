<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\DocumentoCuerpo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Datos sintéticos.
 *
 * @extends Factory<DocumentoCuerpo>
 */
class DocumentoCuerpoFactory extends Factory
{
    protected $model = DocumentoCuerpo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cuerpo = CuerpoDeFabrica::para(TipoDocumento::SoaIso);

        return [
            'cuerpo' => $cuerpo,
            'generado' => $cuerpo,
            'generado_en' => Carbon::now(),
            'editado_en' => null,
        ];
    }

    public function deTipo(TipoDocumento $tipo): self
    {
        return $this->state(function () use ($tipo): array {
            $cuerpo = CuerpoDeFabrica::para($tipo);

            return ['cuerpo' => $cuerpo, 'generado' => $cuerpo];
        });
    }

    public function delDocumento(int $documentoId): self
    {
        return $this->state(fn (): array => ['documento_id' => $documentoId]);
    }

    /**
     * Un cuerpo que alguien ha tocado.
     *
     * `generado` se queda como estaba a propósito: eso es justo lo que hace de
     * línea base, y sin la divergencia no hay nada que declarar.
     *
     * @param  array<string, mixed>|null  $cuerpo
     */
    public function editado(?array $cuerpo = null): self
    {
        return $this->state(fn (array $atributos): array => [
            'cuerpo' => $cuerpo ?? $atributos['cuerpo'],
            'editado_en' => Carbon::now(),
        ]);
    }
}
