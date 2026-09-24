<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\InformeEstadoSeguridad;
use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Cuerpo\HtmlDocumento;
use App\Domain\Documento\Cuerpo\MaterializarCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Sistema\Models\Sistema;
use Inertia\Testing\AssertableInertia;

/**
 * Qué dice exactamente el informe de estado (§ 4.18).
 *
 * Lo que se clava es la promesa que llevaban escrita cinco clases del dominio:
 * **las cifras del informe son las del panel**, contadas por las mismas consultas.
 * Si el informe entregado dijera una cosa y el panel abierto al lado otra, el
 * documento se desmentiría solo.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $marco = Marco::factory()->create(['codigo' => 'ENS-PRUEBA', 'nombre' => 'Esquema de prueba']);
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create(['codigo' => 'SIS-EST']);

    foreach (['implantado', 'implantado', 'en_progreso', 'no_iniciado'] as $orden => $estado) {
        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => 'est.'.$orden,
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        Implantacion::factory()->for($this->sistema)->create(['requisito_id' => $requisito->id, 'estado' => $estado]);
    }

    // Excluida: no se le exige al sistema y no cuenta en ninguna cifra.
    $excluido = Requisito::factory()->create(['marco_id' => $marco->id, 'codigo' => 'est.x', 'tipo' => TipoRequisito::Medida->value, 'orden' => 9]);
    Implantacion::factory()->for($this->sistema)->create([
        'requisito_id' => $excluido->id,
        'estado' => 'no_aplica',
        'aplica' => false,
        'justificacion' => 'Fuera del alcance.',
    ]);

    $this->contenido = function (): ContenidoDocumento {
        $documento = Documento::query()->where('tipo', TipoDocumento::InformeEstado->value)->first()
            ?? Documento::factory()->informeEstado()->create();

        $version = $documento->versiones()->whereNull('numero')->first()
            ?? DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(InformeEstadoSeguridad::class)->construir($documento->fresh(), $version);
    };
});

it('cuenta el cumplimiento sobre lo exigible, sin lo excluido', function (): void {
    $cumplimiento = ($this->contenido)()->extras['cumplimiento'];
    $sistema = collect($cumplimiento['sistemas'])->firstWhere('codigo', 'SIS-EST');

    expect($sistema['aplicables'])->toBe(4)
        ->and($sistema['implantadas'])->toBe(2)
        ->and($cumplimiento['pendientes'])->toBe(2)
        ->and(collect($cumplimiento['estados'])->firstWhere('clave', 'implantado')['valor'])->toBe(2);
});

it('dice lo mismo que el panel', function (): void {
    $cumplimiento = ($this->contenido)()->extras['cumplimiento'];

    $this->actingAs($this->usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('resumen.aplicables', array_sum(array_column($cumplimiento['sistemas'], 'aplicables')))
            ->where('resumen.implantadas', array_sum(array_column($cumplimiento['sistemas'], 'implantadas')))
            ->where('resumen.pendientes', $cumplimiento['pendientes'])
            ->where('evidencias.implantadasSinEvidencia', $cumplimiento['implantadasSinEvidencia']));
});

it('recoge los registros del sistema de gestión, con lo que pide acción marcado', function (): void {
    NoConformidad::factory()->vencida()->create();

    $registros = collect(($this->contenido)()->extras['registros'])->keyBy('titulo');
    $noConformidades = $registros['No conformidades'];
    $vencidas = collect($noConformidades['indicadores'])->firstWhere('etiqueta', 'Fuera de plazo');

    expect($registros->keys()->all())->toContain('Riesgos', 'Incidentes', 'Auditorías', 'Continuidad')
        ->and($noConformidades['total'])->toBe(1)
        ->and($vencidas['valor'])->toBe(1)
        ->and($vencidas['alerta'])->toBeTrue();
});

it('declara que no es el INES y que sus cifras son las del día', function (): void {
    $limitaciones = implode("\n", ($this->contenido)()->limitaciones);

    expect($limitaciones)->toContain('no es el Informe Nacional del Estado de Seguridad (INES)')
        ->and($limitaciones)->toContain('en la fecha de extracción')
        ->and($limitaciones)->toContain('no compara con el anterior');
});

it('el cuerpo materializado lleva las cifras con su denominador y cada registro', function (): void {
    $cuerpo = app(MaterializarCuerpo::class)(
        CuerpoDeFabrica::para(TipoDocumento::InformeEstado),
        ($this->contenido)(),
        TipoDocumento::InformeEstado,
    );

    $texto = json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($texto)->toContain('2 de 4')
        ->and($texto)->toContain('50 %')
        ->and($texto)->toContain('SIS-EST')
        ->and($texto)->toContain('No conformidades')
        ->and($texto)->toContain('Continuidad');
});

it('el HTML del documento se pinta entero, con cada registro', function (): void {
    $contenido = ($this->contenido)();
    $html = app(HtmlDocumento::class)(Documento::query()->where('tipo', TipoDocumento::InformeEstado->value)->sole(), $contenido);

    expect($html)->toContain('El sistema de gestión, registro a registro')
        ->and($html)->toContain('SIS-EST')
        ->and($html)->toContain('Oportunidades de mejora')
        ->and($html)->toContain('Limitaciones de este informe');
});

it('es de la organización entera: se crea sin sistema', function (): void {
    $documento = Documento::factory()->informeEstado()->create();

    expect($documento->sistema_id)->toBeNull()
        ->and(TipoDocumento::InformeEstado->exigeSistema())->toBeFalse();
});
