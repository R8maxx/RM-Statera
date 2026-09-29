<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * El diploma de una persona en una sesión.
 *
 * **Sólo de quien consta que asistió.** Un diploma colgado de alguien que faltó
 * —o que ni estaba convocado— diría que se formó quien la convocatoria dice que
 * no; y la ruta no lo puede impedir con `scopeBindings()`, porque la persona no
 * es hija de la sesión sino de la organización.
 *
 * El fichero, como cualquier adjunto: sin lista de `mimes` y con 50 MB de tope.
 */
class SubirDiplomaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fichero' => ['required', 'file', 'max:51200'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $accion = $this->route('accion');
                $persona = $this->route('persona');

                if (! $accion instanceof AccionFormativa || ! $persona instanceof Persona) {
                    return;
                }

                $asistio = $accion->asistencias()
                    ->where('persona_id', $persona->id)
                    ->where('asistio', true)
                    ->exists();

                if (! $asistio) {
                    $validator->errors()->add('fichero', "{$persona->nombre} no consta como asistente de esta sesión: marca y guarda su asistencia antes de subir el diploma.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichero.max' => 'El diploma no puede pasar de 50 MB.',
            'fichero.required' => 'Elige el diploma que quieres subir.',
        ];
    }
}
