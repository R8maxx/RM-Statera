<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\ResumenInventario;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Los indicadores de control del inventario
|--------------------------------------------------------------------------
|
| Aquí un fallo da por bueno un parque sin cifrar, que es exactamente la clase
| de fallo silencioso que encabeza la lista de prioridades de cobertura: nadie
| se entera hasta que lo dice un auditor.
|
| Cada indicador se prueba con su caso que SÍ cuenta y con el que NO, porque el
| error caro no es contar de menos, es contar de más: un panel que marca
| incumplimientos falsos se deja de mirar en dos semanas.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->resumen = app(ResumenInventario::class);

    $this->activo = fn (array $atributos = []): Activo => Activo::factory()
        ->de($this->organizacion)
        ->create($atributos);

    $this->valor = function (string $clave): int {
        $indicador = collect([...$this->resumen->alertas(), ...$this->resumen->pendientesDeCompletar()])
            ->first(fn (Indicador $uno): bool => $uno->clave === $clave);

        expect($indicador)->not->toBeNull("No existe el indicador `{$clave}`.");

        return $indicador->valor;
    };
});

it('separa lo accionable de lo que falta por rellenar', function (): void {
    // Antes eran nueve cifras iguales, y un campo vacío pesaba lo mismo que un
    // disco sin cifrar. Ahora son dos listas con formas de pintarse distintas.
    expect($this->resumen->alertas())->toHaveCount(5)
        ->and($this->resumen->pendientesDeCompletar())->toHaveCount(3);
});

it('cuenta sin cifrado sólo los que dicen que no', function (): void {
    ($this->activo)(['cifrado' => EstadoControl::No->value]);
    ($this->activo)(['cifrado' => EstadoControl::Si->value]);
    ($this->activo)(['cifrado' => EstadoControl::NoAplica->value]);

    expect(($this->valor)('sin_cifrado'))->toBe(1);
});

it('no cuenta «por confirmar» como incumplimiento', function (): void {
    // La distinción entera del enum vive o muere en esta prueba: si «por
    // confirmar» cayera del lado de «no», dieciocho dudas se convertirían en
    // dieciocho incumplimientos y alguien se pasaría una semana arreglando
    // copias que ya existían.
    ($this->activo)([
        'cifrado' => EstadoControl::PorConfirmar->value,
        'copia_seguridad' => EstadoControl::PorConfirmar->value,
    ]);

    // No sale en las alertas…
    expect(($this->valor)('sin_cifrado'))->toBe(0)
        ->and(($this->valor)('sin_copia'))->toBe(0);

    // …pero tampoco desaparece: se ve en su tramo del reparto, que es donde
    // ahora se distingue el incumplimiento de la duda sin resolver.
    $cifrado = collect($this->resumen->cobertura('cifrado'));

    expect($cifrado->firstWhere('clave', EstadoControl::PorConfirmar->value)?->valor)->toBe(1)
        ->and($cifrado->firstWhere('clave', EstadoControl::No->value)?->valor)->toBe(0);

    // Y no cuenta como resuelto: nadie lo ha comprobado todavía.
    expect($this->resumen->controlesResueltos())->toBe(0);
});

it('el activo con las dos cosas por confirmar aparece en los dos repartos', function (): void {
    // Antes se agregaban en un solo indicador y no se sabía cuál de los dos
    // controles faltaba por comprobar. Ahora cada uno tiene su barra.
    ($this->activo)([
        'cifrado' => EstadoControl::PorConfirmar->value,
        'copia_seguridad' => EstadoControl::PorConfirmar->value,
    ]);

    $panel = $this->resumen->paraElPanel();

    expect(collect($panel->cifrado)->firstWhere('clave', EstadoControl::PorConfirmar->value)?->valor)->toBe(1)
        ->and(collect($panel->copia)->firstWhere('clave', EstadoControl::PorConfirmar->value)?->valor)->toBe(1);
});

it('cuenta sin propietario, sin identificador y sin ubicación', function (): void {
    ($this->activo)(['propietario_id' => null, 'identificador' => null, 'ubicacion' => null]);
    ($this->activo)([
        'propietario_id' => usuarioCon()->id,
        'identificador' => 'SN-0001',
        'ubicacion' => 'Sala técnica',
    ]);

    expect(($this->valor)('sin_propietario'))->toBe(1)
        ->and(($this->valor)('sin_identificador'))->toBe(1)
        ->and(($this->valor)('sin_ubicacion'))->toBe(1);
});

it('trata la cadena vacía como ausencia de dato', function (): void {
    // Un formulario que envía «» no deja `null` en la columna, y contarlo como
    // relleno esconde justo el campo que nadie completó.
    ($this->activo)(['identificador' => '', 'ubicacion' => '']);

    expect(($this->valor)('sin_identificador'))->toBe(1)
        ->and(($this->valor)('sin_ubicacion'))->toBe(1);
});

