<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La acción masiva de la checklist.
 *
 * No lleva `Rule::exists` sobre los puntos, a diferencia de `MarcarRevisadosRequest`
 * en activos: aquí la pertenencia no se comprueba fila a fila sino de golpe, con
 * el `where` de la auditoría que aplica `RevisarPunto::marcarConformes()`. Lo que
 * sea de otra auditoría simplemente no entra en el `update`, sin error y sin
 * ruido — que es lo correcto cuando lo que llega del cliente es una selección y
 * no una afirmación.
 */
class MarcarConformesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'puntos' => ['required', 'array', 'min:1'],
            'puntos.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['puntos.required' => 'No hay ninguna medida seleccionada.'];
    }

    /**
     * @return list<int>
     */
    public function puntos(): array
    {
        return array_values(array_map(intval(...), $this->array('puntos')));
    }
}
