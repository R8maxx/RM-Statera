<?php

declare(strict_types=1);

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Excepciones\ConformidadNoPermitida;
use App\Domain\Conformidad\IniciarDeclaracion;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Conformidad\RegistrarDeclaracion;
use App\Domain\Conformidad\RegistrarPublicacionDistintivo;
use App\Domain\Conformidad\RetirarConformidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\Obligacion;
use App\Domain\Sistema\AplicarValoracion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| La conformidad con el ENS en categoría básica (§ 4.17)
|--------------------------------------------------------------------------
|
| Autoevaluación cerrada → Declaración de Conformidad firmada → distintivo
| publicado. Lo que se fija aquí son las puertas: lo que haría **falsa** la
| declaración no la deja iniciar, la versión que la respalda tiene que ser
| exactamente la suya, y la categoría declarada no se mueve aunque el sistema
| se revalore después.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->sistema = sistemaEns($this->organizacion);
    $this->marco = $this->sistema->marco;

    $this->valorar = function (NivelDimension $nivel): void {
        app(AplicarValoracion::class)->aplicar($this->sistema->fresh(), uniforme($nivel), []);
    };

    $this->autoevaluacion = fn (array $resultados = [ResultadoPunto::Conforme, ResultadoPunto::Conforme], bool $cerrar = true, ?callable $antes = null): Auditoria => autoevaluacion($this->sistema, $resultados, $cerrar, $antes, $this->usuario);

    /** Una versión emitida de la Declaración de Conformidad de un sistema. */
    $this->versionFirmada = function (?Sistema $sistema = null, int $numero = 1): DocumentoVersion {
        $sistema ??= $this->sistema;

        $documento = Documento::query()->where('sistema_id', $sistema->id)->first()
            ?? Documento::factory()->declaracionConformidad()->paraSistema($sistema->id)->create([
                'codigo' => 'DDC-'.$sistema->codigo,
            ]);

        /*
         * La vigente se jubila antes, como hace `EmitirVersion`: el índice único
         * parcial no admite dos aprobadas vivas del mismo documento. Es el único
         * cambio que el trigger de inmutabilidad deja pasar.
         */
        DB::table('documento_versiones')
            ->where('documento_id', $documento->id)
            ->where('estado', 'aprobado')
            ->update(['estado' => 'obsoleto', 'obsoleta_en' => Carbon::today()]);

        return DocumentoVersion::factory()->delDocumento($documento->id)->emitida($numero)->create();
    };
});

it('inicia la declaración sobre la última autoevaluación cerrada y congela la categoría', function (): void {
    $autoevaluacion = ($this->autoevaluacion)();

    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    expect($conformidad->estado)->toBe(EstadoConformidad::EnPreparacion)
        ->and($conformidad->categoria)->toBe(CategoriaEns::Basica)
        ->and($conformidad->auditoria_id)->toBe($autoevaluacion->id)
        ->and($conformidad->transiciones()->count())->toBe(1)
        ->and($conformidad->transiciones()->first()?->estado_anterior)->toBeNull();
});

it('no inicia sin una autoevaluación cerrada', function (): void {
    ($this->autoevaluacion)(cerrar: false);

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'No hay ninguna autoevaluación cerrada');
});

it('una auditoría interna cerrada no cuenta como autoevaluación', function (): void {
    Auditoria::factory()->paraSistema($this->sistema->id)->cerrada()->create();

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'autoevaluación cerrada');
});

it('no inicia con medidas sin revisar: pendiente no es conforme', function (): void {
    ($this->autoevaluacion)([ResultadoPunto::Conforme, ResultadoPunto::Pendiente]);

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, '1 de 2 medidas sin revisar');
});

it('no inicia con una no conformidad mayor abierta, y sí cuando está cerrada', function (): void {
    $hallazgo = null;

    ($this->autoevaluacion)([ResultadoPunto::NoConforme], antes: function (Auditoria $auditoria) use (&$hallazgo): void {
        $hallazgo = Hallazgo::factory()->deTipo(TipoHallazgo::NcMayor)->create([
            'auditoria_id' => $auditoria->id,
            'auditoria_punto_id' => $auditoria->puntos()->value('id'),
        ]);
    });

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'no conformidad mayor sin cerrar');

    $nc = NoConformidad::factory()->deHallazgo($hallazgo->id)->create();

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'no conformidad mayor sin cerrar');

    $nc->update(['estado' => EstadoNoConformidad::Cerrada->value, 'fecha_cierre' => Carbon::today()]);

    expect(app(IniciarDeclaracion::class)($this->sistema, $this->usuario)->estado)
        ->toBe(EstadoConformidad::EnPreparacion);
});

