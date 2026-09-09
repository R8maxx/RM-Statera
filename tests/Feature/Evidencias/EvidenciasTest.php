<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\Concerns\SeleccionVacia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Evidencias
|--------------------------------------------------------------------------
|
| Invariante 6: evidencia ↔ requisito es N:M. Una captura prueba un control de
| ISO y tres medidas del ENS, se registra UNA vez y cuenta en todos. Es el
| problema que el producto resuelve, así que es lo que más se prueba aquí.
|
| Nada de ficheros reales: `Storage::fake` y contenido sintético.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->ens = Marco::factory()->create(['codigo' => 'ENS-SINTETICO']);
    $this->iso = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);

    $this->sistemaEns = Sistema::factory()->de($this->organizacion)->conMarco($this->ens)->create(['codigo' => 'SIS-01']);
    $this->sistemaIso = Sistema::factory()->de($this->organizacion)->conMarco($this->iso)->create(['codigo' => 'SGSI-01']);

    $this->implantacion = function (Marco $marco, Sistema $sistema, string $codigo): Implantacion {
        $requisito = Requisito::factory()->conCodigo($codigo)->create(['marco_id' => $marco->id]);

        return Implantacion::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'sistema_id' => $sistema->id,
            'requisito_id' => $requisito->id,
        ]);
    };

    /** @param array<string, mixed> $campos */
    $this->datos = fn (array $campos = []): array => [
        'titulo' => 'Captura del panel del IdP',
        'tipo' => TipoEvidencia::Captura->value,
        'url_externa' => 'https://interno.ejemplo/panel',
        'fecha_obtencion' => Carbon::today()->toDateString(),
        ...$campos,
    ];
});

it('registra una evidencia con enlace', function (): void {
    $this->actingAs($this->usuario)
        ->post('/evidencias', ($this->datos)())
        ->assertSessionHasNoErrors();

    $evidencia = Evidencia::query()->sole();

    expect($evidencia->titulo)->toBe('Captura del panel del IdP')
        ->and($evidencia->organizacion_id)->toBe($this->organizacion->id)
        ->and($evidencia->esFichero())->toBeFalse();
});

it('guarda el fichero con su huella SHA-256', function (): void {
    Storage::fake('evidencias');

    $fichero = UploadedFile::fake()->createWithContent('captura.png', 'contenido sintetico');

    $this->actingAs($this->usuario)
        ->post('/evidencias', ($this->datos)(['url_externa' => null, 'fichero' => $fichero]))
        ->assertSessionHasNoErrors();

    $evidencia = Evidencia::query()->sole();

    expect($evidencia->esFichero())->toBeTrue()
        ->and($evidencia->nombre_fichero)->toBe('captura.png')
        // La huella es de lo que se recibió, no de lo que quedó en el bucket.
        ->and($evidencia->hash_sha256)->toBe(hash('sha256', 'contenido sintetico'))
        ->and($evidencia->disco)->toBe('evidencias');

    Storage::disk('evidencias')->assertExists((string) $evidencia->ruta);
});

it('exige fichero o enlace, y no admite quedarse sin ninguno', function (): void {
    $this->actingAs($this->usuario)
        ->post('/evidencias', ($this->datos)(['url_externa' => null]))
        ->assertSessionHasErrors(['fichero', 'url_externa']);

    expect(Evidencia::query()->count())->toBe(0);
});

it('no admite una evidencia obtenida en el futuro', function (): void {
    $this->actingAs($this->usuario)
        ->post('/evidencias', ($this->datos)(['fecha_obtencion' => Carbon::tomorrow()->toDateString()]))
        ->assertSessionHasErrors('fecha_obtencion');
});

it('deriva la caducidad de la periodicidad cuando no se pone fecha', function (): void {
    $this->actingAs($this->usuario)
        ->post('/evidencias', ($this->datos)([
            'fecha_obtencion' => '2026-01-15',
            'periodicidad_renovacion' => PeriodicidadRenovacion::Anual->value,
        ]))
        ->assertSessionHasNoErrors();

    expect(Evidencia::query()->sole()->fecha_caducidad?->toDateString())->toBe('2027-01-15');
});

