<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La ficha comercial de un cliente, desde la plataforma (punto 54).
 */
class GuardarFichaComercialRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Organizacion $organizacion */
        $organizacion = $this->route('organizacion');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'cif' => ['nullable', 'string', 'max:20', Rule::unique('organizaciones', 'cif')->ignore($organizacion->id)],
            'sector' => ['nullable', 'string', 'max:255'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_email' => ['nullable', 'string', 'email', 'max:255'],
            'contacto_telefono' => ['nullable', 'string', 'max:40'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'razon_social' => 'razón social',
            'contacto_nombre' => 'nombre del contacto',
            'contacto_email' => 'correo del contacto',
            'contacto_telefono' => 'teléfono del contacto',
        ];
    }

    /** @return array{nombre: string, razon_social: ?string, cif: ?string, sector: ?string} */
    public function identificacion(): array
    {
        return [
            'nombre' => (string) $this->validated('nombre'),
            'razon_social' => $this->validated('razon_social'),
            'cif' => $this->validated('cif'),
            'sector' => $this->validated('sector'),
        ];
    }

    /** @return array{contacto_nombre: ?string, contacto_email: ?string, contacto_telefono: ?string, notas: ?string} */
    public function comercial(): array
    {
        return [
            'contacto_nombre' => $this->validated('contacto_nombre'),
            'contacto_email' => $this->validated('contacto_email'),
            'contacto_telefono' => $this->validated('contacto_telefono'),
            'notas' => $this->validated('notas'),
        ];
    }
}
