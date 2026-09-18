<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Contexto\Enums\MateriaCuestion;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una cuestión del DAFO.
 *
 * **El análisis de alta y el de baja no están entre las reglas.** El primero lo
 * pone `RegistrarCuestion` contra el borrador abierto y el segundo lo escribe
 * `RetirarDelAnalisis` con su motivo. Admitirlos aquí dejaría mover una cuestión
 * de un análisis a otro desde el formulario de edición, que es decir que una
 * debilidad recién escrita ya estaba sobre la mesa el año pasado — exactamente lo
 * que el histórico existe para impedir. Mismo reparto que el estado en tareas y en
 * no conformidades.
 *
 * **El tipo sí se puede cambiar**, a diferencia de casi todo lo que fija un
 * registro. Reclasificar una debilidad como amenaza —«no era nuestro, era del
 * mercado»— es una corrección legítima y frecuente, y es exactamente por esto por
 * lo que el ámbito y el signo se derivan del tipo en vez de guardarse: cambiarlo es
 * cambiar un campo y no acordarse de tres.
 */
class GuardarCuestionRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cuestion = $this->route('cuestion');
        $id = $cuestion instanceof CuestionContexto ? $cuestion->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:40',
                // Único dentro de la organización, no del mundo.
                Rule::unique('cuestiones_contexto', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'tipo' => ['required', Rule::enum(TipoCuestion::class)],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'materia' => ['required', Rule::enum(MateriaCuestion::class)],
            'es_climatica' => ['boolean'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * El desplegable de responsable es opcional y manda el centinela de Reka.
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
            'tipo' => 'tipo',
            'titulo' => 'título',
            'descripcion' => 'descripción',
            'materia' => 'materia',
            'es_climatica' => 'relación con el cambio climático',
            'responsable_id' => 'responsable',
        ];
    }
}
