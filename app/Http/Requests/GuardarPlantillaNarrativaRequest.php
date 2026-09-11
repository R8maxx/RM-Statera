<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Narrativa\Reglas\SinHtml;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los textos base de la organización, para un tipo de documento.
 *
 * Mismas reglas que las del documento y por el mismo motivo: salen del enum, así
 * que no se puede nombrar un hueco que no exista.
 */
class GuardarPlantillaNarrativaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [];

        foreach (SeccionNarrativa::paraTipo($this->tipo()) as $seccion) {
            $reglas[$seccion->value] = [
                'nullable',
                'string',
                'max:'.$seccion->maxCaracteres(),
                new SinHtml,
            ];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $nombres = [];

        foreach (SeccionNarrativa::cases() as $seccion) {
            $nombres[$seccion->value] = mb_strtolower($seccion->etiqueta());
        }

        return $nombres;
    }

    /**
     * @return array<string, string|null>
     */
    public function textos(): array
    {
        /** @var array<string, string|null> $validados */
        $validados = $this->validated();

        return $validados;
    }

    public function tipo(): TipoDocumento
    {
        $tipo = $this->route('tipo');

        if ($tipo instanceof TipoDocumento) {
            return $tipo;
        }

        return TipoDocumento::tryFrom((string) $tipo) ?? abort(404);
    }
}
