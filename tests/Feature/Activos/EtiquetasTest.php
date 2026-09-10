<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\GeneradorEtiquetas;
use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Las etiquetas QR
|--------------------------------------------------------------------------
|
| Dos cosas que sólo se descubren pegando trescientas pegatinas: que no salga
| etiqueta para lo que no tiene carcasa, y que el QR apunte a donde debe. Una
| etiqueta impresa dura años; reimprimir el parque porque el código apuntaba a
| localhost no es un error recuperable con un despliegue.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->generador = app(GeneradorEtiquetas::class);
});

it('codifica la URL de la ficha, no el código en texto plano', function (): void {
    $this->organizacion->update(['url_base_etiquetas' => 'https://statera.ejemplo']);

    $activo = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->create();

    expect($this->generador->contenido($activo, $this->organizacion->fresh()))
        ->toBe("https://statera.ejemplo/activos/{$activo->id}");
});

it('cae a la URL de la aplicación cuando la organización no declara ninguna', function (): void {
    config(['app.url' => 'https://statera.local']);

    $activo = Activo::factory()->de($this->organizacion)->create();

    expect($this->generador->contenido($activo, $this->organizacion))
        ->toBe("https://statera.local/activos/{$activo->id}");
});

it('no deja una barra doble cuando la base termina en barra', function (): void {
    $this->organizacion->update(['url_base_etiquetas' => 'https://statera.ejemplo/']);

    $activo = Activo::factory()->de($this->organizacion)->create();

    expect($this->generador->contenido($activo, $this->organizacion->fresh()))
        ->toBe("https://statera.ejemplo/activos/{$activo->id}");
});

it('genera un SVG incrustable, sin declaración XML', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create();

    $svg = $this->generador->svg($activo, $this->organizacion);

    /*
     * Con la declaración XML a mitad de documento el HTML deja de ser válido y
     * algunos navegadores dejan de pintar el resto de la página. (El comentario
     * no la escribe entera a propósito: la secuencia de cierre corta el bloque
     * PHP incluso dentro de un comentario de línea.)
     */
    expect($svg)->toStartWith('<svg')
        ->and($svg)->not->toContain('<?xml');
});

it('sólo etiqueta lo que tiene carcasa donde pegarla', function (): void {
    $fisicos = [TipoActivo::Hardware, TipoActivo::Comunicaciones, TipoActivo::Soportes,
        TipoActivo::EquipamientoAuxiliar, TipoActivo::Instalaciones];
    $logicos = [TipoActivo::Servicios, TipoActivo::Datos, TipoActivo::Software, TipoActivo::Personal];

    foreach ([...$fisicos, ...$logicos] as $tipo) {
        Activo::factory()->de($this->organizacion)->deTipo($tipo)->create();
    }

    $etiquetables = $this->generador->etiquetables(Activo::query()->get());

    expect($etiquetables)->toHaveCount(count($fisicos));
});

it('no etiqueta un activo retirado', function (): void {
    Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)
        ->enEstado(EstadoCicloVida::Retirado)->create();

    expect($this->generador->etiquetables(Activo::query()->get()))->toBeEmpty();
});

it('sirve la hoja con todo lo etiquetable y dice qué dejó fuera', function (): void {
    Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->create(['codigo' => 'HW-1']);
    Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Servicios)->create(['codigo' => 'SRV-1']);
    Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Datos)->create(['codigo' => 'BBDD-1']);

    $this->actingAs($this->usuario)
        ->get('/activos/etiquetas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('activos/Etiquetas')
            ->has('etiquetas', 1)
            ->where('etiquetas.0.codigo', 'HW-1')
            // Que diga cuántos quedaron fuera evita pensar que se ha perdido
            // medio inventario por el camino.
            ->where('descartados', 2)
        );
});

it('acota la hoja a la selección de la tabla', function (): void {
    $uno = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->create(['codigo' => 'HW-1']);
    Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->create(['codigo' => 'HW-2']);

    $this->actingAs($this->usuario)
        ->get("/activos/etiquetas?ids[]={$uno->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('etiquetas', 1)
            ->where('etiquetas.0.codigo', 'HW-1')
        );
});

it('no sirve etiquetas de activos de otra organización', function (): void {
    $ajena = comoOrganizacion();
    $ajeno = Activo::factory()->de($ajena)->deTipo(TipoActivo::Hardware)->create(['codigo' => 'AJENO']);
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->get("/activos/etiquetas?ids[]={$ajeno->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('etiquetas', 0));
});

/*
|--------------------------------------------------------------------------
| El QR en pantalla
|--------------------------------------------------------------------------
|
| Se enseña en la ficha y en el formulario para poder comprobar a qué apunta
| ANTES de imprimir. Y no se enseña donde no tiene sentido: un servicio en la
| nube no tiene carcasa, y un equipo retirado no se etiqueta, se borra.
|
*/

it('lleva el QR a la ficha de un activo físico, con la URL que codifica', function (): void {
    $this->organizacion->update(['url_base_etiquetas' => 'https://statera.ejemplo']);

    $activo = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->create();

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('etiqueta')
            ->where('etiqueta.url', "https://statera.ejemplo/activos/{$activo->id}")
            ->where('etiqueta.svg', fn (string $svg): bool => str_starts_with($svg, '<svg'))
        );
});

it('lleva el QR también al formulario de edición', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->create();

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}/editar")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('activos/Formulario')
            ->has('etiqueta.svg')
        );
});

it('no enseña QR donde no hay dónde pegarlo', function (): void {
    $servicio = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Servicios)->create();
    $retirado = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->retirado()->create();

    foreach ([$servicio, $retirado] as $activo) {
        $this->actingAs($this->usuario)
            ->get("/activos/{$activo->id}")
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('etiqueta', null));
    }
});

it('el alta no trae etiqueta, pero el prop viaja igual', function (): void {
    // Un activo que no existe no tiene ficha a la que apuntar. El prop se manda
    // en nulo para que el formulario no tenga que distinguir alta de edición.
    $this->actingAs($this->usuario)
        ->get('/activos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('etiqueta', null));
});

it('deja ver las etiquetas a quien sólo lee', function (): void {
    // Imprimir no escribe nada, así que no exige gestionar ni segundo factor:
    // quien pega pegatinas no tiene por qué poder editar el inventario.
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)->get('/activos/etiquetas')->assertOk();
});
