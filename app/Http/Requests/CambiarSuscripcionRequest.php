<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Plataforma\Models\Plan;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * El plan y el vencimiento de un cliente (punto 43). Sin plan no hay fecha: la
 * suscripción sin plan no vence.
 */
class CambiarSuscripcionRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['nullable', 'integer', Rule::exists('planes', 'id')],
            'vence_en' => ['nullable', 'date', 'prohibited_if:plan_id,null'],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'vence_en.prohibited_if' => 'Sin plan la suscripción no vence: quita la fecha o elige un plan.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['plan_id' => 'plan', 'vence_en' => 'vencimiento'];
    }

    public function plan(): ?Plan
    {
        $id = $this->validated('plan_id');

        return $id === null ? null : Plan::query()->findOrFail((int) $id);
    }

    /** El final del día elegido: vence al acabar ese día, no al empezar. */
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
}
