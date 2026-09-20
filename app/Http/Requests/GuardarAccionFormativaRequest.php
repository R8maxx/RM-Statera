<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Models\AccionFormativa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una sesión de formación o concienciación.
 *
 * La asistencia no entra aquí: se guarda entera por su propia ruta, como la
 * checklist de una persona y las subtareas de una tarea.
 */
class GuardarAccionFormativaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $accion = $this->route('accion');
        $id = $accion instanceof AccionFormativa ? $accion->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('acciones_formativas', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoAccionFormativa::class)],
            'fecha' => ['required', 'date'],
            'duracion_horas' => ['nullable', 'numeric', 'gt:0', 'max:999.99'],
            'contenido' => ['nullable', 'string', 'max:5000'],
            'evidencia_id' => ['nullable', 'integer', Rule::exists('evidencias', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'duracion_horas' => 'duración',
            'evidencia_id' => 'hoja de firmas',
        ];
    }
}