it('una fecha escrita a mano manda sobre la derivada', function (): void {
    $this->actingAs($this->usuario)
        ->post('/evidencias', ($this->datos)([
            'fecha_obtencion' => '2026-01-15',
            'fecha_caducidad' => '2026-06-30',
            'periodicidad_renovacion' => PeriodicidadRenovacion::Anual->value,
        ]))
        ->assertSessionHasNoErrors();

    expect(Evidencia::query()->sole()->fecha_caducidad?->toDateString())->toBe('2026-06-30');
});

it('el centinela de «ninguno» de los desplegables llega como nulo', function (): void {
    $this->actingAs($this->usuario)
        ->post('/evidencias', ($this->datos)([
            'responsable_id' => SeleccionVacia::VALOR,
            'periodicidad_renovacion' => SeleccionVacia::VALOR,
        ]))
        ->assertSessionHasNoErrors();

    $evidencia = Evidencia::query()->sole();

    expect($evidencia->responsable_id)->toBeNull()
        ->and($evidencia->periodicidad_renovacion)->toBeNull();
});

it('la misma evidencia prueba un requisito de ISO y otro del ENS a la vez', function (): void {
    $medidaEns = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $controlIso = ($this->implantacion)($this->iso, $this->sistemaIso, 'A.8.5');

    $evidencia = Evidencia::factory()->create(['organizacion_id' => $this->organizacion->id]);

    foreach ([$medidaEns, $controlIso] as $implantacion) {
        $this->actingAs($this->usuario)
            ->post("/implantaciones/{$implantacion->id}/evidencias", [
                'evidencia_id' => $evidencia->id,
                'nota' => 'Prueba el mecanismo de autenticación.',
            ])
            ->assertSessionHasNoErrors();
    }

    // Registrada una vez, contada en los dos marcos. Ése es el invariante 6.
    expect($evidencia->implantaciones()->count())->toBe(2);

    $this->actingAs($this->usuario)
        ->get("/evidencias/{$evidencia->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('evidencias/Ficha')
            ->has('vinculos', 2)
            ->has('marcos', 2)
        );
});

it('vincular dos veces la misma evidencia no falla: actualiza la nota', function (): void {
    $implantacion = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $evidencia = Evidencia::factory()->create(['organizacion_id' => $this->organizacion->id]);

    foreach (['Primera nota', 'Nota corregida'] as $nota) {
        $this->actingAs($this->usuario)
            ->post("/implantaciones/{$implantacion->id}/evidencias", [
                'evidencia_id' => $evidencia->id,
                'nota' => $nota,
            ])
            ->assertSessionHasNoErrors();
    }

    expect($evidencia->implantaciones()->count())->toBe(1)
        ->and($evidencia->implantaciones()->first()?->getRelationValue('pivot')?->getAttribute('nota'))
        ->toBe('Nota corregida');
});

it('desvincular deja la evidencia en el repositorio', function (): void {
    $implantacion = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $evidencia = Evidencia::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)
        ->post("/implantaciones/{$implantacion->id}/evidencias", ['evidencia_id' => $evidencia->id]);

    $this->actingAs($this->usuario)
        ->delete("/implantaciones/{$implantacion->id}/evidencias/{$evidencia->id}")
        ->assertSessionHasNoErrors();

    expect($evidencia->implantaciones()->count())->toBe(0)
        ->and(Evidencia::query()->whereKey($evidencia->id)->exists())->toBeTrue();
});

it('la ficha del requisito enseña sus pruebas y las candidatas sólo si se piden', function (): void {
    $implantacion = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $vinculada = Evidencia::factory()->create(['organizacion_id' => $this->organizacion->id]);
    $sinVincular = Evidencia::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)
        ->post("/implantaciones/{$implantacion->id}/evidencias", ['evidencia_id' => $vinculada->id]);

    $this->actingAs($this->usuario)
        ->get("/implantaciones/{$implantacion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('evidencias', 1)
            // Prop opcional: no viaja hasta que el diálogo la pide.
            ->missing('evidenciasDisponibles')
        );

    // Una recarga parcial responde JSON, no la vista con el `page`, así que
    // `assertInertia` no vale aquí: se afirma sobre el propio documento.
    $this->actingAs($this->usuario)
        ->get(
            "/implantaciones/{$implantacion->id}",
            recargaParcial('implantaciones/Ficha', ['evidenciasDisponibles']),
        )
        ->assertOk()
        // La ya vinculada no se vuelve a ofrecer.
        ->assertJsonCount(1, 'props.evidenciasDisponibles')
        ->assertJsonPath('props.evidenciasDisponibles.0.valor', (string) $sinVincular->id);
});

