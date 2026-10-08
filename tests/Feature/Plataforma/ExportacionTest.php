<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\Models\Persona;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\Exportacion\ModelosDelCliente;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use App\Domain\Plataforma\Models\FichaComercial;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Exportar todos los datos de un cliente (punto 56)
|--------------------------------------------------------------------------
*/

function exportador(PerfilPlataforma $perfil = PerfilPlataforma::Administracion): User
{
    $cuenta = User::factory()->plataforma()->create(['perfil_plataforma' => $perfil->value]);
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

/** @return array<string, string> el contenido del ZIP, por nombre de entrada */
function contenidoDelZip(ExportacionOrganizacion $exportacion): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk('adjuntos')->path((string) $exportacion->ruta));

    $entradas = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nombre = (string) $zip->getNameIndex($i);
        $entradas[$nombre] = (string) $zip->getFromIndex($i);
    }

    $zip->close();

    return $entradas;
}

beforeEach(function (): void {
    foreach (['adjuntos', 'evidencias', 'documentos'] as $disco) {
        Storage::fake($disco);
    }

    $this->una = comoOrganizacion();
    Sistema::factory()->create(['nombre' => 'Sistema de la una']);
    Persona::factory()->create(['nombre_pila' => 'Lucía', 'apellido1' => 'Exportada', 'nif' => '12345678Z']);
    usuarioCon();
    Storage::disk('evidencias')->put("{$this->una->id}/2026/acta.pdf", 'contenido del acta');

    $this->otra = comoOrganizacion();
    Sistema::factory()->create(['nombre' => 'Sistema de la otra']);
    Storage::disk('evidencias')->put("{$this->otra->id}/2026/ajena.pdf", 'no debe salir');
    sinOrganizacion();
});

function exportarLaUna(object $test): ExportacionOrganizacion
{
    $test->actingAs(exportador())->post("/plataforma/organizaciones/{$test->una->id}/exportaciones")->assertRedirect();

    return ExportacionOrganizacion::query()->latest('id')->firstOrFail();
}

it('descubre todos los modelos con organización, sin enumerarlos', function (): void {
    $modelos = app(ModelosDelCliente::class)->todos();

    expect($modelos)->toContain(Sistema::class)
        ->toContain(Persona::class)
        ->toContain(EventoAuditoria::class)
        ->not->toContain(FichaComercial::class);
});

it('deja un ZIP con un NDJSON por modelo, sus ficheros y el manifiesto', function (): void {
    $exportacion = exportarLaUna($this);

    expect($exportacion->estado)->toBe(EstadoExportacion::Lista)
        ->and($exportacion->huella)->not->toBeNull();

    $zip = contenidoDelZip($exportacion);

    expect($zip)->toHaveKeys(['manifiesto.json', 'datos/organizacion.json', 'datos/sistema.ndjson', 'datos/persona.ndjson', 'datos/cuentas.ndjson'])
        ->and($zip["ficheros/evidencias/{$this->una->id}/2026/acta.pdf"] ?? null)->toBe('contenido del acta');

    $manifiesto = json_decode($zip['manifiesto.json'], true);
    expect($manifiesto['recuentos']['Sistema'])->toBe(1)
        ->and($manifiesto['recuentos']['Cuentas'])->toBe(1)
        ->and($manifiesto['recuentos']['Ficheros'])->toBe(1)
        ->and($manifiesto['huellas']["ficheros/evidencias/{$this->una->id}/2026/acta.pdf"])->toBe(hash('sha256', 'contenido del acta'));
});

it('los datos personales salen en claro y no sale ningún secreto', function (): void {
    $zip = contenidoDelZip(exportarLaUna($this));

    expect($zip['datos/persona.ndjson'])->toContain('12345678Z')
        ->and($zip['datos/cuentas.ndjson'])->not->toContain('password')
        ->not->toContain('two_factor')
        ->not->toContain('remember_token');
});

it('no aparece nada de otra organización', function (): void {
    $zip = contenidoDelZip(exportarLaUna($this));
    $todo = implode("\n", array_keys($zip))."\n".implode("\n", $zip);

    expect($todo)->not->toContain('Sistema de la otra')
        ->not->toContain('no debe salir')
        ->not->toContain("evidencias/{$this->otra->id}/");
});

it('la descarga queda en la traza y las caducadas se borran', function (): void {
    $exportacion = exportarLaUna($this);
    $admin = exportador();

    $this->actingAs($admin)->get("/plataforma/exportaciones/{$exportacion->id}/descargar")->assertOk();
    expect(DB::table('eventos_plataforma')->where('accion', 'exportacion_descargada')->exists())->toBeTrue();

    $this->travel(8)->days();
    $this->artisan('exportaciones:borrar-caducadas')->assertSuccessful();

    expect($exportacion->fresh()?->estado)->toBe(EstadoExportacion::Caducada)
        ->and(Storage::disk('adjuntos')->exists((string) $exportacion->ruta))->toBeFalse();
});

it('gestión comercial no exporta ni descarga', function (): void {
    $exportacion = exportarLaUna($this);
    $comercial = exportador(PerfilPlataforma::Comercial);

    $this->actingAs($comercial)->post("/plataforma/organizaciones/{$this->una->id}/exportaciones")->assertForbidden();
    $this->actingAs($comercial)->get("/plataforma/exportaciones/{$exportacion->id}/descargar")->assertForbidden();
});

it('no deja contexto puesto tras exportar', function (): void {
    exportarLaUna($this);

    expect(app(ContextoOrganizacion::class)->hayContexto())->toBeFalse();
});