it('las no conformidades menores no impiden declarar', function (): void {
    ($this->autoevaluacion)([ResultadoPunto::NoConforme], antes: function (Auditoria $auditoria): void {
        Hallazgo::factory()->deTipo(TipoHallazgo::NcMenor)->create([
            'auditoria_id' => $auditoria->id,
            'auditoria_punto_id' => $auditoria->puntos()->value('id'),
        ]);
    });

    expect(app(IniciarDeclaracion::class)($this->sistema, $this->usuario)->estado)
        ->toBe(EstadoConformidad::EnPreparacion);
});

it('un sistema de categoría media no se declara: se certifica', function (): void {
    ($this->valorar)(NivelDimension::Medio);
    ($this->autoevaluacion)();

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema->fresh(), $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'ENAC');
});

it('la base impide una declaración de categoría media y una certificación de básica', function (): void {
    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    // Cada intento en su propio punto de guardado: en PostgreSQL, una sentencia
    // que falla aborta la transacción del test entera.
    expect(fn () => DB::transaction(fn () => DB::table('conformidades')->where('id', $conformidad->id)->update(['categoria' => 'media'])))
        ->toThrow(QueryException::class, 'conformidades_via_categoria_check');

    expect(fn () => DB::transaction(fn () => DB::table('conformidades')->where('id', $conformidad->id)->update(['via' => 'certificacion'])))
        ->toThrow(QueryException::class, 'conformidades_via_categoria_check');
});

it('no admite dos declaraciones en preparación del mismo sistema', function (): void {
    ($this->autoevaluacion)();
    app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'Ya hay una declaración en preparación');
});

it('declara con la versión firmada: fecha de la firma y vigencia bienal', function (): void {
    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);
    $version = ($this->versionFirmada)();

    $conformidad = app(RegistrarDeclaracion::class)($conformidad, $version, $this->usuario);

    expect($conformidad->estado)->toBe(EstadoConformidad::Declarada)
        ->and($conformidad->documento_version_id)->toBe($version->id)
        ->and($conformidad->fecha_declaracion?->toDateString())->toBe(Carbon::today()->toDateString())
        ->and($conformidad->vigente_hasta?->toDateString())->toBe(Carbon::today()->addMonthsNoOverflow(24)->toDateString())
        ->and($conformidad->estaEnVigor())->toBeTrue();
});