it('cuenta como sin revisar el que nunca se revisó', function (): void {
    ($this->activo)(['ultima_revision' => null]);
    ($this->activo)(['ultima_revision' => Carbon::today()->subMonths(13)->toDateString()]);
    ($this->activo)(['ultima_revision' => Carbon::today()->subMonths(2)->toDateString()]);

    expect(($this->valor)('sin_revisar'))->toBe(2);
});

it('cuenta la información restringida como perfil, no como alerta', function (): void {
    // No es un problema que haya información restringida: es lo que hay que
    // vigilar de cerca. Por eso salió de la tira de alertas y vive en el panel.
    ($this->activo)(['clasificacion' => Clasificacion::Restringido->value]);
    ($this->activo)(['clasificacion' => Clasificacion::Confidencial->value]);

    expect($this->resumen->paraElPanel()->restringidos)->toBe(1);
    expect(collect($this->resumen->alertas())->pluck('clave'))->not->toContain('restringida');
});

it('cuenta soporte vencido por sistema operativo o por garantía', function (): void {
    ($this->activo)(['fin_soporte_so' => Carbon::today()->subDay()->toDateString()]);
    ($this->activo)(['fin_garantia' => Carbon::today()->subDay()->toDateString()]);
    ($this->activo)(['fin_soporte_so' => Carbon::today()->addYear()->toDateString()]);
    // Sin fecha no es obsoleto: no saber no es incumplir.
    ($this->activo)(['fin_soporte_so' => null, 'fin_garantia' => null]);

    expect(($this->valor)('sin_soporte'))->toBe(2);
});

it('un activo de baja no está pendiente de nada, salvo del borrado seguro', function (): void {
    // Un portátil dado de baja sin copia de seguridad no está pendiente: está
    // cerrado. Lo ÚNICO que puede seguir debiendo es la constancia del borrado,
    // y esa es justamente la alerta que antes no existía.
    ($this->activo)([
        'estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value,
        'cifrado' => EstadoControl::No->value,
        'copia_seguridad' => EstadoControl::No->value,
        'clasificacion' => Clasificacion::Restringido->value,
        'borrado_seguro_en' => null,
    ]);

    foreach ($this->resumen->alertas() as $alerta) {
        $esperado = $alerta->clave === 'espera_borrado' ? 1 : 0;

        expect($alerta->valor)->toBe($esperado, "La alerta `{$alerta->clave}` cuenta mal un activo de baja.");
    }

    foreach ($this->resumen->pendientesDeCompletar() as $pendiente) {
        expect($pendiente->valor)->toBe(0, "`{$pendiente->clave}` cuenta un activo de baja.");
    }

    expect($this->resumen->vigentes())->toBe(0)
        ->and($this->resumen->paraElPanel()->restringidos)->toBe(0);
});

it('cuenta los estados intermedios como vigentes', function (): void {
    // En stock, en reparación y prestado siguen teniendo los datos dentro y
    // siguen siendo responsabilidad de alguien.
    foreach ([EstadoCicloVida::EnStock, EstadoCicloVida::EnReparacion, EstadoCicloVida::Prestado] as $estado) {
        ($this->activo)(['estado_ciclo_vida' => $estado->value, 'cifrado' => EstadoControl::No->value]);
    }

    expect(($this->valor)('sin_cifrado'))->toBe(3)
        ->and($this->resumen->vigentes())->toBe(3);
});

