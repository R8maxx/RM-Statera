<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una auditoría.
 *
 * La única fuente de verdad de la validación, como en el resto del proyecto: el
 * controlador ni comprueba ni reinterpreta lo que llega aquí.
 */
class GuardarAuditoriaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $auditoria = $this->route('auditoria');
        $id = $auditoria instanceof Auditoria ? $auditoria->id : null;

        return [
            /*
             * El sistema es obligatorio y no admite nulo. El SGSI **es** un
             * sistema, así que una auditoría sin él no puede tener checklist, que
             * es la mitad del módulo. El `CHECK` de la tabla dice lo mismo.
             */
            'sistema_id' => ['required', 'integer', 'exists:sistemas,id'],

            'codigo' => [
                'required', 'string', 'max:60',
                // Único dentro de la organización, no del mundo: dos clientes
                // pueden llamar igual a su auditoría de 2026 y es lo normal.
                Rule::unique('auditorias', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'tipo' => ['required', Rule::enum(TipoAuditoria::class)],
            'fecha' => ['required', 'date'],
            'alcance' => ['nullable', 'string', 'max:5000'],
            'auditor' => ['nullable', 'string', 'max:255'],
            'entidad_certificadora' => ['nullable', 'string', 'max:255'],
            'conclusiones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * La entidad certificadora es de las externas, y lo dice también el `CHECK`.
     *
     * Sin esta comprobación, el error que sube es el de la restricción de la
     * base, que habla de `auditorias_entidad_check` y no de lo que la persona
     * estaba rellenando.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tipo = TipoAuditoria::tryFrom((string) $this->input('tipo'));
                $entidad = $this->input('entidad_certificadora');

                if ($tipo === null || $entidad === null || $entidad === '') {
                    return;
                }

                if ($tipo->admiteEntidadCertificadora()) {
                    return;
                }

                $validator->errors()->add('entidad_certificadora', sprintf(
                    'Sólo una auditoría externa la firma una entidad acreditada. «%s» no lo es.',
                    $tipo->etiqueta(),
                ));
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sistema_id' => 'sistema',
            'entidad_certificadora' => 'entidad certificadora',
        ];
    }
}
