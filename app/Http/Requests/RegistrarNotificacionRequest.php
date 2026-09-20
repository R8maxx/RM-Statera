<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anotar que se notificó el incidente a un supervisor.
 *
 * **Ruta propia y no un campo del formulario largo**: notificar a la AEPD o al
 * CCN-CERT es un acto con fecha, y es el dato que el auditor comprueba contra el
 * justificante. Mezclado con los otros veinte campos se rellenaría de pasada.
 *
 * **La fecha se admite y no se impone**, a diferencia de la de cierre de una
 * tarea: la notificación se hace en la sede del supervisor y se apunta aquí
 * después, a veces al día siguiente. Imponer `now()` fabricaría una fecha que no
 * es la del justificante — y en un incidente fuera de plazo eso cambia si hubo
 * incumplimiento o no.
 */
class RegistrarNotificacionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destinatario' => ['required', Rule::in(['aepd', 'ccn_cert'])],
            'notificado_en' => ['nullable', 'date'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['notificado_en' => 'fecha de la notificación'];
    }
}
