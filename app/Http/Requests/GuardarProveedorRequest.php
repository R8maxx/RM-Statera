<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Proveedor\Enums\ModeloNube;
use App\Domain\Proveedor\Enums\UbicacionDatos;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Usuario\CuentasAsignables;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un proveedor (§ 4.9).
 *
 * **El estado no entra por aquí**: lo decide una evaluación, y retirar tiene su
 * ruta. Y la criticidad declarada se comprueba contra la derivada en
 * `CriticidadProveedor`, no aquí: la derivada sale de otra tabla y la regla vale
 * igual para el seeder.
 *
 * Los activos que presta tampoco: se asignan desde la ficha del activo, que es
 * donde se sabe quién presta cada cosa.
 */
class GuardarProveedorRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $proveedor = $this->route('proveedor');

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('proveedores', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($proveedor instanceof Proveedor ? $proveedor->id : null),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'cif' => ['nullable', 'string', 'max:32'],
            'servicio_prestado' => ['required', 'string', 'max:5000'],

            'criticidad_declarada' => ['nullable', Rule::enum(Criticidad::class)],
            'justificacion_criticidad' => ['nullable', 'string', 'max:2000'],

            'es_nube' => ['boolean'],
            'modelo_nube' => [Rule::requiredIf(fn (): bool => $this->boolean('es_nube')), 'nullable', Rule::enum(ModeloNube::class)],

            'ubicacion_datos' => ['required', Rule::enum(UbicacionDatos::class)],
            'ubicacion_detalle' => ['nullable', 'string', 'max:255'],
            'es_subencargado_rgpd' => ['boolean'],

            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
                // Ni un auditor ni una cuenta que ya no entra: ver `CuentasAsignables`.
                function (string $atributo, mixed $valor, Closure $falla): void {
                    $actual = $this->route('proveedor')?->responsable_id;

                    if (! app(CuentasAsignables::class)->admite((int) $valor, Permiso::ProveedoresGestionar, $actual)) {
                        $falla('Esa cuenta no puede llevarlo: no escribe en este módulo o ya no tiene acceso.');
                    }
                },
            ],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modelo_nube.required' => 'Si es un servicio en la nube, di qué modelo: op.nub.1 y A.5.23 no piden lo mismo para una infraestructura que para una aplicación.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'servicio_prestado' => 'servicio que presta',
            'criticidad_declarada' => 'criticidad',
            'justificacion_criticidad' => 'justificación',
            'modelo_nube' => 'modelo de nube',
            'ubicacion_datos' => 'ubicación de los datos',
            'responsable_id' => 'responsable',
        ];
    }

    /**
     * Lo que se guarda, con los dos interruptores a booleano y el modelo de nube
     * a nulo cuando no es nube: el `CHECK` rechaza un modelo sin nube.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->validated();
        $datos['es_nube'] = $this->boolean('es_nube');
        $datos['es_subencargado_rgpd'] = $this->boolean('es_subencargado_rgpd');

        if (! $datos['es_nube']) {
            $datos['modelo_nube'] = null;
        }

        return $datos;
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['criticidad_declarada', 'modelo_nube', 'responsable_id'];
    }
}
