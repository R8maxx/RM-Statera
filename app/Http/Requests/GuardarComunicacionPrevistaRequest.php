<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Domain\Obligacion\Cadencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una línea del plan de comunicación. Cláusula 7.4.
 *
 * **Las partes interesadas van aparte**, por su pivote, y no en `datos()`: la
 * pertenencia y la vigencia las filtra `SincronizarDestinatarios` contra la
 * consulta con scope, que es quien sabe.
 *
 * **Con cadencia hace falta desde cuándo cuenta**, y sin ella no se guarda: la
 * base lo impone en las dos direcciones y aquí se dice en castellano.
 */
class GuardarComunicacionPrevistaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $prevista = $this->route('prevista');
        $id = $prevista instanceof ComunicacionPrevista ? $prevista->id : null;
        $organizacion = app(ContextoOrganizacion::class)->idObligatorio();

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('comunicaciones_previstas', 'codigo')->where('organizacion_id', $organizacion)->ignore($id),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'canal' => ['required', Rule::enum(CanalComunicacion::class)],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $organizacion),
            ],
            'periodicidad_meses' => ['nullable', 'integer', 'min:'.Cadencia::MINIMO_MESES, 'max:'.Cadencia::MAXIMO_MESES],
            'computa_desde' => ['nullable', 'required_with:periodicidad_meses', 'date'],
            'destinatarios_otros' => ['nullable', 'string', 'max:2000'],
            'partes_interesadas' => ['nullable', 'array'],
            'partes_interesadas.*' => ['integer', Rule::exists('partes_interesadas', 'id')],
        ];
    }

    /**
     * Lo que va a la tabla: sin las partes interesadas, y sin `computa_desde`
     * cuando no hay cadencia —sin ella no significa nada y el `CHECK` lo
     * rechazaría—.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->safe()->except('partes_interesadas');

        if (($datos['periodicidad_meses'] ?? null) === null) {
            $datos['periodicidad_meses'] = null;
            $datos['computa_desde'] = null;
        }

        return $datos;
    }

    /** @return list<int> */
    public function partesInteresadas(): array
    {
        /** @var list<int|string> $partes */
        $partes = $this->validated('partes_interesadas') ?? [];

        return array_map(intval(...), $partes);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Una comunicación sin decir qué se comunica es una fila que nadie sabe qué es.',
            'computa_desde.required_with' => 'Con una cadencia hace falta decir desde cuándo cuenta.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'qué se comunica',
            'responsable_id' => 'responsable',
            'periodicidad_meses' => 'cadencia',
            'computa_desde' => 'desde cuándo cuenta',
            'destinatarios_otros' => 'otros destinatarios',
        ];
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id', 'periodicidad_meses'];
    }

    /** @return list<string> */
    protected function gruposDeCasillas(): array
    {
        return ['partes_interesadas'];
    }
}
