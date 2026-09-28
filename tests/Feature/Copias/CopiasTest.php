<?php

declare(strict_types=1);

use App\Domain\Copia\CifradoDeCopias;
use App\Domain\Copia\EspejoDeObjetos;
use App\Domain\Copia\Excepciones\CopiaInvalida;
use App\Domain\Copia\HacerCopia;
use App\Domain\Copia\VerificarCopia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Copias cifradas con restauración probada (§ 6, punto 34)
|--------------------------------------------------------------------------
|
| Los de punta a punta corren `pg_dump` y `pg_restore` de verdad contra la base
| de tests, con el rol de copias. Ese rol va por otra conexión y no ve la
| transacción abierta de `RefrescaLaBase`, así que la base que se copia es la
| recién migrada: basta para probar que el volcado sale de una instantánea, se
| cifra, se restaura y se compara, que es lo que se prueba aquí.
|
*/

beforeEach(function (): void {
    foreach (['copias', 'evidencias', 'documentos', 'adjuntos'] as $disco) {
        Storage::fake($disco);
    }

    $this->temporal = storage_path('framework/testing/copias-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($this->temporal);
});

afterEach(function (): void {
    File::deleteDirectory($this->temporal);
});

// --- Cifrado -----------------------------------------------------------------

it('descifra exactamente lo que cifró, aunque ocupe varios trozos', function (): void {
    $claro = "{$this->temporal}/claro";
    // Dos trozos y medio: el último, más corto, es el que lleva la marca de final.
    file_put_contents($claro, random_bytes((int) (2.5 * 1024 * 1024)));

    app(CifradoDeCopias::class)->cifrar($claro, "{$this->temporal}/cifrado");
    app(CifradoDeCopias::class)->descifrar("{$this->temporal}/cifrado", "{$this->temporal}/vuelta");

    expect(hash_file('sha256', "{$this->temporal}/vuelta"))->toBe(hash_file('sha256', $claro))
        ->and(file_get_contents("{$this->temporal}/cifrado"))->not->toContain(substr((string) file_get_contents($claro), 0, 64));
});

it('no descifra una copia con un solo byte cambiado', function (): void {
    file_put_contents("{$this->temporal}/claro", str_repeat('statera', 1000));
    app(CifradoDeCopias::class)->cifrar("{$this->temporal}/claro", "{$this->temporal}/cifrado");

    $bytes = (string) file_get_contents("{$this->temporal}/cifrado");
    $bytes[100] = $bytes[100] === 'a' ? 'b' : 'a';
    file_put_contents("{$this->temporal}/cifrado", $bytes);

    app(CifradoDeCopias::class)->descifrar("{$this->temporal}/cifrado", "{$this->temporal}/vuelta");
})->throws(CopiaInvalida::class, 'no supera la autenticación');

it('no da por buena una copia truncada', function (): void {
    // Es la subida que se cortó a la mitad: sin la marca de final, restauraría
    // media base sin avisar.
    file_put_contents("{$this->temporal}/claro", random_bytes(3 * 1024 * 1024));
    app(CifradoDeCopias::class)->cifrar("{$this->temporal}/claro", "{$this->temporal}/cifrado");

    $bytes = (string) file_get_contents("{$this->temporal}/cifrado");
    file_put_contents("{$this->temporal}/cifrado", substr($bytes, 0, 24 + 1024 * 1024 + 17));

    app(CifradoDeCopias::class)->descifrar("{$this->temporal}/cifrado", "{$this->temporal}/vuelta");
})->throws(CopiaInvalida::class, 'truncada');

it('se niega a cifrar sin clave', function (): void {
    config(['copias.clave' => null]);
    file_put_contents("{$this->temporal}/claro", 'x');

    app(CifradoDeCopias::class)->cifrar("{$this->temporal}/claro", "{$this->temporal}/cifrado");
})->throws(CopiaInvalida::class, 'COPIAS_CLAVE está vacía');

// --- De punta a punta --------------------------------------------------------

it('hace una copia cifrada con su manifiesto y el espejo de los ficheros', function (): void {
    Storage::disk('evidencias')->put('1/7/acta.pdf', 'contenido de la evidencia');

    $this->artisan('copias:hacer')->assertSuccessful();

    $disco = Storage::disk('copias');
    [$nombre] = HacerCopia::copias($disco);
    $manifiesto = json_decode((string) $disco->get(HacerCopia::ruta($nombre, 'manifiesto.json')), true);

    expect($manifiesto['recuentos']['migrations'])->toBeGreaterThan(100)
        ->and($manifiesto['huella'])->toBe(hash('sha256', (string) $disco->get(HacerCopia::ruta($nombre, 'base.cifrada'))))
        ->and($disco->get(EspejoDeObjetos::rutaEnCopia('evidencias', '1/7/acta.pdf')))->toBe('contenido de la evidencia')
        // Cifrada: el volcado de pg_dump empieza por «PGDMP» y esto no.
        ->and(substr((string) $disco->get(HacerCopia::ruta($nombre, 'base.cifrada')), 0, 5))->not->toBe('PGDMP');
});

it('restaura la copia en otra base y la da por buena', function (): void {
    $this->artisan('copias:hacer')->assertSuccessful();

    $this->artisan('copias:verificar')
        ->expectsOutputToContain('La copia se restaura entera')
        ->assertSuccessful();

    expect(Storage::disk('copias')->files(VerificarCopia::PREFIJO))->toHaveCount(1);
});

it('da la copia por mala si una tabla no restaura las filas que tenía', function (): void {
    $this->artisan('copias:hacer')->assertSuccessful();

    $disco = Storage::disk('copias');
    [$nombre] = HacerCopia::copias($disco);
    $ruta = HacerCopia::ruta($nombre, 'manifiesto.json');
    $manifiesto = json_decode((string) $disco->get($ruta), true);
    $manifiesto['recuentos']['migrations']++;
    $disco->put($ruta, (string) json_encode($manifiesto));

    $this->artisan('copias:verificar')
        ->expectsOutputToContain('migrations')
        ->assertFailed();
});

it('no restaura una copia que no es la que se subió', function (): void {
    $this->artisan('copias:hacer')->assertSuccessful();

    $disco = Storage::disk('copias');
    [$nombre] = HacerCopia::copias($disco);
    $disco->append(HacerCopia::ruta($nombre, 'base.cifrada'), 'basura');

    $this->artisan('copias:verificar');
})->throws(CopiaInvalida::class, 'no es la que se subió');

it('retira las copias que pasan del plazo y nunca la última', function (): void {
    $disco = Storage::disk('copias');
    $vieja = Carbon::now()->subDays(40)->format('Y-m-d\THis');
    $reciente = Carbon::now()->subDays(3)->format('Y-m-d\THis');

    foreach ([$vieja, $reciente] as $nombre) {
        $disco->put(HacerCopia::ruta($nombre, 'manifiesto.json'), '{}');
    }

    $this->artisan('copias:hacer')->assertSuccessful();

    expect($disco->exists(HacerCopia::ruta($vieja, 'manifiesto.json')))->toBeFalse()
        ->and($disco->exists(HacerCopia::ruta($reciente, 'manifiesto.json')))->toBeTrue()
        ->and(HacerCopia::copias($disco))->toHaveCount(2);
});
