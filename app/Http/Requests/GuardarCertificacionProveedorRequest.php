<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Proveedor\Enums\TipoCertificacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registrar lo que acredita un proveedor. La categoría sólo para el ENS y la
 * descripción sólo para «otra», como piden los `CHECK`.
 */
class GuardarCertificacionProveedorRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipo = $this->input('tipo');

        return [
            'tipo' => ['required', Rule::enum(TipoCertificacion::class)],
            'categoria_ens' => [
                $tipo === TipoCertificacion::Ens->value ? 'required' : 'prohibited',
                'nullable',
                Rule::enum(CategoriaEns::class),
            ],
            'descripcion' => [
                $tipo === TipoCertificacion::Otra->value ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'entidad_emisora' => ['nullable', 'string', 'max:255'],
            'emitida_en' => ['nullable', 'date'],
            'caduca_en' => ['nullable', 'date', 'after_or_equal:emitida_en'],
            // RLS: una evidencia de otra organización no existe para esta consulta.
            'evidencia_id' => ['nullable', 'integer', Rule::exists('evidencias', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categoria_ens' => 'categoría',
            'entidad_emisora' => 'entidad emisora',
            'emitida_en' => 'fecha de emisión',
            'caduca_en' => 'fecha de caducidad',
            'evidencia_id' => 'evidencia',
        ];
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['categoria_ens', 'evidencia_id'];
    }
}
