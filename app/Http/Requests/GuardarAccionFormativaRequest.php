<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\Enums\ImparticionFormacion;
use App\Domain\Persona\Enums\ModalidadFormacion;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Models\AccionFormativa;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
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
    use NormalizaSeleccionVacia;

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

            'modalidad' => ['nullable', Rule::enum(ModalidadFormacion::class)],
            'imparte' => ['nullable', Rule::enum(ImparticionFormacion::class)],
            /*
             * Quién, según quién la impartió. La base admite el ponente nulo
             * —borrar a la persona o al proveedor lo deja así—, pero al guardar
             * una interna tiene que nombrar a alguien y una externa, al menos,
             * a quién o a qué empresa.
             */
            'ponente_persona_id' => [
                'nullable', 'integer', 'exclude_unless:imparte,interna', 'required',
                Rule::exists('personas', 'id'),
            ],
            'ponente_nombre' => ['nullable', 'string', 'max:255', 'exclude_unless:imparte,externa', 'required_without:proveedor_id'],
            'proveedor_id' => ['nullable', 'integer', 'exclude_unless:imparte,externa', Rule::exists('proveedores', 'id')],
        ];
    }

    /**
     * Lo validado, con lo que no corresponde a la impartición puesto a nulo.
     *
     * Los campos del ponente se pintan según `imparte`, así que al cambiar de
     * externa a interna el nombre del formador **no viaja** y `validated()` no
     * lo trae: se quedaría el de antes en la fila y el `CHECK` rechazaría la
     * mezcla. Aquí se vacía lo que no toca.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->validated();
        $imparte = ImparticionFormacion::tryFrom((string) ($datos['imparte'] ?? ''));

        return [
            ...$datos,
            'imparte' => $imparte?->value,
            'ponente_persona_id' => $imparte === ImparticionFormacion::Interna ? ($datos['ponente_persona_id'] ?? null) : null,
            'ponente_nombre' => $imparte === ImparticionFormacion::Externa ? ($datos['ponente_nombre'] ?? null) : null,
            'proveedor_id' => $imparte === ImparticionFormacion::Externa ? ($datos['proveedor_id'] ?? null) : null,
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
            'imparte' => 'impartida por',
            'ponente_persona_id' => 'persona que la impartió',
            'ponente_nombre' => 'quién la impartió',
            'proveedor_id' => 'proveedor',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ponente_persona_id.required' => 'Elige a la persona de la plantilla que impartió la sesión.',
            'ponente_nombre.required_without' => 'Escribe quién la impartió o elige el proveedor.',
        ];
    }

    /**
     * La evidencia es opcional y su desplegable manda el centinela de
     * «ninguno», así que hay que traducirlo antes de validar.
     *
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['evidencia_id', 'modalidad', 'imparte', 'ponente_persona_id', 'proveedor_id'];
    }
}
