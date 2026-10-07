<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Plataforma\Models\Plan;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Alta de una organización cliente desde la plataforma (punto 41).
 *
 * Se pide lo mínimo para que el cliente pueda entrar: cómo se llama y quién es
 * su primer responsable de seguridad. El resto de la ficha —domicilio, las dos
 * banderas del ENS, la marca— la rellena el propio cliente: es suya.
 *
 * El correo del responsable es único **en todo Statera**, por lo mismo que el
 * de cualquier cuenta: es con lo que se entra, y el login todavía no sabe de
 * qué organización es nadie.
 */
class AltaOrganizacionRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'cif' => ['nullable', 'string', 'max:20', Rule::unique('organizaciones', 'cif')],
            'sector' => ['nullable', 'string', 'max:255'],
            'responsable_nombre' => ['required', 'string', 'max:255'],
            'responsable_email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'plan_id' => ['nullable', 'integer', Rule::exists('planes', 'id')->where('activo', true)],
            'vence_en' => ['nullable', 'date', 'after:today', 'prohibited_if:plan_id,null'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'razon_social' => 'razón social',
            'responsable_nombre' => 'nombre del responsable',
            'responsable_email' => 'correo del responsable',
            'plan_id' => 'plan',
            'vence_en' => 'vencimiento',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'vence_en.prohibited_if' => 'Sin plan la suscripción no vence: quita la fecha o elige un plan.',
        ];
    }

    public function plan(): ?Plan
    {
        $id = $this->validated('plan_id');

        return $id === null ? null : Plan::query()->findOrFail((int) $id);
    }

    public function venceEn(): ?Carbon
    {
        $fecha = $this->validated('vence_en');

        return is_string($fecha) ? Carbon::parse($fecha)->endOfDay() : null;
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['plan_id'];
    }

    /**
     * @return array{nombre: string, cif: ?string, razon_social: ?string, sector: ?string}
     */
    public function ficha(): array
    {
        return [
            'nombre' => (string) $this->validated('nombre'),
            'cif' => $this->validated('cif'),
            'razon_social' => $this->validated('razon_social'),
            'sector' => $this->validated('sector'),
        ];
    }
}
