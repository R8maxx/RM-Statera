<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ninguna consulta de usuarios cruza la frontera de organización
|--------------------------------------------------------------------------
|
| `User` es el único modelo de datos propios que se queda **fuera de las tres
| capas**: no lleva `PerteneceAOrganizacion`, no tiene scope global y no tiene
| RLS. La autenticación tiene que poder encontrar a alguien antes de saber de qué
| organización es, así que no puede ser de otra forma.
|
| La consecuencia es que un `User::query()` inocente —el desplegable de
| responsables, la lista de destinatarios de un acuse— **lista a los usuarios de
| todos los clientes**, y no lo caza ningún otro test de aislamiento, porque los
| que hay interrogan al esquema y aquí no hay nada que interrogar: la fuga no
| está en la base, está en la consulta.
|
| Este test es la capa que falta. Llegó cuando el § 4.16 fue a tocar el
| desplegable del calendario y aparecieron **doce** consultas sin acotar en seis
| módulos —controlador y recurso de cada uno—, mientras los otros diez sitios sí
| lo hacían y uno de ellos lo llevaba comentado. Con diecisiete sitios repartidos
| por dos capas, acordarse no es un mecanismo.
|
| Se acota con `->where('organizacion_id', …)`, y la organización se toma
| preferentemente de la fila que se está mirando —`$version->organizacion_id`— y
| no del contexto: bajo RLS es la misma, y así la consulta no depende de que
| alguien haya fijado el contexto antes.
|
*/

/**
 * Cada `User::query()` del código, con el fichero y la línea donde está.
 *
 * Mira **las cinco líneas siguientes** y no sólo la de al lado: entre la consulta
 * y su `where` puede haber un `select` o un `when`, y exigir que el `where` vaya
 * pegado obligaría a escribir la consulta de una forma concreta en vez de a
 * acotarla.
 *
 * **Las líneas de comentario no cuentan.** `PlantillaDocumentoController` explica
 * en su docblock por qué ahí no hace falta acotar —los ids salen de filas que sí
 * están acotadas—, y un test que lea esa explicación como una infracción enseña a
 * no escribirlas.
 *
 * @return list<array{0: string, 1: int}>
 */
function consultasDeUsuario(): array
{
    $raiz = __DIR__.'/../../../app';

    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
    );

    $encontradas = [];

    /** @var SplFileInfo $fichero */
    foreach ($iterador as $fichero) {
        if ($fichero->getExtension() !== 'php') {
            continue;
        }

        $lineas = file($fichero->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lineas as $indice => $linea) {
            if (! str_contains($linea, 'User::query()') || esComentario($linea)) {
                continue;
            }

            $encontradas[] = [
                str_replace($raiz.'/', 'app/', $fichero->getPathname()),
                $indice + 1,
            ];
        }
    }

    sort($encontradas);

    return $encontradas;
}

function esComentario(string $linea): bool
{
    $limpia = ltrim($linea);

    return str_starts_with($limpia, '*')
        || str_starts_with($limpia, '//')
        || str_starts_with($limpia, '/*');
}

it('acota por organización toda consulta de usuarios', function (string $fichero, int $linea): void {
    $ruta = __DIR__.'/../../../'.$fichero;
    $lineas = file($ruta, FILE_IGNORE_NEW_LINES) ?: [];

    $ventana = implode(' ', array_slice($lineas, $linea - 1, 6));

    // `toBeTrue` y no `toContain`: `toContain` es variádico en Pest y se tragaría
    // el mensaje como una segunda aguja, con lo que el test fallaría siempre y por
    // el motivo equivocado.
    expect(str_contains($ventana, 'organizacion_id'))->toBeTrue(sprintf(
        "%s:%d hace un `User::query()` sin acotar por organización.\n"
        .'`User` está fuera de las tres capas —sin scope global y sin RLS—, así que '
        ."esa consulta lista a los usuarios de todos los clientes.\n"
        .'Añade `->where(\'organizacion_id\', …)`, tomando la organización de la fila '
        .'que estés mirando o, si no hay ninguna, de `ContextoOrganizacion::idObligatorio()`.',
        $fichero,
        $linea,
    ));
})->with(fn () => consultasDeUsuario());
