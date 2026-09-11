<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Models\DocumentoSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos sintéticos.
 *
 * @extends Factory<DocumentoSeccion>
 */
class DocumentoSeccionFactory extends Factory
{
    protected $model = DocumentoSeccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccion' => SeccionNarrativa::Introduccion->value,
            'contenido_md' => 'Texto del documento.',
            'origen' => OrigenTexto::Plantilla->value,
        ];
    }

    public function para(SeccionNarrativa $seccion, string $contenido): self
    {
        return $this->state(fn (): array => [
            'seccion' => $seccion->value,
            'contenido_md' => $contenido,
        ]);
    }

    public function retocada(): self
    {
        return $this->state(fn (): array => ['origen' => OrigenTexto::Propio->value]);
    }

    public function delDocumento(int $documentoId): self
    {
        return $this->state(fn (): array => ['documento_id' => $documentoId]);
    }
}
