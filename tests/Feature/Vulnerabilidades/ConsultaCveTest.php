<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Traer los datos de un CVE: NVD y el catálogo KEV de CISA
|--------------------------------------------------------------------------
|
| La única salida del producto hacia fuera. Aquí no sale nada: las dos
| fuentes se simulan con datos sintéticos y cualquier otra petición revienta.
| Lo que se fija es qué se toma de cada respuesta, que las dos caen por
| separado y que no se guarda nada hasta que alguien registra.
|
*/

const URL_NVD = 'services.nvd.nist.gov/*';
const URL_KEV = 'www.cisa.gov/*';

function respuestaNvd(array $cve): array
{
    return ['totalResults' => 1, 'vulnerabilities' => [['cve' => $cve]]];
}

function cveSintetico(array $extra = []): array
{
    return [
        'id' => 'CVE-2031-10001',
        'published' => '2031-03-02T10:15:00.000',
        'vulnStatus' => 'Analyzed',
        'descriptions' => [
            ['lang' => 'en', 'value' => 'A race condition in the synthetic daemon allows remote code execution. Second sentence.'],
        ],
        'metrics' => [
            'cvssMetricV31' => [
                ['source' => 'cna@example.test', 'type' => 'Secondary', 'cvssData' => ['version' => '3.1', 'vectorString' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 'baseScore' => 9.8]],
                ['source' => 'nvd@nist.gov', 'type' => 'Primary', 'cvssData' => ['version' => '3.1', 'vectorString' => 'CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H', 'baseScore' => 8.1]],
            ],
        ],
        'weaknesses' => [
            ['source' => 'nvd@nist.gov', 'type' => 'Primary', 'description' => [['lang' => 'en', 'value' => 'NVD-CWE-Other'], ['lang' => 'en', 'value' => 'CWE-362']]],
        ],
        'references' => [
            ['url' => 'https://example.test/aviso'],
            ['url' => 'javascript:alert(1)'],
            ['url' => 'https://example.test/aviso'],
            ['url' => 'http://example.test/parche'],
        ],
        ...$extra,
    ];
}

function catalogoKev(array $entradas = []): array
{
    return ['catalogVersion' => '2031.03.10', 'vulnerabilities' => $entradas];
}

beforeEach(function (): void {
    comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    config(['services.nvd.activa' => true, 'services.nvd.clave' => null]);
    Http::preventStrayRequests();

    $this->consultar = fn (string $cve, $quien = null) => $this->actingAs($quien ?? $this->responsable)
        ->postJson('/vulnerabilidades/consulta-cve', ['cve' => $cve]);
});

it('rellena con lo que sabe NVD y marca si está en KEV', function (): void {
    Http::fake([
        URL_NVD => Http::response(respuestaNvd(cveSintetico())),
        URL_KEV => Http::response(catalogoKev([
            ['cveID' => 'CVE-2031-10001', 'vulnerabilityName' => 'Synthetic Daemon Race Condition', 'dateAdded' => '2031-03-05', 'knownRansomwareCampaignUse' => 'Known'],
        ])),
    ]);

    ($this->consultar)('cve-2031-10001')
        ->assertOk()
        ->assertJsonPath('estado', 'encontrado')
        ->assertJsonPath('datos.cve', 'CVE-2031-10001')
        // La puntuación primaria, la de NVD, y no la de quien lo publicó.
        ->assertJsonPath('datos.cvssPuntuacion', '8.1')
        ->assertJsonPath('datos.cvssVector', 'CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H')
        ->assertJsonPath('datos.cwe', 'CWE-362')
        // Sin duplicados y sin nada que no sea http o https.
        ->assertJsonPath('datos.referencias', ['https://example.test/aviso', 'http://example.test/parche'])
        ->assertJsonPath('datos.idioma', 'en')
        ->assertJsonPath('datos.titulo', 'Synthetic Daemon Race Condition')
        ->assertJsonPath('datos.kev.desde', '2031-03-05')
        ->assertJsonPath('datos.kev.ransomware', true)
        ->assertJsonPath('yaRegistrada', null);

    // Sólo sale el identificador.
    Http::assertSent(fn (Request $peticion): bool => str_contains($peticion->url(), 'nvd.nist.gov')
        && $peticion['cveId'] === 'CVE-2031-10001');
    expect(Vulnerabilidad::query()->count())->toBe(0);
});

