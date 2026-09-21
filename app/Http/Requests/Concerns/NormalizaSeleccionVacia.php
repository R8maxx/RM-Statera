<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Traduce los dos centinelas de «ninguno» que manda la interfaz.
 *
 * **El del desplegable.** Reka —como Radix— prohíbe el valor vacío en un
 * `SelectItem`, así que un desplegable opcional necesita una opción con valor
 * propio para decir «sin responsable» o «sin evaluar». Ese centinela es cosa de
 * la interfaz y no tiene que llegar a la validación: aquí se traduce de vuelta a
 * lo que significa. El valor está en `SeleccionVacia::VALOR` y en `SIN_VALOR` de
 * `resources/js/lib/formularios.ts`. Los dos tienen que decir lo mismo.
 *
 * **El del grupo de casillas**, que es más traicionero y estuvo roto.
 * `CampoCasillas` manda `<input name="x[]" value="">` cuando no hay nada
 * marcado, porque sin ese campo el grupo desaparece del `FormData` y el servidor
 * entendería «no tocar» en vez de «ninguno». Pero `ConvertEmptyStringsToNull`
 * **recorre los arrays**, así que al servidor no llegaba `[]` sino `[null]`, y
 * `[null]` sí se valida: un `integer` sobre nulo falla y el error sale en
 * `x.0`, que no es la clave que la página liga. Resultado: el formulario no
 * guardaba y no decía por qué.
 *
 * Los dos ganchos van en el mismo trait **y no en uno nuevo** porque los dos
 * necesitan `prepareForValidation`, y dos traits que lo declaren colisionan.
 */
trait NormalizaSeleccionVacia
{
    /**
     * Los campos cuyo centinela hay que traducir.
     *
     * @return list<string>
     */
    abstract protected function seleccionesOpcionales(): array;

    /**
     * Los campos de array que pueden llegar con el centinela de «ninguno».
     *
     * Con implementación por defecto: la mayoría de los formularios no tiene
     * grupos de casillas, y obligarles a declarar una lista vacía es ruido.
     *
     * @return list<string>
     */
    protected function gruposDeCasillas(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarCentinelas();
    }

    /**
     * Se separa de `prepareForValidation` para que un `FormRequest` con gancho
     * propio —`GuardarIncidenteRequest` deriva además sus booleanos— pueda
     * llamarlo sin renunciar al suyo.
     */
    protected function normalizarCentinelas(): void
    {
        $normalizados = [];

        foreach ($this->seleccionesOpcionales() as $campo) {
            if ($this->input($campo) === SeleccionVacia::VALOR) {
                $normalizados[$campo] = null;
            }
        }

        foreach ($this->gruposDeCasillas() as $campo) {
            $valor = $this->input($campo);

            if (! is_array($valor)) {
                continue;
            }

            $normalizados[$campo] = array_values(array_filter(
                $valor,
                static fn (mixed $elemento): bool => $elemento !== null && $elemento !== '',
            ));
        }

        if ($normalizados !== []) {
            $this->merge($normalizados);
        }
    }
}
