<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\Reglas\SinHtml;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los textos que alguien redacta para un documento.
 *
 * **Las reglas se construyen iterando el enum**, no se escriben a mano. Así
 * añadir un hueco no deja un campo sin validar, que es la forma habitual de que
 * se cuele algo; y, sobre todo, **lo que no está en el enum no llega a
 * `validated()`**, de modo que no hay manera de escribir en una sección que no
 * existe ni de tocar las limitaciones del sistema desde aquí.
 */
class GuardarNarrativaDocumentoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [];

        foreach (SeccionNarrativa::paraTipo($this->documento()->tipo) as $seccion) {
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
     * Los textos, ya acotados a las secciones que este tipo de documento admite.
     *
     * @return array<string, string|null>
     */
    public function textos(): array
    {
        /** @var array<string, string|null> $validados */
        $validados = $this->validated();

        return $validados;
    }

    private function documento(): Documento
    {
        $documento = $this->route('documento');

        abort_unless($documento instanceof Documento, 404);

        return $documento;
    }
}
