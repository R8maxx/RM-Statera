<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Los recorridos guiados
|--------------------------------------------------------------------------
|
| Dos fallos que no rompen nada y por eso no los ve nadie:
|
| 1. **Un ancla que ya no existe.** El overlay la descarta y el paso se pinta
|    centrado, sin señalar nada. Es lo que pasa cuando alguien rehace una
|    pantalla y se lleva por delante un `data-recorrido`.
| 2. **Un módulo del lateral sin recorrido.** La pantalla funciona; quien llega
|    a ella se la encuentra sin explicar, que es el problema que los recorridos
|    existen para cerrar.
|
| Los dos se descubren leyendo los ficheros, no con una lista: el módulo
| siguiente entra solo.
|
*/

/**
 * Los ficheros donde se declaran los pasos.
 *
 * @return list<string>
 */
function ficherosDeRecorridos(): array
{
    $ficheros = [base_path('resources/js/lib/recorridos.ts')];

    foreach (Finder::create()->files()->in(base_path('resources/js/lib/recorridos'))->name('*.ts') as $fichero) {
        $ficheros[] = $fichero->getRealPath();
    }

    return $ficheros;
}

/**
 * Cada ancla que algún paso pide, con el fichero que la pide.
 *
 * @return array<string, string>
 */
function anclasPedidas(): array
{
    $pedidas = [];

    foreach (ficherosDeRecorridos() as $fichero) {
        $fuente = (string) file_get_contents($fichero);

        preg_match_all('/anclas:\s*\[(.*?)\]/s', $fuente, $listas);

        foreach ($listas[1] as $lista) {
            // `anclaGrupo('Plan')` se resuelve igual que en el cliente.
            $lista = (string) preg_replace_callback(
                "/anclaGrupo\('([^']+)'\)/u",
                fn (array $m): string => "'grupo-".Str::slug($m[1])."'",
                $lista,
            );

            preg_match_all("/'([a-z0-9-]+)'/", $lista, $anclas);

            foreach ($anclas[1] as $ancla) {
                $pedidas[$ancla] = basename($fichero);
            }
        }
    }

    return $pedidas;
}

/**
 * Las anclas que existen: las escritas en algún componente y las que
 * `AppLayout` deriva de `navegacion.ts` —`nav-<ruta>` y `grupo-<título>`—.
 *
 * @return list<string>
 */
function anclasDisponibles(): array
{
    $disponibles = [];

    foreach (Finder::create()->files()->in(base_path('resources/js'))->name('*.vue') as $fichero) {
        $fuente = $fichero->getContents();

        preg_match_all('/(?<![:\w-])data-recorrido="([a-z0-9-]+)"/', $fuente, $m);
        $disponibles = [...$disponibles, ...$m[1]];

        // Las ligadas a una lista, como los pasos de `PrimerosPasos`: `ancla: 'paso-sistema'`.
        if (str_contains($fuente, ':data-recorrido=')) {
            preg_match_all("/ancla: '([a-z0-9-]+)'/", $fuente, $m);
            $disponibles = [...$disponibles, ...$m[1]];
        }
    }

    $navegacion = (string) file_get_contents(base_path('resources/js/lib/navegacion.ts'));

    preg_match_all("/href: '\/([^']*)'/", $navegacion, $rutas);
    foreach ($rutas[1] as $ruta) {
        $disponibles[] = 'nav-'.str_replace('/', '-', $ruta);
    }

    preg_match_all("/titulo: '([^']+)'/u", $navegacion, $titulos);
    foreach ($titulos[1] as $titulo) {
        $disponibles[] = 'grupo-'.Str::slug($titulo);
    }

    return array_values(array_unique($disponibles));
}

it('encuentra los pasos que comprueba', function (): void {
    expect(anclasPedidas())->not->toBeEmpty('No se encuentra ninguna lista de anclas en lib/recorridos.');
});

it('cada ancla que pide un paso existe en algún sitio', function (): void {
    $disponibles = anclasDisponibles();

    foreach (anclasPedidas() as $ancla => $fichero) {
        expect(in_array($ancla, $disponibles, true))->toBeTrue(
            "El ancla `{$ancla}` que pide {$fichero} no está en ningún `data-recorrido`: "
            .'el paso saldría centrado sin señalar nada.',
        );
    }
});

/**
 * Las pantallas del lateral que no llevan recorrido, cada una con su motivo.
 *
 * @var array<string, string>
 */
const SIN_RECORRIDO = [
    'panel' => 'Lleva el general, que arranca `Cumplimiento.vue` y no la cabecera.',
    'cuentas' => 'Administración: la ve quien gestiona cuentas, que ya conoce la herramienta.',
    'plantillas-documento' => 'Administración de las plantillas, no trabajo del SGSI.',
];

it('cada módulo del lateral llega con su recorrido', function (): void {
    $navegacion = (string) file_get_contents(base_path('resources/js/lib/navegacion.ts'));
    preg_match_all("/href: '\/([a-z0-9-]+)/", $navegacion, $rutas);

    expect($rutas[1])->not->toBeEmpty('No se encuentran las rutas de lib/navegacion.ts.');

    foreach (array_unique($rutas[1]) as $modulo) {
        if (array_key_exists($modulo, SIN_RECORRIDO)) {
            continue;
        }

        $directorio = base_path("resources/js/pages/{$modulo}");

        expect(is_dir($directorio))->toBeTrue("El módulo `{$modulo}` de navegacion.ts no tiene `pages/{$modulo}/`.");

        $conRecorrido = false;

        foreach (Finder::create()->files()->in($directorio)->name('*.vue') as $fichero) {
            if (preg_match('/(?<![:\w-])recorrido="[a-z0-9-]+"/', $fichero->getContents()) === 1) {
                $conRecorrido = true;

                break;
            }
        }

        expect($conRecorrido)->toBeTrue(
            "Ninguna pantalla de `pages/{$modulo}/` pasa `recorrido` a su `CabeceraPagina`. "
            .'Añádele uno en lib/recorridos/, o decláralo en SIN_RECORRIDO con su motivo.',
        );
    }
});