it('cuenta las caducadas y los implantados sin prueba en el panel', function (): void {
    $implantacion = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $implantacion->update(['estado' => EstadoImplantacion::Implantado->value]);

    Evidencia::factory()->caducada()->create(['organizacion_id' => $this->organizacion->id]);
    Evidencia::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'fecha_caducidad' => Carbon::today()->addDays(10),
    ]);

    $this->actingAs($this->usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('evidencias.total', 2)
            ->where('evidencias.caducadas', 1)
            ->where('evidencias.porCaducar', 1)
            // Implantado y sin ninguna prueba detrás: el hallazgo esperando.
            ->where('evidencias.implantadasSinEvidencia', 1)
        );
});

it('no ve, ni vincula, ni borra evidencias de otra organización', function (): void {
    $implantacion = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');

    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);
    $ajena = Evidencia::factory()->create(['organizacion_id' => $otra->id]);
    comoOrganizacion($this->organizacion);

    // 404 y no 403: «existe pero no es tuya» ya sería filtrar información.
    $this->actingAs($this->usuario)->get("/evidencias/{$ajena->id}")->assertNotFound();
    $this->actingAs($this->usuario)->delete("/evidencias/{$ajena->id}")->assertNotFound();

    // Aquí no llega ni a 404: la política de RLS deja la fila fuera de la
    // consulta de la regla `exists`, así que falla la validación diciendo que no
    // existe. Desde esta organización es literalmente cierto.
    $this->actingAs($this->usuario)
        ->post("/implantaciones/{$implantacion->id}/evidencias", ['evidencia_id' => $ajena->id])
        ->assertSessionHasErrors('evidencia_id');

    comoOrganizacion($otra);
    expect($ajena->fresh()?->implantaciones()->count())->toBe(0);
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->get('/evidencias')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0));
});

it('borrar una evidencia avisa de cuántos requisitos se quedan sin prueba', function (): void {
    $implantacion = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $evidencia = Evidencia::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)
        ->post("/implantaciones/{$implantacion->id}/evidencias", ['evidencia_id' => $evidencia->id]);

    $this->actingAs($this->usuario)
        ->delete("/evidencias/{$evidencia->id}")
        ->assertRedirect('/evidencias');

    $this->actingAs($this->usuario)
        ->get('/evidencias')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->hasFlash('exito'));

    expect(Evidencia::query()->count())->toBe(0)
        ->and($implantacion->evidencias()->count())->toBe(0);
});

it('editar no toca el fichero ni su huella', function (): void {
    Storage::fake('evidencias');

    $evidencia = Evidencia::factory()->conFichero()->create(['organizacion_id' => $this->organizacion->id]);
    $huella = $evidencia->hash_sha256;

    $this->actingAs($this->usuario)
        ->put("/evidencias/{$evidencia->id}", [
            'titulo' => 'Título corregido',
            'tipo' => TipoEvidencia::Informe->value,
            'fecha_obtencion' => $evidencia->fecha_obtencion->toDateString(),
            'hash_sha256' => 'intento de sustituir la huella',
            'ruta' => 'otra/ruta.png',
        ])
        ->assertSessionHasNoErrors();

    $evidencia->refresh();

    expect($evidencia->titulo)->toBe('Título corregido')
        ->and($evidencia->hash_sha256)->toBe($huella)
        ->and($evidencia->ruta)->not->toBe('otra/ruta.png');
});

it('exige sesión iniciada', function (): void {
    $this->get('/evidencias')->assertRedirect('/login');
    $this->post('/evidencias', [])->assertRedirect('/login');
});
