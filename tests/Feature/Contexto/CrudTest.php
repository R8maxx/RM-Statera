<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Contexto\VincularImplantacionARequisito;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Facades\DB;

/**
 * El módulo por la interfaz: quién puede hacer qué y qué ve cada rol.
 *
 * Lo que se clava aquí es el reparto de los **tres** permisos —`contexto.aprobar`
 * es el sexto verbo de supervisión y el técnico no lo tiene— y el aislamiento,
 * que responde **404 y nunca 403**: decir «existe pero no es tuyo» ya sería
 * filtrar información.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('el panorama del contexto se abre con la matriz y el alcance', function (): void {
    $this->actingAs($this->usuario)
        ->get('/contexto')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('contexto/Index')
            ->has('dafo')
            ->has('ejes.ambitos', 2)
            ->has('alcance'));
});

it('el panorama marca las adversas sin riesgo y dice qué le falta al borrador', function (): void {
    $amenaza = CuestionContexto::factory()->deTipo(TipoCuestion::Amenaza)->create();
    $fortaleza = CuestionContexto::factory()->deTipo(TipoCuestion::Fortaleza)->create();

    $poca = ParteInteresada::factory()->create(['codigo' => 'PI-01']);
    RequisitoInteresado::factory()->for($poca, 'parteInteresada')->create();
    $mucha = ParteInteresada::factory()->create(['codigo' => 'PI-02']);
    RequisitoInteresado::factory()->for($mucha, 'parteInteresada')->legal()->count(2)->create();

    $this->actingAs($this->usuario)
        ->get('/contexto')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('contexto/Index')
            ->where('dafo.amenaza.0.id', $amenaza->id)
            ->where('dafo.amenaza.0.sinRiesgo', true)
            ->where('dafo.fortaleza.0.id', $fortaleza->id)
            ->where('dafo.fortaleza.0.sinRiesgo', false)
            ->where('comprobaciones.0.clave', 'clima')
            ->where('comprobaciones.0.cumplida', false)
            ->where('comprobaciones.1.cumplida', true)
            // Primero a quien más se le debe, no el primer código.
            ->where('partes.0.codigo', 'PI-02')
            ->where('partes.0.obligan', 2)
            ->where('partes.1.obligan', 0));
});

it('la tabla de cuestiones trae sus indicadores', function (): void {
    CuestionContexto::factory()->deTipo(TipoCuestion::Amenaza)->create();

    $this->actingAs($this->usuario)
        ->get('/contexto/cuestiones')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('contexto/Cuestiones')
            ->has('filas', 1)
            ->has('pendientes'));
});

it('registrar una cuestión la deja en el borrador abierto', function (): void {
    $this->actingAs($this->usuario)
        ->post('/contexto/cuestiones', [
            'codigo' => 'CTX-01',
            'tipo' => TipoCuestion::Amenaza->value,
            'materia' => 'legal_regulatorio',
            'titulo' => 'Los pliegos empiezan a exigir categoría media',
            'es_climatica' => false,
        ])
        ->assertRedirect();

    $cuestion = CuestionContexto::query()->where('codigo', 'CTX-01')->firstOrFail();

    expect($cuestion->analisis_alta_id)->not->toBeNull()
        ->and($cuestion->analisis_baja_id)->toBeNull()
        ->and($cuestion->estaVigente())->toBeTrue();
});

it('no admite dos cuestiones con el mismo código en la organización', function (): void {
    CuestionContexto::factory()->create(['codigo' => 'CTX-01']);

    $this->actingAs($this->usuario)
        ->post('/contexto/cuestiones', [
            'codigo' => 'CTX-01',
            'tipo' => TipoCuestion::Fortaleza->value,
            'materia' => 'organizativo',
            'titulo' => 'Otra cosa',
        ])
        ->assertSessionHasErrors('codigo');
});

/*
 * Retirar exige motivo, igual que descartar una tarea o anular una no
 * conformidad: es lo que explicará, en la revisión siguiente, por qué el DAFO
 * tiene una cuestión menos.
 */
