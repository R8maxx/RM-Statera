<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Persona\Models\Persona;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una cuenta (§ 4.19).
 *
 * **El nombre y el correo sólo se piden al invitar.** Después son de la propia
 * cuenta, que los cambia desde `/perfil`: dejar que otro reescriba el correo de
 * una cuenta es dejarle que se quede con ella pidiendo una contraseña nueva.
 *
 * El correo es único **en todo Statera** y no sólo en la organización, porque
 * es con lo que se entra y el login todavía no sabe de qué organización es
 * nadie.
 *
 * Que un auditor tenga sistemas y fecha de fin se comprueba aquí, para que el
 * error salga junto al campo, y otra vez en `AlcanceDeCuenta`, que es donde la
 * regla vive y la que vale para el seeder.
 */
class GuardarCuentaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cuenta = $this->route('cuenta');
        $esAlta = ! $cuenta instanceof User;
        $esAuditor = $this->input('rol') === Rol::Auditor->value;

        return [
            'name' => $esAlta ? ['required', 'string', 'max:255'] : ['prohibited'],
            'email' => $esAlta
                ? ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')]
                : ['prohibited'],

            'rol' => ['required', Rule::enum(Rol::class)],

            'sistemas' => [$esAuditor ? 'required' : 'nullable', 'array', $esAuditor ? 'min:1' : 'max:0'],
            // RLS: un sistema de otra organización no existe para esta consulta.
            'sistemas.*' => ['integer', Rule::exists('sistemas', 'id')],

            'acceso_hasta' => [
                $esAuditor ? 'required' : 'prohibited',
                'nullable',
                'date',
                'after_or_equal:today',
            ],

            'persona_id' => [
                'nullable',
                'integer',
                Rule::exists('personas', 'id')->where(function ($consulta) use ($cuenta): void {
                    $consulta->where(fn ($libre) => $libre
                        ->whereNull('user_id')
                        ->when($cuenta instanceof User, fn ($propia) => $propia->orWhere('user_id', $cuenta->id)));
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sistemas.required' => 'Un auditor externo tiene que ver al menos un sistema: es lo que se audita.',
            'sistemas.min' => 'Un auditor externo tiene que ver al menos un sistema: es lo que se audita.',
            'sistemas.max' => 'Sólo la cuenta de auditor se acota a unos sistemas; las demás ven la organización entera.',
            'acceso_hasta.required' => 'Un auditor externo entra mientras dura la auditoría: pon la fecha en que termina.',
            'acceso_hasta.prohibited' => 'Sólo la cuenta de auditor lleva fecha de fin.',
            'persona_id.exists' => 'Esa persona ya está enlazada con otra cuenta.',
            'email.unique' => 'Ya hay una cuenta con ese correo en Statera.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'acceso_hasta' => 'fin del acceso',
            'persona_id' => 'persona',
        ];
    }

    public function rol(): Rol
    {
        return Rol::from((string) $this->validated('rol'));
    }

    /** @return list<int> */
    public function sistemas(): array
    {
        /** @var list<int|string> $sistemas */
        $sistemas = $this->validated('sistemas') ?? [];

        return array_map(intval(...), $sistemas);
    }

    public function accesoHasta(): ?Carbon
    {
        $fecha = $this->validated('acceso_hasta');

        return is_string($fecha) ? Carbon::parse($fecha)->startOfDay() : null;
    }

    public function persona(): ?Persona
    {
        $id = $this->validated('persona_id');

        return $id === null ? null : Persona::query()->findOrFail((int) $id);
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['persona_id'];
    }

    /** @return list<string> */
    protected function gruposDeCasillas(): array
    {
        return ['sistemas'];
    }
}