it('rechaza la versión de otro sistema, un borrador y una emitida antes de iniciar', function (): void {
    // Emitida antes de iniciar la declaración: el trigger no deja reescribir
    // `emitida_en`, así que lo que se mueve es el reloj.
    $anterior = ($this->versionFirmada)();
    $this->travel(1)->hours();

    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    expect(fn () => app(RegistrarDeclaracion::class)($conformidad, $anterior->fresh(), $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'antes de iniciar');

    $otro = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();
    expect(fn () => app(RegistrarDeclaracion::class)($conformidad, ($this->versionFirmada)($otro), $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'otro sistema');

    $borrador = DocumentoVersion::factory()->delDocumento($anterior->documento_id)->generada()->create();
    expect(fn () => app(RegistrarDeclaracion::class)($conformidad, $borrador, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'no está emitida');

    expect($conformidad->fresh()->estado)->toBe(EstadoConformidad::EnPreparacion);
});

it('registra el cumplimiento de ens.conformidad si la obligación está asumida', function (): void {
    $obligacion = Obligacion::factory()->deMarco($this->marco->id)->cada(24)->create(['codigo' => 'ens.conformidad']);
    $compromiso = Compromiso::factory()->deObligacion($obligacion->id)->deSistema($this->sistema->id)->cada(24)->create();

    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);
    $version = ($this->versionFirmada)();

    app(RegistrarDeclaracion::class)($conformidad, $version, $this->usuario);

    $cumplimiento = $compromiso->cumplimientos()->sole();

    expect($cumplimiento->documento_id)->toBe($version->documento_id)
        ->and($cumplimiento->cubre_hasta->toDateString())->toBe(Carbon::today()->addMonthsNoOverflow(24)->toDateString());
});

it('publica el distintivo sólo con una URL pública y una fecha posible', function (): void {
    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    // Sin declaración firmada detrás, no hay distintivo que publicar.
    expect(fn () => app(RegistrarPublicacionDistintivo::class)($conformidad, 'https://ejemplo.test/ens', Carbon::today()))
        ->toThrow(ConformidadNoPermitida::class);

    $conformidad = app(RegistrarDeclaracion::class)($conformidad, ($this->versionFirmada)(), $this->usuario);

    expect(fn () => app(RegistrarPublicacionDistintivo::class)($conformidad, 'javascript:alert(1)', Carbon::today()))
        ->toThrow(ConformidadNoPermitida::class, 'URL pública');

    expect(fn () => app(RegistrarPublicacionDistintivo::class)($conformidad, 'https://ejemplo.test/ens', Carbon::tomorrow()))
        ->toThrow(ConformidadNoPermitida::class, 'fecha futura');

    $conformidad = app(RegistrarPublicacionDistintivo::class)($conformidad, 'https://ejemplo.test/ens', Carbon::today(), usuario: $this->usuario);

    expect($conformidad->estado)->toBe(EstadoConformidad::Publicada)
        ->and($conformidad->distintivo_url)->toBe('https://ejemplo.test/ens');
});

it('retirar exige motivo, lo deja en el histórico y no tiene vuelta', function (): void {
    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    expect(fn () => app(RetirarConformidad::class)($conformidad, '   '))
        ->toThrow(ConformidadNoPermitida::class, 'exige decir por qué');

    $conformidad = app(RetirarConformidad::class)($conformidad, 'La autoevaluación se repite con otro alcance.', $this->usuario);

    expect($conformidad->estado)->toBe(EstadoConformidad::Retirada)
        ->and($conformidad->transiciones()->first()?->nota)->toBe('La autoevaluación se repite con otro alcance.');

    expect(fn () => app(RegistrarDeclaracion::class)($conformidad, ($this->versionFirmada)(), $this->usuario))
        ->toThrow(ConformidadNoPermitida::class);
});

it('declarar la renovación retira la anterior en la misma transacción', function (): void {
    ($this->autoevaluacion)();
    $primera = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);
    $primera = app(RegistrarDeclaracion::class)($primera, ($this->versionFirmada)(), $this->usuario);

    ($this->autoevaluacion)();
    $renovacion = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);
    $renovacion = app(RegistrarDeclaracion::class)($renovacion, ($this->versionFirmada)(numero: 2), $this->usuario);

    expect($renovacion->estado)->toBe(EstadoConformidad::Declarada)
        ->and($primera->fresh()->estado)->toBe(EstadoConformidad::Retirada)
        ->and($primera->fresh()->transiciones()->first()?->nota)->toContain('Sustituida')
        ->and(Conformidad::query()->enVigor()->count())->toBe(1);
});

it('la renovación no puede apoyarse en la autoevaluación que ya respalda la vigente', function (): void {
    ($this->autoevaluacion)();
    $primera = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);
    app(RegistrarDeclaracion::class)($primera, ($this->versionFirmada)(), $this->usuario);

    expect(fn () => app(IniciarDeclaracion::class)($this->sistema, $this->usuario))
        ->toThrow(ConformidadNoPermitida::class, 'ya respalda la declaración vigente');

    ($this->autoevaluacion)();

    expect(app(IniciarDeclaracion::class)($this->sistema, $this->usuario)->estado)
        ->toBe(EstadoConformidad::EnPreparacion);
});

it('revalorar el sistema después de iniciar no cambia la categoría declarada', function (): void {
    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    ($this->valorar)(NivelDimension::Alto);

    expect($conformidad->fresh()->categoria)->toBe(CategoriaEns::Basica)
        ->and($this->sistema->fresh()->categoria())->toBe(CategoriaEns::Alta);
});

it('caducar es una fecha y no un estado', function (): void {
    ($this->autoevaluacion)();
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);
    $conformidad = app(RegistrarDeclaracion::class)($conformidad, ($this->versionFirmada)(), $this->usuario);

    $this->travel(25)->months();

    $conformidad = $conformidad->fresh();

    expect($conformidad->estado)->toBe(EstadoConformidad::Declarada)
        ->and($conformidad->haCaducado())->toBeTrue()
        ->and($conformidad->tono())->toBe('caducada')
        ->and(Conformidad::query()->caducadas()->count())->toBe(1)
        ->and(Conformidad::query()->enVigor()->count())->toBe(0);
});
