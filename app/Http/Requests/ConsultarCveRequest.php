<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El CVE que se pregunta fuera. Con la misma forma que admite el alta, para que
 * no salga hacia NVD nada que el formulario luego rechazaría.
 */
class ConsultarCveRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cve' => ['required', 'string', 'max:32', 'regex:/^CVE-\d{4}-\d{4,}$/i'],
            // En la edición, la propia no cuenta como «ya registrada».
            'vulnerabilidad_id' => ['nullable', 'integer', Rule::exists('vulnerabilidades', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cve.required' => 'Escribe el CVE que quieres consultar.',
            'cve.regex' => 'Un CVE se escribe CVE-AAAA-NNNN, por ejemplo CVE-2024-3094.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $cve = $this->input('cve');

        if (is_string($cve)) {
            $this->merge(['cve' => trim($cve)]);
        }
    }
}