it('retirar una cuestión exige motivo y la deja en el registro', function (): void {
    $cuestion = CuestionContexto::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/contexto/cuestiones/{$cuestion->id}/retirada", ['motivo' => ''])
        ->assertSessionHasErrors('motivo');

    $this->actingAs($this->usuario)
        ->post("/contexto/cuestiones/{$cuestion->id}/retirada", [
            'motivo' => 'El proveedor migró el parque y la homogeneidad ya no se sostiene.',
        ])
        ->assertRedirect();

    $cuestion->refresh();

    expect($cuestion->exists)->toBeTrue()
        ->and($cuestion->estaVigente())->toBeFalse()
        ->and($cuestion->motivo_baja)->not->toBeNull();
});

it('una parte interesada se da de alta con sus requisitos aparte', function (): void {
    $this->actingAs($this->usuario)
        ->post('/partes-interesadas', [
            'codigo' => 'PI-01',
            'nombre' => 'Centro Criptológico Nacional',
            'tipo' => 'regulador',
            'ambito' => 'externo',
        ])
        ->assertRedirect();

    $parte = ParteInteresada::query()->where('codigo', 'PI-01')->firstOrFail();

    expect($parte->requisitos()->count())->toBe(0);

    $this->actingAs($this->usuario)
        ->put("/partes-interesadas/{$parte->id}/requisitos", [
            'requisitos' => [
                [
                    'descripcion' => 'Declarar la conformidad y publicar el distintivo.',
                    'naturaleza' => NaturalezaRequisito::Legal->value,
                    'referencia' => 'RD 311/2022, art. 38',
                    'es_climatico' => false,
                ],
            ],
        ])
        ->assertRedirect();

    expect($parte->requisitos()->count())->toBe(1)
        ->and($parte->requisitos()->first()?->naturaleza)->toBe(NaturalezaRequisito::Legal);
});

/*
 * La lista se guarda entera: lo que desaparece de ella se borra. Es lo que
 * distingue un requisito —una línea de la ficha de su parte— de una cuestión del
 * DAFO, que es un juicio fechado y se retira con motivo.
 */
/*
 * Tres escalones y no dos: una obligación atada a una medida sin iniciar tiene
 * medida, pero no está cumplida. Y la SoA sólo cita controles de ISO, así que una
 * medida del ENS atada no sale como «exigido por».
 */
it('la ficha de una parte cuenta su cobertura y dice dónde se nota', function (): void {
    $parte = ParteInteresada::factory()->create(['codigo' => 'PI-01']);
    $legal = RequisitoInteresado::factory()->for($parte, 'parteInteresada')->legal()->create();
    $contractual = RequisitoInteresado::factory()->for($parte, 'parteInteresada')
        ->deNaturaleza(NaturalezaRequisito::Contractual)->create();
    $cubierto = RequisitoInteresado::factory()->for($parte, 'parteInteresada')
        ->deNaturaleza(NaturalezaRequisito::Contractual)->create();
    RequisitoInteresado::factory()->for($parte, 'parteInteresada')->create();

    $medidaEns = Implantacion::factory()
        ->for(Requisito::factory()->conCodigo('mp.com.1'), 'requisito')
        ->create();
    $controlIso = Implantacion::factory()
        ->for(Requisito::factory()->control()->conCodigo('A.5.24'), 'requisito')
        ->enEstado(EstadoImplantacion::Implantado)
        ->create();

    $vinculos = app(VincularImplantacionARequisito::class);
    $vinculos->vincular($legal, $medidaEns, $this->usuario);
    $vinculos->vincular($cubierto, $controlIso, $this->usuario);

    $this->actingAs($this->usuario)
        ->get("/partes-interesadas/{$parte->id}")
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('partes-interesadas/Ficha')
            ->where('cobertura.obligan', 3)
            ->where('cobertura.legales', 1)
            ->where('cobertura.contractuales', 2)
            ->where('cobertura.conMedida', 2)
            ->where('cobertura.conMedidaImplantada', 1)
            ->where('cobertura.sinMedida', 1)
            ->where('cobertura.pendientesDeImplantar', ['mp.com.1'])
            ->where('cobertura.citadasEnSoa', ['A.5.24'])
            ->where('cobertura.medidasEnsAtadas', ['mp.com.1'])
            ->where('cobertura.partesConObligacionSinCubrir', 1)
            ->where('cobertura.cuentaEnElIndicador', true)
            ->where('requisitos', fn ($requisitos): bool => collect($requisitos)
                ->firstWhere('id', $contractual->id)['sinCubrir'] === true
                && collect($requisitos)->where('sinCubrir', true)->count() === 1));
});

