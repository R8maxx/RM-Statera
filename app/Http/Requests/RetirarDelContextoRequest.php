<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Retirar una cuestión o una parte interesada del contexto.
 *
 * **Uno solo para las dos**, porque la petición es idéntica: un motivo escrito. Dos
 * clases con el mismo cuerpo es cómo se acaba con una que exige motivo y otra que
 * lo deja en blanco.
 *
 * **El motivo es obligatorio también aquí**, aunque la regla viva además en
 * `RetirarDelAnalisis` y aunque un `CHECK` de la base la repita. No es
 * duplicación: el dominio la necesita porque vale igual para un importador o para
 * el seeder, la base porque es la que no se puede saltar, y ésta porque es la
 * única de las tres que sale por pantalla al lado del campo. Un `QueryException`
 * sube como un 500 con un mensaje sin tildes que no lee nadie.
 *
 * Es el mismo reparto que tienen `descartada` en tareas, `anulada` en no
 * conformidades y `rechazado` en documentos.
 */
class RetirarDelContextoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe por qué se retira: es lo que explicará, en la próxima revisión por la dirección, por qué esto ya no está.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motivo' => 'motivo'];
    }
}
