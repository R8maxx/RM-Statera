<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Abrir una tarea desde un proveedor. Los mismos campos que la de una prueba de
 * continuidad, que ya es la forma común de «tarea que nace de otro registro»,
 * **salvo que aquí responsable y fecha límite son obligatorios**.
 *
 * Lo que se abre desde un proveedor es casi siempre lo que levanta una
 * condición de su evaluación —firmar el encargo, pedir el certificado nuevo—, y
 * mientras no se haga el proveedor sigue condicionado. Una tarea así sin nadie
 * detrás y sin fecha es la que se queda en el plan de acción hasta la
 * reevaluación del año siguiente. La conclusión que la pide ya suele decir
 * «antes de fin de año»; la tarea tiene que decirlo también.
 */
class DerivarTareaDeProveedorRequest extends DerivarTareaDePruebaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = parent::rules();

        $reglas['responsable_id'][0] = 'required';
        $reglas['fecha_limite'] = ['required', 'date'];

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'responsable_id.required' => 'Di quién se encarga. Sin responsable, nadie la mueve y el proveedor sigue condicionado.',
            'fecha_limite.required' => 'Pon para cuándo. Sin fecha, la tarea no avisa y nada la saca del plan de acción.',
        ];
    }
}