it('prefiere la descripción en castellano y sugiere el título con su primera frase', function (): void {
    Http::fake([
        URL_NVD => Http::response(respuestaNvd(cveSintetico(['descriptions' => [
            ['lang' => 'en', 'value' => 'English text.'],
            ['lang' => 'es', 'value' => 'Una condición de carrera en el demonio sintético. Segunda frase.'],
        ]]))),
        URL_KEV => Http::response(catalogoKev()),
    ]);

    ($this->consultar)('CVE-2031-10001')
        ->assertJsonPath('datos.idioma', 'es')
        ->assertJsonPath('datos.titulo', 'Una condición de carrera en el demonio sintético')
        ->assertJsonPath('datos.kev', null)
        ->assertJsonPath('datos.kevConsultado', true);
});

it('de la v4 trae el vector y no la puntuación', function (): void {
    Http::fake([
        URL_NVD => Http::response(respuestaNvd(cveSintetico(['metrics' => [
            'cvssMetricV40' => [['type' => 'Secondary', 'cvssData' => ['version' => '4.0', 'vectorString' => 'CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N', 'baseScore' => 9.3]]],
        ]]))),
        URL_KEV => Http::response(catalogoKev()),
    ]);

    ($this->consultar)('CVE-2031-10001')
        ->assertJsonPath('datos.cvssPuntuacion', null)
        ->assertJsonPath('datos.cvssVersion', '4.0');
});

it('si KEV no contesta rellena igual y lo dice', function (): void {
    Http::fake([
        URL_NVD => Http::response(respuestaNvd(cveSintetico())),
        URL_KEV => Http::response('', 503),
    ]);

    ($this->consultar)('CVE-2031-10001')
        ->assertJsonPath('estado', 'encontrado')
        ->assertJsonPath('datos.kev', null)
        ->assertJsonPath('datos.kevConsultado', false);
});

it('dice que NVD no lo conoce sin romper el formulario', function (): void {
    Http::fake([URL_NVD => Http::response(['totalResults' => 0, 'vulnerabilities' => []])]);

    ($this->consultar)('CVE-2031-10002')->assertOk()->assertJsonPath('estado', 'no_encontrado');
});

it('dice que NVD no contesta sin romper el formulario, y no lo recuerda', function (): void {
    Http::fake([URL_NVD => Http::sequence()->push('', 500)->push(respuestaNvd(cveSintetico()))]);
    Http::fake([URL_KEV => Http::response(catalogoKev())]);

    ($this->consultar)('CVE-2031-10001')->assertOk()->assertJsonPath('estado', 'no_disponible');
    // Un fallo no se guarda en caché: la siguiente vuelve a preguntar.
    ($this->consultar)('CVE-2031-10001')->assertOk()->assertJsonPath('estado', 'encontrado');
});

it('apagada en la configuración no sale a ningún sitio', function (): void {
    config(['services.nvd.activa' => false]);
    Http::fake();

    ($this->consultar)('CVE-2031-10001')->assertOk()->assertJsonPath('estado', 'desactivada');

    Http::assertNothingSent();
});

it('avisa si el CVE ya está registrado, salvo que sea la misma que se edita', function (): void {
    Http::fake([URL_NVD => Http::response(respuestaNvd(cveSintetico())), URL_KEV => Http::response(catalogoKev())]);
    $existente = Vulnerabilidad::factory()->create(['cve' => 'CVE-2031-10001']);

    ($this->consultar)('CVE-2031-10001')->assertJsonPath('yaRegistrada.codigo', $existente->codigo);

    $this->actingAs($this->responsable)
        ->postJson('/vulnerabilidades/consulta-cve', ['cve' => 'CVE-2031-10001', 'vulnerabilidad_id' => $existente->id])
        ->assertJsonPath('yaRegistrada', null);
});

