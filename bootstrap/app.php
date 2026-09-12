<?php

declare(strict_types=1);

use App\Domain\Aviso\Console\EnviarAvisosCommand;
use App\Domain\Catalogo\Console\ImportarCatalogoCommand;
use App\Domain\Documento\Console\GenerarDocumentoCommand;
use App\Domain\Implantacion\Console\GenerarImplantacionesCommand;
use App\Http\Middleware\EstablecerContextoOrganizacion;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        // Los comandos del dominio viven junto a su contexto, no en
        // app/Console/Commands, así que hay que registrarlos aquí.
        ImportarCatalogoCommand::class,
        GenerarImplantacionesCommand::class,
        GenerarDocumentoCommand::class,
        EnviarAvisosCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // El orden importa y no es el que sale por defecto.
        //
        // `SubstituteBindings` resuelve los modelos de la ruta con una consulta
        // de Eloquent, y esa consulta pasa por el scope de organización. Si el
        // contexto todavía no está fijado, el scope no devuelve nada y CUALQUIER
        // ruta con `{sistema}` responde 404, incluidas las propias. Por eso el
        // contexto se coloca justo antes, sacando `SubstituteBindings` del sitio
        // que ocupa por defecto y volviéndolo a poner detrás.
        //
        // Los props compartidos de Inertia van al final: leen la organización
        // activa y necesitan que ya esté puesta.
        $middleware->web(
            remove: [SubstituteBindings::class],
            append: [
                EstablecerContextoOrganizacion::class,
                SubstituteBindings::class,
                HandleInertiaRequests::class,
            ],
        );

        /*
         * El cuerpo de un documento no se recorta.
         *
         * `TrimStrings` recorta TODA cadena de la petición, y el cuerpo del
         * editor viaja como un árbol de nodos donde cada trozo de texto es una
         * cadena suelta. Un espacio al principio de un nodo no es relleno: es la
         * separación entre ese trozo y el anterior. Recortarlo soldaba las
         * palabras —«no es una entrega.Este PDF se regenera»— en el PDF que se le
         * entrega al auditor, y además hacía que guardar sin tocar nada cambiara
         * el documento y lo sellara como editado a mano.
         *
         * `ConvertEmptyStringsToNull` va detrás por lo mismo: un nodo cuyo texto
         * es un solo espacio acabaría en `null` y reventaría el renderizador.
         *
         * `Str::is` entiende el comodín, así que `cuerpo.*` cubre el árbol
         * entero a cualquier profundidad.
         */
        $middleware->trimStrings(except: ['cuerpo', 'cuerpo.*']);

        // Estos dos corren ANTES de resolver la ruta, así que aquí no hay
        // `routeIs()` que valga: se mira el camino.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->isMethod('PUT')
                && $request->is('documentos/*/cuerpo'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Las páginas de error se pintan con Inertia, no con las plantillas de
         * Symfony.
         *
         * Aquí importa más que en otras aplicaciones: el aislamiento
         * multi-tenant responde 404 —no 403— cuando alguien pide un recurso de
         * otra organización, porque decir «existe pero no es tuyo» ya sería
         * filtrar información. Ese 404 lo va a ver gente real y con frecuencia,
         * así que tiene que explicar qué ha pasado y llevar a alguna parte.
         *
         * Los 500 sólo se maquillan fuera de depuración: en local se quiere la
         * traza de Laravel, no una pantalla bonita que la esconda.
         */
        $exceptions->respond(function (Response $respuesta, Throwable $excepcion, Request $request): Response {
            $propias = [403, 404, 419, 429, 503];
            $estado = $respuesta->getStatusCode();

            if ($request->is('api/*') || $request->expectsJson()) {
                return $respuesta;
            }

            if (! in_array($estado, $propias, true) && ! (! config('app.debug') && $estado >= 500)) {
                return $respuesta;
            }

            return Inertia::render('Error', ['estado' => $estado])
                ->toResponse($request)
                ->setStatusCode($estado);
        });
    })->create();
