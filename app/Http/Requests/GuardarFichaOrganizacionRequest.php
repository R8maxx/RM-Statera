<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\GuardarFichaOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La ficha de la organización.
 *
 * **Aquí sólo se comprueba la forma**, como en `GuardarMetodologiaRequest`: lo
 * que significa cada campo y cómo se normaliza vive en
 * `GuardarFichaOrganizacion`, porque vale igual para un importador.
 *
 * La única regla con algo de fondo es la unicidad del CIF, y tiene que ir aquí
 * porque necesita `ignore()`: sin él, guardar la ficha sin tocar el CIF chocaría
 * consigo misma. Se compara con el CIF **ya normalizado**, o «B-1234» pasaría el
 * `unique` y luego chocaría contra el índice de la base con un error de
 * restricción que no menciona la palabra «CIF».
 *
 * No hay `authorize()`: autoriza la ruta con `can:organizacion.gestionar`, como
 * en todo el producto.
 */
class GuardarFichaOrganizacionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = app(ContextoOrganizacion::class)->idObligatorio();

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'cif' => ['nullable', 'string', 'max:20', Rule::unique('organizaciones', 'cif')->ignore($id)],
            'sector' => ['nullable', 'string', 'max:255'],

            'domicilio' => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],

            // `url` y no un texto cualquiera: de aquí sale lo que un móvil abre
            // al escanear una pegatina, y una base sin esquema no se resuelve.
            'url_base_etiquetas' => ['nullable', 'url', 'max:255'],

            'sujeto_obligado_ens' => ['required', 'boolean'],
            'proveedor_sector_publico' => ['required', 'boolean'],

            // La política de reevaluación de proveedores (§ 4.9), en meses.
            // El mismo intervalo que el `CHECK`: un mes a diez años.
            'reevaluacion_proveedor_alta_meses' => ['required', 'integer', 'between:1,120'],
            'reevaluacion_proveedor_media_meses' => ['required', 'integer', 'between:1,120'],
            'reevaluacion_proveedor_baja_meses' => ['required', 'integer', 'between:1,120'],

            // El plazo de remediación de vulnerabilidades, en días por severidad.
            'plazo_vulnerabilidad_critica_dias' => ['required', 'integer', 'between:1,730'],
            'plazo_vulnerabilidad_alta_dias' => ['required', 'integer', 'between:1,730'],
            'plazo_vulnerabilidad_media_dias' => ['required', 'integer', 'between:1,730'],
            'plazo_vulnerabilidad_baja_dias' => ['required', 'integer', 'between:1,730'],
        ];
    }

    /**
     * El CIF entra normalizado en la validación, no sólo en la escritura.
     *
     * Así `unique` compara lo mismo que acabará en la columna. Si sólo se
     * normalizara en la acción, «A-99999999» pasaría el `unique` frente a un
     * «A99999999» ajeno y reventaría después contra el índice.
     */
    protected function prepareForValidation(): void
    {
        $cif = $this->input('cif');

        if (is_string($cif) && trim($cif) !== '') {
            $this->merge(['cif' => GuardarFichaOrganizacion::normalizarCif($cif)]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'La organización necesita un nombre con el que aparecer en la aplicación.',
            'cif.unique' => 'Ese CIF ya está registrado en otra organización.',
            'url_base_etiquetas.url' => 'Escribe la dirección completa, con https:// delante.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre comercial',
            'razon_social' => 'razón social',
            'url_base_etiquetas' => 'dirección base de las etiquetas',
            'reevaluacion_proveedor_alta_meses' => 'reevaluación de criticidad alta',
            'reevaluacion_proveedor_media_meses' => 'reevaluación de criticidad media',
            'reevaluacion_proveedor_baja_meses' => 'reevaluación de criticidad baja',
            'plazo_vulnerabilidad_critica_dias' => 'plazo de una vulnerabilidad crítica',
            'plazo_vulnerabilidad_alta_dias' => 'plazo de una vulnerabilidad alta',
            'plazo_vulnerabilidad_media_dias' => 'plazo de una vulnerabilidad media',
            'plazo_vulnerabilidad_baja_dias' => 'plazo de una vulnerabilidad baja',
        ];
    }
}
