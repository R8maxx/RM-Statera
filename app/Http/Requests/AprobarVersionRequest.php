<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La firma de la dirección.
 *
 * La nota es opcional y es **lo que dijo quien firma** —«aprobado en el comité
 * del 3 de marzo»—, no por qué hay una versión nueva; eso lo escribió quien la
 * preparó al mandarla a revisión. Son dos personas en dos momentos, y por eso son
 * dos columnas: con una sola, firmar pisaría el razonamiento que el auditor
 * quiere leer justo al lado de la firma.
 *
 * Que la versión esté en revisión y tenga PDF NO se comprueba aquí: son reglas
 * de estado y viven en `AprobarVersion`, para que valgan igual desde un comando.
 */
class AprobarVersionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nota' => 'nota de aprobación',
        ];
    }
}