it('no cuenta activos de otra organización', function (): void {
    $ajena = comoOrganizacion();
    Activo::factory()->de($ajena)->create(['cifrado' => EstadoControl::No->value]);
    comoOrganizacion($this->organizacion);

    expect(($this->valor)('sin_cifrado'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Lo que abre el panel
|--------------------------------------------------------------------------
|
| «Controles resueltos» es la cifra que se lee primero, así que es la que más
| daño hace si miente: un porcentaje alto porque el denominador está mal deja de
| avisar justo cuando debería.
|
*/

it('cuenta como resuelto sólo el activo que tiene las DOS cosas decididas', function (): void {
    ($this->activo)(['cifrado' => EstadoControl::Si->value, 'copia_seguridad' => EstadoControl::Si->value]);
    // A medias no cuenta: falta por comprobar la mitad del control.
    ($this->activo)(['cifrado' => EstadoControl::Si->value, 'copia_seguridad' => EstadoControl::PorConfirmar->value]);
    ($this->activo)(['cifrado' => EstadoControl::No->value, 'copia_seguridad' => EstadoControl::Si->value]);

    expect($this->resumen->controlesResueltos())->toBe(1)
        ->and($this->resumen->vigentes())->toBe(3);
});

it('cuenta «no aplica» como resuelto', function (): void {
    // Un router no cifra en reposo porque no almacena nada. Contarlo como
    // pendiente pondría un techo que la organización no puede alcanzar por mucho
    // que trabaje, y un indicador que nunca llega al cien por cien se deja de
    // mirar.
    ($this->activo)([
        'cifrado' => EstadoControl::NoAplica->value,
        'copia_seguridad' => EstadoControl::NoAplica->value,
    ]);

    expect($this->resumen->controlesResueltos())->toBe(1);
});

it('la cobertura de cada control suma exactamente los activos vigentes', function (): void {
    // Si no suman, hay activos cayéndose entre casillas y la barra miente sin
    // que se note: los tramos siguen pareciendo un reparto completo.
    ($this->activo)(['cifrado' => EstadoControl::Si->value]);
    ($this->activo)(['cifrado' => EstadoControl::No->value]);
    ($this->activo)(['cifrado' => EstadoControl::PorConfirmar->value]);
    ($this->activo)(['estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value]);

    $panel = $this->resumen->paraElPanel();

    expect(collect($panel->cifrado)->sum('valor'))->toBe($panel->vigentes)
        ->and(collect($panel->copia)->sum('valor'))->toBe($panel->vigentes)
        ->and($panel->vigentes)->toBe(3);
});

it('reparte por tipo y deja fuera los tipos sin ningún activo', function (): void {
    // Nueve barras de las que seis están vacías no es un reparto: es una lista
    // de tipos.
    ($this->activo)(['tipo' => TipoActivo::Hardware->value]);
    ($this->activo)(['tipo' => TipoActivo::Hardware->value]);
    ($this->activo)(['tipo' => TipoActivo::Datos->value]);

    $porTipo = $this->resumen->porTipo();

    expect($porTipo)->toHaveCount(2)
        ->and(collect($porTipo)->sum('valor'))->toBe(3)
        ->and(collect($porTipo)->firstWhere('clave', TipoActivo::Hardware->value)?->valor)->toBe(2);
});

it('el reparto por ciclo de vida SÍ incluye los retirados', function (): void {
    // Es la excepción a contar sobre vigentes, y es deliberada: lo interesante
    // de esta gráfica es justamente el parque que ya no está en uso.
    ($this->activo)([]);
    ($this->activo)(['estado_ciclo_vida' => EstadoCicloVida::Retirado->value]);
    ($this->activo)(['estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value]);

    expect(collect($this->resumen->porCicloDeVida())->sum('valor'))->toBe(3)
        ->and($this->resumen->vigentes())->toBe(1);
});

it('cada tramo de un reparto lleva su tono del dominio, no un color', function (): void {
    ($this->activo)(['tipo' => TipoActivo::Datos->value]);

    expect(collect($this->resumen->porTipo())->first()?->tono)->toBe('tipo:datos');
});

/*
|--------------------------------------------------------------------------
| El hallazgo que no se contaba
|--------------------------------------------------------------------------
*/

it('cuenta los retirados sin constancia del borrado seguro', function (): void {
    // mp.si.5: el soporte sigue por ahí con los datos dentro. Existía por activo
    // pero no se contaba en ninguna parte, así que nadie lo veía hasta abrir una
    // ficha concreta.
    ($this->activo)([
        'estado_ciclo_vida' => EstadoCicloVida::Retirado->value,
        'borrado_seguro_en' => null,
    ]);
    ($this->activo)([
        'estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value,
        'borrado_seguro_en' => Carbon::yesterday()->toDateTimeString(),
    ]);
    // Uno en uso no espera ningún borrado.
    ($this->activo)([]);

    expect(($this->valor)('espera_borrado'))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Cada cifra tiene que llevar a su lista
|--------------------------------------------------------------------------
|
| Es lo que distingue esto de la hoja de cálculo de la que viene. Y es frágil:
| el indicador y el filtro comparten scope justamente para que no se separen, y
| esta prueba es la que se entera si alguien los separa.
|
*/

it('el filtro de cada indicador devuelve exactamente su cifra', function (): void {
    $usuario = usuarioCon();

    ($this->activo)(['cifrado' => EstadoControl::No->value, 'codigo' => 'A-1']);
    ($this->activo)(['copia_seguridad' => EstadoControl::PorConfirmar->value, 'codigo' => 'A-2']);
    ($this->activo)(['clasificacion' => Clasificacion::Restringido->value, 'codigo' => 'A-3']);
    ($this->activo)(['fin_garantia' => Carbon::today()->subDay()->toDateString(), 'codigo' => 'A-4']);
    ($this->activo)(['propietario_id' => $usuario->id, 'ubicacion' => 'X', 'identificador' => 'Y', 'codigo' => 'A-5']);

    foreach ([...$this->resumen->alertas(), ...$this->resumen->pendientesDeCompletar()] as $indicador) {
        $this->actingAs($usuario)
            ->get('/activos?'.$indicador->filtro)
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has(
                'filas',
                $indicador->valor,
                // Si esto falla, el panel dice una cifra y la tabla enseña otra:
                // a partir de ahí nadie se fía del panel.
            ), "El filtro de `{$indicador->clave}` no coincide con su indicador.");
    }
});
