<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Convierte a nulo el valor centinela de «ninguno» de los desplegables.
 *
 * Reka —como Radix— prohíbe el valor vacío en un `SelectItem`, así que un
 * desplegable opcional necesita una opción con valor propio para decir «sin
 * responsable» o «sin evaluar». Ese centinela es cosa de la interfaz y no tiene
 * que llegar a la validación: aquí se traduce de vuelta a lo que significa.
 *
 * El valor está en `SeleccionVacia::VALOR` y en `SIN_VALOR` de
 * `resources/js/lib/formularios.ts`. Los dos tienen que decir lo mismo.
 */
trait NormalizaSeleccionVacia
{
    /**
     * Los campos cuyo centinela hay que traducir.
     *
     * @return list<string>
     */
    abstract protected function seleccionesOpcionales(): array;

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach ($this->seleccionesOpcionales() as $campo) {
            if ($this->input($campo) === SeleccionVacia::VALOR) {
                $normalizados[$campo] = null;
            }
        }

        if ($normalizados !== []) {
            $this->merge($normalizados);
        }
    }
}
