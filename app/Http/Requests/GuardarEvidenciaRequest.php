<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La única fuente de verdad de la validación de una evidencia.
 *
 * La regla que manda: **o fichero o enlace, exactamente uno**. Una evidencia sin
 * ninguno de los dos no prueba nada, y con los dos no se sabe cuál es la prueba.
 * La base lo respalda con un `CHECK`; esto es para que el mensaje llegue al
 * campo en vez de como un error de PostgreSQL.
 *
 * Al editar no se pide fichero: el de una evidencia no se reemplaza, se da de
 * alta otra. El bucket lleva Object Lock justamente para eso.
 */
class GuardarEvidenciaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoEvidencia::class)],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'fecha_obtencion' => ['required', 'date', 'before_or_equal:today'],
            'fecha_caducidad' => ['nullable', 'date', 'after_or_equal:fecha_obtencion'],
            'periodicidad_renovacion' => ['nullable', Rule::enum(PeriodicidadRenovacion::class)],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
        ];

        if ($this->esEdicion()) {
            // Una evidencia de fichero no puede pasar a ser de enlace ni al
            // revés: la restricción de la base exige exactamente uno.
            $reglas['url_externa'] = ['nullable', 'string', 'url', 'max:2048'];

            return $reglas;
        }

        $reglas['fichero'] = [
            Rule::requiredIf(fn (): bool => blank($this->input('url_externa'))),
            'nullable',
            'file',
            'max:51200',
        ];

        $reglas['url_externa'] = [
            Rule::requiredIf(fn (): bool => ! $this->hasFile('fichero')),
            'nullable',
            'string',
            'url',
            'max:2048',
        ];

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichero.required' => 'Sube el fichero o pega un enlace: una evidencia sin ninguno de los dos no prueba nada.',
            'url_externa.required' => 'Pega el enlace o sube el fichero: una evidencia sin ninguno de los dos no prueba nada.',
            'fichero.max' => 'El fichero no puede pasar de 50 MB.',
            'fecha_obtencion.before_or_equal' => 'Una evidencia no se puede obtener en el futuro.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_obtencion' => 'fecha de obtención',
            'fecha_caducidad' => 'fecha de caducidad',
            'periodicidad_renovacion' => 'periodicidad de renovación',
            'responsable_id' => 'responsable',
            'url_externa' => 'enlace',
            'descripcion' => 'descripción',
        ];
    }

    /**
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id', 'periodicidad_renovacion'];
    }

    private function esEdicion(): bool
    {
        return $this->route('evidencia') instanceof Evidencia;
    }
}
