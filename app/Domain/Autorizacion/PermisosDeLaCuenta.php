<?php

declare(strict_types=1);

namespace App\Domain\Autorizacion;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Qué puede hacer una cuenta, contado para que se lea.
 *
 * Es el bloque de sólo lectura de «Mi cuenta», y contesta **«¿por qué no me
 * sale este botón?»**. De ahí la decisión que da forma a esta clase: se declara
 * también lo que NO se tiene. Una lista de sólo lo concedido no contesta esa
 * pregunta, y es la única pregunta que alguien le hace a esta pantalla.
 *
 * Lo que no se hace es enumerar los permisos uno a uno con su palomita: eso es la
 * fila de ceros del inventario otra vez, se lee una vez y nunca más. Se agrupan
 * por módulo y **los módulos que no se ven enteros se resumen aparte**, en una
 * frase.
 *
 * **Sin cifras en este docblock, a propósito.** Decía «cuarenta y tres permisos» y
 * «quince filas», y las dos eran falsas mucho antes de que alguien lo mirara:
 * `porModulo()` agrupa por prefijo y se entera solo, y un recuento escrito aquí
 * envejece cada vez que entra un módulo.
 *
 * **El título y el icono de un módulo no se escriben aquí.** Viaja el `href` y
 * el cliente lo resuelve contra `lib/navegacion.ts`, que es el mapa único de la
 * aplicación y pasa a tener un quinto lector en vez de una segunda lista que
 * mantener sincronizada. Que todo prefijo tenga entrada allí lo clava
 * `PermisosDeLaCuentaTest`, porque `entradaDe()` devuelve `undefined` en
 * silencio y la fila saldría sin nombre sin que fallara nadie.
 *
 * **Salvo los que no son módulos**, que se declaran en `FUERA_DEL_MAPA`. Es el
 * patrón de `RlsDeclaradaTest` con sus cuatro excepciones: no se afloja la
 * regla, se escribe quién está exento y el test exige que quien deje de estarlo
 * salga de la lista.
 *
 * El orden es el de declaración de `Permiso`, que ya agrupa por módulo y es el
 * mismo orden con el que se lee el enum.
 */
final readonly class PermisosDeLaCuenta
{
    /**
     * Los prefijos de permiso que NO son un módulo del mapa de navegación.
     *
     * `lib/navegacion.ts` lista módulos, y la ficha del tenant no lo es: está
     * fuera a propósito, porque una entrada ahí la pintaría en el sidebar de
     * los tres roles cuando sólo uno puede abrirla. Como el cliente resuelve el
     * título contra ese mapa, aquí hace falta decirle cómo se llama — si no, su
     * fila saldría **sin nombre y sin icono**, que es justo lo que el test de
     * esta clase existe para impedir.
     *
     * @var array<string, string>
     */
    private const FUERA_DEL_MAPA = [
        'organizacion' => 'La organización',
    ];

    /**
     * @return array{
     *     roles: list<array{clave: string, etiqueta: string, descripcion: ?string}>,
     *     modulos: list<array{clave: string, href: string, etiqueta: ?string, verbos: list<array{clave: string, etiqueta: string, tiene: bool}>}>,
     *     sinAcceso: list<array{clave: string, href: string, etiqueta: ?string}>,
     * }
     */
    public static function de(User $usuario): array
    {
        $concedidos = $usuario->getAllPermissions()->pluck('name')->all();

        $modulos = [];
        $sinAcceso = [];

        foreach (self::porModulo() as $clave => $permisos) {
            $verbos = array_map(static fn (Permiso $permiso): array => [
                'clave' => $permiso->value,
                'etiqueta' => $permiso->etiqueta(),
                'tiene' => in_array($permiso->value, $concedidos, true),
            ], $permisos);

            $entrada = [
                'clave' => $clave,
                'href' => self::href($clave),
                // Nulo para un módulo de verdad: ahí manda `lib/navegacion.ts`.
                'etiqueta' => self::FUERA_DEL_MAPA[$clave] ?? null,
            ];

            /*
             * El reparto es «tengo algo de este módulo» y no «tengo su .ver»:
             * son lo mismo hoy —ningún rol escribe sin leer— y si algún día
             * dejaran de serlo, un módulo con escritura y sin lectura tiene que
             * salir en la lista larga y no escondido en el resumen.
             */
            if (array_any($verbos, static fn (array $verbo): bool => $verbo['tiene'])) {
                $modulos[] = [...$entrada, 'verbos' => $verbos];
            } else {
                $sinAcceso[] = $entrada;
            }
        }

        return [
            'roles' => self::roles($usuario),
            'modulos' => $modulos,
            'sinAcceso' => $sinAcceso,
        ];
    }

    /**
     * Los permisos agrupados por el prefijo de su clave, en orden de declaración.
     *
     * @return array<string, list<Permiso>>
     */
    private static function porModulo(): array
    {
        $grupos = [];

        foreach (Permiso::cases() as $permiso) {
            $grupos[Str::before($permiso->value, '.')][] = $permiso;
        }

        return $grupos;
    }

    /**
     * `revision_direccion` → `/revision-direccion`, que es como lo declara el
     * mapa de navegación. La correspondencia la fija un test.
     */
    private static function href(string $modulo): string
    {
        return '/'.str_replace('_', '-', $modulo);
    }

    /**
     * El rol con la descripción que ya escribe el enum, que es texto bueno y
     * no hay por qué reescribirlo aquí.
     *
     * Un nombre que no case con ningún caso de `Rol` se enseña tal cual en vez
     * de desaparecer: si alguien crea un rol a mano, verlo es lo correcto.
     *
     * @return list<array{clave: string, etiqueta: string, descripcion: ?string}>
     */
    private static function roles(User $usuario): array
    {
        return $usuario->roles
            ->map(function (Model $rol): array {
                // `getAttribute` y no `->name`: la relación de spatie está
                // tipada como `Model` y Larastan no conoce sus columnas.
                $nombre = (string) $rol->getAttribute('name');
                $conocido = Rol::tryFrom($nombre);

                return [
                    'clave' => $nombre,
                    'etiqueta' => $conocido?->etiqueta() ?? $nombre,
                    'descripcion' => $conocido?->descripcion(),
                ];
            })
            ->values()
            ->all();
    }
}
