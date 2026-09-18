<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\TipoParteInteresada;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una parte interesada.
 *
 * **El ámbito es obligatorio y no se deduce del tipo.** `TipoParteInteresada` lo
 * propone para que el formulario no lo pregunte dos veces, pero quien escribe
 * manda: un socio o un accionista son internos o externos según cómo esté montada
 * la organización, y adivinarlo acertaría en seis casos de ocho.
 *
 * **Sus requisitos no entran aquí**, sino por `GuardarRequisitosInteresadoRequest`:
 * identificar a quién le importa la seguridad de la organización y escribir qué le
 * exige cada uno son dos trabajos, y el segundo se hace con la ficha delante.
 */
class GuardarParteInteresadaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $parte = $this->route('parte');
        $id = $parte instanceof ParteInteresada ? $parte->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:40',
                Rule::unique('partes_interesadas', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoParteInteresada::class)],
            'ambito' => ['required', Rule::enum(Ambito::class)],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * El desplegable de quién la atiende es opcional y manda el centinela de Reka.
     *
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'tipo' => 'tipo',
            'ambito' => 'ámbito',
            'descripcion' => 'descripción',
            'responsable_id' => 'quién la atiende',
        ];
    }
}
