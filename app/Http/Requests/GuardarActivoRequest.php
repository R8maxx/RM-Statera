<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La única fuente de verdad de la validación de un activo.
 *
 * La regla que manda: **un activo retirado o dado de baja necesita fecha de
 * baja, y si dice estar dado de baja necesita además constancia del borrado
 * seguro**. `mp.si.5` del ENS no pide que se retire el soporte, pide que se
 * pueda demostrar qué se hizo con lo que había dentro; un estado «dado de baja»
 * sin esa constancia es una casilla marcada que no prueba nada.
 */
class GuardarActivoRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ?Activo $activo */
        $activo = $this->route('activo');

        $reglas = [
            'codigo' => [
                'required',
                'string',
                'max:32',
                // Único dentro de la organización y no globalmente: dos clientes
                // distintos pueden llamar SRV-01 a servidores distintos.
                Rule::unique('activos', 'codigo')
                    ->where('organizacion_id', $this->user()?->organizacion_id)
                    ->ignore($activo?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'tipo' => ['required', Rule::enum(TipoActivo::class)],
            'subtipo' => ['nullable', 'string', 'max:255'],
            'marca_modelo' => ['nullable', 'string', 'max:255'],
            'especificaciones' => ['nullable', 'string', 'max:1000'],
            'sistema_operativo' => ['nullable', 'string', 'max:255'],
            'fin_soporte_so' => ['nullable', 'date'],
            'identificador' => ['nullable', 'string', 'max:255'],
            'estado_ciclo_vida' => ['required', Rule::enum(EstadoCicloVida::class)],
            'clasificacion' => ['required', Rule::enum(Clasificacion::class)],
            'cifrado' => ['required', Rule::enum(EstadoControl::class)],
            'copia_seguridad' => ['required', Rule::enum(EstadoControl::class)],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'propietario_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            'custodio_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            // RLS: un proveedor de otra organización no existe para esta consulta.
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')],
            'fecha_alta' => ['nullable', 'date'],
            'fin_garantia' => ['nullable', 'date'],
            'ultima_revision' => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_baja' => ['nullable', 'date', 'after_or_equal:fecha_alta'],
            'borrado_seguro_en' => ['nullable', 'date', 'before_or_equal:now'],
            'nota_baja' => ['nullable', 'string', 'max:2000'],
            'sistemas' => ['array'],
            'sistemas.*' => [
                'integer',
                Rule::exists('sistemas', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
        ];

        foreach (Activo::columnasDeValoracion() as $columna) {
            $reglas[$columna] = ['required', Rule::enum(NivelDimension::class)];
        }

        return $reglas;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $estado = EstadoCicloVida::tryFrom((string) $this->input('estado_ciclo_vida'));

            if ($estado === null || $estado->estaVigente()) {
                return;
            }

            if (blank($this->input('fecha_baja'))) {
                $validator->errors()->add(
                    'fecha_baja',
                    'Un activo '.mb_strtolower($estado->etiqueta()).' necesita fecha de baja.',
                );
            }

            if ($estado === EstadoCicloVida::DadoDeBaja && blank($this->input('borrado_seguro_en'))) {
                $validator->errors()->add(
                    'borrado_seguro_en',
                    'Para dar de baja el activo hay que dejar constancia de cuándo se borró o destruyó lo que contenía. Si todavía no se ha hecho, el activo está retirado, no dado de baja.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $atributos = [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'estado_ciclo_vida' => 'estado',
            'clasificacion' => 'clasificación',
            'copia_seguridad' => 'copia de seguridad',
            'propietario_id' => 'propietario',
            'custodio_id' => 'custodio',
            'proveedor_id' => 'proveedor',
            'ubicacion' => 'ubicación',
            'sistema_operativo' => 'sistema operativo',
            'fin_soporte_so' => 'fin de soporte del sistema operativo',
            'fin_garantia' => 'fin de garantía',
            'identificador' => 'nº de serie o identificador',
            'marca_modelo' => 'marca y modelo',
            'ultima_revision' => 'última revisión',
            'fecha_alta' => 'fecha de alta',
            'fecha_baja' => 'fecha de baja',
            'borrado_seguro_en' => 'fecha del borrado seguro',
            'nota_baja' => 'nota de baja',
            'sistemas' => 'alcance',
        ];

        foreach (Activo::columnasDeValoracion() as $codigo => $columna) {
            $atributos[$columna] = mb_strtolower(Dimension::from($codigo)->nombre());
        }

        return $atributos;
    }

    /**
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        // `sistema_operativo` también: su desplegable ofrece «ninguno» con el
        // centinela, y sin normalizarlo se guardaba `__ninguno__` en la columna
        // de cualquier activo editado sin sistema operativo. Lo destapó el
        // recorrido del § 4.9, al editar un servicio para asignarle proveedor.
        return ['propietario_id', 'custodio_id', 'proveedor_id', 'sistema_operativo'];
    }

    /**
     * El alcance llega de un grupo de casillas, así que puede traer el
     * centinela de «ninguno». Sin esto, quitar el activo de todos los sistemas
     * fallaba con un error en `sistemas.0` que la página no liga.
     *
     * @return list<string>
     */
    protected function gruposDeCasillas(): array
    {
        return ['sistemas'];
    }
}
