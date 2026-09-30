<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Usuario\CuentasAsignables;
use App\Domain\Vulnerabilidad\Enums\OrigenVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una vulnerabilidad.
 *
 * **El estado no entra por aquí**: tiene su ruta, que es la que deja el
 * histórico y exige motivo o verificación. **La severidad sólo se pide sin
 * CVSS**: con puntuación la deriva `Severidad::desdeCvss()` y lo que llegue en
 * el campo se ignora, que es lo que el `CHECK` de la tabla exigiría igual.
 *
 * **Lo que trae la consulta del CVE entra por aquí como un campo más**: quien
 * guarda ya puede escribir la puntuación que quiera, y la traza dice quién fue.
 */
class GuardarVulnerabilidadRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vulnerabilidad = $this->route('vulnerabilidad');
        $conCvss = $this->filled('cvss_puntuacion');

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('vulnerabilidades', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($vulnerabilidad instanceof Vulnerabilidad ? $vulnerabilidad->id : null),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:10000'],

            'cve' => ['nullable', 'string', 'max:32', 'regex:/^CVE-\d{4}-\d{4,}$/i'],
            'cvss_puntuacion' => ['nullable', 'numeric', 'between:0,10'],
            'cvss_vector' => ['nullable', 'string', 'max:255'],
            'cwe' => ['nullable', 'string', 'max:20', 'regex:/^CWE-\d+$/i'],
            // Se pintan como enlaces en la ficha: sólo http y https, nunca `javascript:`.
            'referencias' => ['nullable', 'array', 'max:20'],
            'referencias.*' => ['string', 'max:2048', 'url:http,https'],
            // Lo que trajo la consulta del CVE: la marca de CISA KEV y el día en que se miró NVD.
            'kev_desde' => ['nullable', 'date', 'before_or_equal:today'],
            'nvd_consultado_el' => ['nullable', 'date', 'before_or_equal:today'],
            'severidad' => [$conCvss ? 'nullable' : 'required', Rule::enum(Severidad::class)],

            'origen' => ['required', Rule::enum(OrigenVulnerabilidad::class)],
            'fecha_deteccion' => ['required', 'date', 'before_or_equal:today'],

            // RLS: lo de otra organización no existe para estas consultas.
            'activos' => ['nullable', 'array'],
            'activos.*' => ['integer', Rule::exists('activos', 'id')],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')],
            'riesgo_id' => ['nullable', 'integer', Rule::exists('riesgos', 'id')],
            'incidente_id' => ['nullable', 'integer', Rule::exists('incidentes', 'id')],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
                // Ni un auditor ni una cuenta que ya no entra: ver `CuentasAsignables`.
                function (string $atributo, mixed $valor, Closure $falla): void {
                    $actual = $this->route('vulnerabilidad')?->responsable_id;

                    if (! app(CuentasAsignables::class)->admite((int) $valor, Permiso::VulnerabilidadesGestionar, $actual)) {
                        $falla('Esa cuenta no puede llevarlo: no escribe en este módulo o ya no tiene acceso.');
                    }
                },
            ],

            'remediacion' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cve.regex' => 'Un CVE se escribe CVE-AAAA-NNNN, por ejemplo CVE-2024-3094.',
            'cwe.regex' => 'Un CWE se escribe CWE-NNN, por ejemplo CWE-362.',
            'referencias.*.url' => 'Cada referencia tiene que ser una dirección http o https, una por línea.',
            'referencias.max' => 'Como mucho veinte referencias.',
            'severidad.required' => 'Sin puntuación CVSS, la severidad hay que declararla.',
            'fecha_deteccion.before_or_equal' => 'Una vulnerabilidad se registra cuando ya se ha detectado.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cvss_puntuacion' => 'puntuación CVSS',
            'cvss_vector' => 'vector CVSS',
            'fecha_deteccion' => 'fecha de detección',
            'proveedor_id' => 'proveedor',
            'riesgo_id' => 'riesgo',
            'incidente_id' => 'incidente',
            'responsable_id' => 'responsable',
            'remediacion' => 'remediación',
            'kev_desde' => 'fecha de inclusión en KEV',
            'nvd_consultado_el' => 'fecha de consulta a NVD',
        ];
    }

    /**
     * Lo que se guarda: la severidad derivada si hay CVSS, el CVE en mayúsculas
     * y sin los activos, que van por su pivote.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->safe()->except('activos');

        if (isset($datos['cvss_puntuacion']) && $datos['cvss_puntuacion'] !== '') {
            $datos['severidad'] = Severidad::desdeCvss((float) $datos['cvss_puntuacion'])->value;
        } else {
            $datos['cvss_puntuacion'] = null;
        }

        if (isset($datos['cve']) && is_string($datos['cve'])) {
            $datos['cve'] = mb_strtoupper($datos['cve']);
        }

        if (isset($datos['cwe']) && is_string($datos['cwe'])) {
            $datos['cwe'] = mb_strtoupper($datos['cwe']);
        }

        $datos['referencias'] = ($datos['referencias'] ?? []) === [] ? null : array_values($datos['referencias']);

        /*
         * La procedencia va con el CVE: sin CVE no hay de qué haberla consultado,
         * y una marca de KEV suelta sería de otro.
         */
        if (($datos['cve'] ?? null) === null) {
            $datos['kev_desde'] = null;
            $datos['nvd_consultado_el'] = null;
        }

        return $datos;
    }

    /** @return list<int> */
    public function activos(): array
    {
        /** @var list<int|string> $activos */
        $activos = $this->validated('activos') ?? [];

        return array_map(intval(...), $activos);
    }

    /**
     * La puntuación con coma decimal —«7,5»— es como se escribe aquí, y
     * `numeric` sólo entiende el punto.
     */
    protected function prepareForValidation(): void
    {
        $cvss = $this->input('cvss_puntuacion');

        if (is_string($cvss)) {
            $this->merge(['cvss_puntuacion' => str_replace(',', '.', trim($cvss))]);
        }

        // Una por fila (`CampoLista`), o una por línea si llegan como texto. La
        // lista vacía viaja como un `referencias[]` en blanco, que el middleware
        // convierte en nulo: sin filtrarlo, vaciar la lista fallaría la validación.
        $referencias = $this->input('referencias');

        if (is_string($referencias)) {
            $referencias = preg_split('/\R/', $referencias) ?: [];
        }

        if (is_array($referencias)) {
            $this->merge(['referencias' => array_values(array_filter(
                array_map(static fn (mixed $linea): mixed => is_string($linea) ? trim($linea) : $linea, $referencias),
                static fn (mixed $linea): bool => $linea !== '' && $linea !== null,
            ))]);
        }

        $this->normalizarCentinelas();
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['severidad', 'proveedor_id', 'riesgo_id', 'incidente_id', 'responsable_id'];
    }

    /** @return list<string> */
    protected function gruposDeCasillas(): array
    {
        return ['activos'];
    }
}
