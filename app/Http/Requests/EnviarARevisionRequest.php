<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Documento\Models\Documento;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mandar el borrador a quien tiene que firmarlo.
 *
 * El motivo es obligatorio en cuanto hay una versión anterior: «¿por qué hay una
 * v4?» es la primera pregunta del auditor, y contestarla después de seis meses no
 * lo hace nadie. En la primera entrega no se pide, porque el motivo es obvio.
 *
 * **Se pide aquí y no al firmar**, y eso cambió con el flujo de aprobación: por
 * qué hay una versión nueva lo sabe quien la ha preparado, no quien la firma. Lo
 * que escribe quien firma es otra cosa y va en `nota_aprobacion`, igual que
 * `nota` y `nota_aceptacion` en una valoración de riesgo.
 *
 * Que el borrador esté generado NO se comprueba aquí: es una regla de estado y
 * vive en el dominio, para que valga igual desde un comando de consola.
 */
class EnviarARevisionRequest extends FormRequest
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
            'motivo.required' => 'Di por qué hay una versión nueva: es lo primero que pregunta el auditor.',
        ];
    }
}
