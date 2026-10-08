<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\TipoRescate;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedir un rescate de cuenta (punto 52).
 *
 * **La verificación es obligatoria y no puede ser una palabra**: es lo que
 * leerá quien la ejecute para decidir si se fía, y lo que leerá un auditor.
 * «Llamada al teléfono de la ficha del cliente, confirmada con su director
 * general» es una verificación; «ok» no.
 *
 * La cuenta se valida acotada a la organización de la ruta y fuera de la
 * plataforma: `users` no tiene RLS.
 */
class SolicitarRescateRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Organizacion $organizacion */
        $organizacion = $this->route('organizacion');
        $sinCuenta = $this->input('cuenta_id') === null;
        $designar = $this->input('tipo') === TipoRescate::DesignarResponsable->value;

        return [
            'tipo' => ['required', Rule::enum(TipoRescate::class)],
            'cuenta_id' => [
                $designar ? 'nullable' : 'required',
                'integer',
                // Con closure: con `where('es_plataforma', false)` a secas la regla
                // enlaza el booleano como cadena vacía y PostgreSQL lo rechaza.
                Rule::exists('users', 'id')
                    ->where('organizacion_id', $organizacion->id)
                    ->where(static fn ($consulta) => $consulta->where('es_plataforma', false)),
            ],
            'nombre' => [$designar && $sinCuenta ? 'required' : 'prohibited', 'nullable', 'string', 'max:255'],
            'email' => [$designar && $sinCuenta ? 'required' : 'prohibited', 'nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'verificacion' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'verificacion.min' => 'Cuenta cómo se comprobó quién lo pide: quién llamó, a qué teléfono o correo de la ficha, con quién se confirmó.',
            'cuenta_id.exists' => 'Esa cuenta no es de esta organización.',
            'email.unique' => 'Ya hay una cuenta con ese correo en Statera.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['cuenta_id' => 'cuenta', 'verificacion' => 'verificación'];
    }

    public function tipo(): TipoRescate
    {
        return TipoRescate::from((string) $this->validated('tipo'));
    }

    /** @return array{nombre?: string, email?: string} */
    public function datos(): array
    {
        return array_filter([
            'nombre' => $this->validated('nombre'),
            'email' => $this->validated('email'),
        ], static fn ($valor): bool => is_string($valor) && $valor !== '');
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['cuenta_id'];
    }
}
