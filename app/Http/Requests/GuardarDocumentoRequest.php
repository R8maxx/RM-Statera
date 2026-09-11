<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de la serie documental.
 *
 * La única fuente de verdad de la validación, como en el resto del proyecto: el
 * controlador ni comprueba ni reinterpreta lo que llega aquí.
 */
class GuardarDocumentoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $documento = $this->route('documento');
        $id = $documento instanceof Documento ? $documento->id : null;

        return [
            'sistema_id' => ['required', 'integer', 'exists:sistemas,id'],
            'tipo' => ['required', Rule::enum(TipoDocumento::class)],
            'codigo' => [
                'required', 'string', 'max:60',
                // Único dentro de la organización, no del mundo: dos clientes
                // pueden llamar igual a su SoA y es lo normal.
                Rule::unique('documentos', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'clasificacion' => ['required', Rule::enum(ClasificacionDocumental::class)],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * El marco del sistema tiene que casar con el tipo de documento.
     *
     * Una SoA de un sistema declarado bajo el ENS no es un documento raro: es un
     * documento imposible, porque sus controles no existen en ese marco. Saldría
     * vacío y nadie sabría por qué, así que se dice aquí y con nombres propios.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tipo = TipoDocumento::tryFrom((string) $this->input('tipo'));
                $sistema = Sistema::query()->with('marco')->find($this->input('sistema_id'));

                if ($tipo === null || $sistema === null) {
                    return;
                }

                // `marco_id` es NOT NULL, así que la relación siempre está.
                if ($sistema->marco->codigo === $tipo->marcoEsperado()) {
                    return;
                }

                $validator->errors()->add('sistema_id', sprintf(
                    'La %s es de %s; el sistema «%s» está declarado bajo %s.',
                    $tipo->etiquetaCorta(),
                    $tipo->marcoEsperado() === 'ISO27001-2022' ? 'ISO 27001' : 'el ENS',
                    $sistema->nombre,
                    $sistema->marco->nombre,
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
            'responsable_id' => 'responsable',
        ];
    }
}