it('guardar la lista de requisitos borra lo que ya no está', function (): void {
    $parte = ParteInteresada::factory()->create();
    $requisito = RequisitoInteresado::factory()->for($parte, 'parteInteresada')->create();

    $this->actingAs($this->usuario)
        ->put("/partes-interesadas/{$parte->id}/requisitos", ['requisitos' => []])
        ->assertRedirect();

    expect(RequisitoInteresado::query()->whereKey($requisito->id)->exists())->toBeFalse();
});

// --- El reparto de los tres permisos ---------------------------------------

it('el técnico escribe el contexto y no lo aprueba', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)->get('/contexto')->assertOk();

    $this->actingAs($tecnico)
        ->post('/contexto/cuestiones', [
            'codigo' => 'CTX-01',
            'tipo' => TipoCuestion::Fortaleza->value,
            'materia' => 'organizativo',
            'titulo' => 'Dirección implicada',
        ])
        ->assertRedirect();

    $analisis = CuestionContexto::query()->where('codigo', 'CTX-01')->firstOrFail()->analisis_alta_id;

    $this->actingAs($tecnico)
        ->post("/contexto/analisis/{$analisis}/aprobacion")
        ->assertForbidden();
});

it('el auditor mira y no toca', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)->get('/contexto')->assertOk();
    $this->actingAs($auditor)->get('/partes-interesadas')->assertOk();

    $this->actingAs($auditor)
        ->post('/contexto/cuestiones', [
            'codigo' => 'CTX-01',
            'tipo' => TipoCuestion::Fortaleza->value,
            'materia' => 'organizativo',
            'titulo' => 'Lo que sea',
        ])
        ->assertForbidden();
});

// --- Aislamiento ------------------------------------------------------------

it('lo de otra organización da 404 y no 403', function (): void {
    $ajena = comoOrganizacion();
    $cuestion = CuestionContexto::factory()->create();
    $parte = ParteInteresada::factory()->create();

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)->get("/contexto/cuestiones/{$cuestion->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/partes-interesadas/{$parte->id}")->assertNotFound();

    expect($ajena->id)->not->toBe($this->organizacion->id)
        // Y RLS por debajo del scope: la capa que un `withoutGlobalScopes()`
        // se saltaría sigue devolviendo cero.
        ->and(DB::table('cuestiones_contexto')->count())->toBe(0);
});

it('no acepta de responsable la cuenta de otra organización', function (): void {
    // `users` no tiene RLS: un `exists:users,id` a secas aceptaba este id.
    $ajeno = usuarioCon(organizacion: Organizacion::factory()->create());

    $this->actingAs($this->usuario)
        ->post('/partes-interesadas', [
            'codigo' => 'PI-09',
            'nombre' => 'Centro Criptológico Nacional',
            'tipo' => 'regulador',
            'ambito' => 'externo',
            'responsable_id' => $ajeno->id,
        ])
        ->assertSessionHasErrors('responsable_id');

    expect(ParteInteresada::query()->where('codigo', 'PI-09')->exists())->toBeFalse();
});
