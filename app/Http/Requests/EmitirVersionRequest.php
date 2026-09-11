<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Documento\Models\Documento;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Emitir es entregar, y a partir de ahí la versión es inmutable.
 *
 * El motivo es obligatorio en cuanto hay una versión anterior: «¿por qué hay una
 * v4?» es la primera pregunta del auditor, y contestarla después de seis meses
 * no lo hace nadie. En la primera entrega no se pide, porque el motivo es obvio.
 *
 * Que el borrador esté generado NO se comprueba aquí: es una regla de estado y
 * vive en `DocumentoVersion::esEmisible()`, para que valga igual desde un
 * comando de consola.
 */
class EmitirVersionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $documento = $this->route('documento');
        $hayAnteriores = $documento instanceof Documento
            && $documento->versiones()->whereNotNull('numero')->exists();

        return [
            'motivo' => [$hayAnteriores ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Di por qué se emite una versión nueva: es lo primero que pregunta el auditor.',
        ];
    }
}