it('no pregunta fuera por algo que no tiene forma de CVE', function (): void {
    Http::fake();

    ($this->consultar)('https://evil.test/')->assertUnprocessable()->assertJsonValidationErrors('cve');

    Http::assertNothingSent();
});

it('sólo quien gestiona vulnerabilidades puede consultar', function (): void {
    Http::fake();

    ($this->consultar)('CVE-2031-10001', usuarioCon(Rol::Auditor))->assertForbidden();
});

it('guarda el CWE, las referencias y la procedencia al registrar', function (): void {
    $this->actingAs($this->responsable)->post('/vulnerabilidades', [
        'codigo' => 'VUL-2031-0001',
        'titulo' => 'Condición de carrera en el demonio sintético',
        'cve' => 'cve-2031-10001',
        'cvss_puntuacion' => '8,1',
        'cvss_vector' => 'CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H',
        'cwe' => 'cwe-362',
        'referencias' => "https://example.test/aviso\n\n  http://example.test/parche  \n",
        'kev_desde' => '2026-03-05',
        'nvd_consultado_el' => now()->toDateString(),
        'origen' => 'aviso',
        'fecha_deteccion' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $guardada = Vulnerabilidad::query()->sole();

    expect($guardada->cwe)->toBe('CWE-362')
        ->and($guardada->referencias)->toBe(['https://example.test/aviso', 'http://example.test/parche'])
        ->and($guardada->kev_desde?->toDateString())->toBe('2026-03-05')
        ->and($guardada->nvd_consultado_el?->toDateString())->toBe(now()->toDateString());
});

it('rechaza una referencia que no sea http o https', function (): void {
    $this->actingAs($this->responsable)->post('/vulnerabilidades', [
        'codigo' => 'VUL-2031-0002',
        'titulo' => 'Sintética',
        'severidad' => 'media',
        'referencias' => "https://example.test/bien\njavascript:alert(1)",
        'origen' => 'interna',
        'fecha_deteccion' => now()->toDateString(),
    ])->assertSessionHasErrors('referencias.1');
});

/*
| Las referencias llegan una por fila (`CampoLista`, `referencias[]`). La lista
| vacía viaja como un `referencias[]` en blanco para que vaciarla en una edición
| la vacíe, y el middleware lo convierte en nulo: sin filtrarlo, la validación de
| cada URL fallaba sobre un hueco que nadie escribió.
*/
it('admite las referencias en lista y vaciarlas en una edición', function (): void {
    $datos = [
        'codigo' => 'VUL-2031-0004',
        'titulo' => 'Sintética con lista',
        'severidad' => 'media',
        'origen' => 'interna',
        'fecha_deteccion' => now()->toDateString(),
    ];

    $this->actingAs($this->responsable)->post('/vulnerabilidades', [
        ...$datos,
        'referencias' => ['https://example.test/aviso', '  http://example.test/parche  '],
    ])->assertSessionHasNoErrors();

    $guardada = Vulnerabilidad::query()->sole();
    expect($guardada->referencias)->toBe(['https://example.test/aviso', 'http://example.test/parche']);

    $this->actingAs($this->responsable)
        ->put("/vulnerabilidades/{$guardada->id}", [...$datos, 'referencias' => ['']])
        ->assertSessionHasNoErrors();

    expect($guardada->refresh()->referencias)->toBeNull();
});

it('sin CVE no guarda procedencia', function (): void {
    $this->actingAs($this->responsable)->post('/vulnerabilidades', [
        'codigo' => 'VUL-2031-0003',
        'titulo' => 'Sintética sin CVE',
        'severidad' => 'media',
        'kev_desde' => '2026-03-05',
        'nvd_consultado_el' => now()->toDateString(),
        'origen' => 'interna',
        'fecha_deteccion' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(Vulnerabilidad::query()->sole())
        ->kev_desde->toBeNull()
        ->nvd_consultado_el->toBeNull();
});
