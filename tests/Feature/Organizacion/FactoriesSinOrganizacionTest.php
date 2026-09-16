<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ninguna factory decide de qué organización es una fila
|--------------------------------------------------------------------------
|
| `PerteneceAOrganizacion` rellena `organizacion_id` en el evento `creating`
| **sólo si viene a nulo**. Una factory que lo declare en su `definition()`
| cortocircuita esa red: la fila nace con el tenant que diga la factory, que no
| es el que fijó `comoOrganizacion()`, y el `WITH CHECK` de la política RLS la
| rechaza con un «insufficient privilege» que no menciona la palabra
| «organización».
|
| Eso es lo que tuvo nueve tests de riesgos en rojo desde el día que se
| escribieron, y lo causaba una sola línea de `SistemaFactory`. Este test impide
| la recaída, y se parametriza solo sobre las factories que existan.
|
| Declararlo en un `state` —`->de($organizacion)`— sí vale: ahí es una decisión
| explícita de quien escribe el test, no el valor por defecto de todo el mundo.
|
*/

/**
 * La ruta va literal y no por `database_path()`: el dataset de Pest se resuelve
 * antes de que arranque la aplicación, así que ahí todavía no hay contenedor.
 *
 * @return list<string>
 */
function factoriesDelProyecto(): array
{
    $ficheros = glob(__DIR__.'/../../../database/factories/*Factory.php') ?: [];

    return array_values(array_map(
        static fn (string $ruta): string => 'Database\\Factories\\'.basename($ruta, '.php'),
        $ficheros,
    ));
}

it('ninguna factory declara organizacion_id por defecto', function (string $factory): void {
    expect(class_exists($factory))->toBeTrue("No se puede cargar {$factory}.");

    $definicion = (new $factory)->definition();

    expect($definicion)->not->toHaveKey('organizacion_id', sprintf(
        '%s declara `organizacion_id` en su `definition()`. Eso cortocircuita '
        .'`PerteneceAOrganizacion` y la fila nace con otro tenant, que RLS rechaza. '
        .'Déjalo fuera y ponlo en un `state` si un test concreto lo necesita.',
        class_basename($factory),
    ));
})->with(fn () => factoriesDelProyecto());
