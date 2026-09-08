<?php

declare(strict_types=1);

use App\Domain\Catalogo\Excepciones\CatalogoInvalido;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Mapeo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Catalogo\Models\Refuerzo;
use App\Domain\Catalogo\Models\Requisito;

beforeEach(function (): void {
    $this->directorio = sys_get_temp_dir().'/statera-catalogo-'.bin2hex(random_bytes(6));
    mkdir($this->directorio);
    $this->importador = app(ImportadorCatalogo::class);
});

afterEach(function (): void {
    foreach (glob($this->directorio.'/*') ?: [] as $fichero) {
        unlink($fichero);
    }

    rmdir($this->directorio);
});

/**
 * Escribe un YAML de prueba y devuelve su ruta.
 */
function escribirCatalogo(string $directorio, string $nombre, string $contenido): string
{
    $ruta = $directorio.'/'.$nombre;
    file_put_contents($ruta, $contenido);

    return $ruta;
}

function marcoDePrueba(string $requisitos): string
{
    return <<<YAML
    marco:
      codigo: MARCO-TEST
      nombre: Marco de prueba
      version: '1.0'
      estado: vigente
    revisado: false
    requisitos:
    {$requisitos}
    YAML;
}

it('importa un marco con su jerarquía, refuerzos y matriz de aplicabilidad', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: op
        tipo: medida
        titulo: Marco operacional
        hijos:
          - codigo: op.acc
            tipo: medida
            titulo: Control de acceso
            hijos:
              - codigo: op.acc.4
                tipo: medida
                titulo: Proceso de gestión de derechos de acceso
                aplicabilidad: {basica: aplica, media: R1, alta: R2}
                refuerzos:
                  - codigo: R1
                    descripcion: Revisión periódica de los derechos concedidos.
                  - codigo: R2
                    descripcion: Revisión automatizada con registro de cada cambio.
    YAML));

    $resultado = $this->importador->importar($fichero);

    expect($resultado->nuevos)->toHaveCount(3)
        ->and($resultado->modificados)->toBeEmpty()
        ->and($resultado->refuerzos)->toBe(2)
        ->and($resultado->celdasAplicabilidad)->toBe(3);

    $medida = Requisito::query()->where('codigo', 'op.acc.4')->firstOrFail();

    expect($medida->padre->codigo)->toBe('op.acc')
        ->and($medida->padre->padre->codigo)->toBe('op')
        ->and($medida->refuerzos)->toHaveCount(2)
        ->and((string) $medida->aplicabilidad()->where('categoria', 'alta')->value('exigencia'))->toBe('R2');
});

it('es idempotente: la segunda pasada no produce ningún cambio', function (): void {
    $yaml = marcoDePrueba(<<<'YAML'
      - codigo: org
        tipo: medida
        titulo: Marco organizativo
        hijos:
          - codigo: org.1
            tipo: medida
            titulo: Política de seguridad
            aplicabilidad: {basica: aplica, media: aplica, alta: aplica}
    YAML);

    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', $yaml);

    $this->importador->importar($fichero);
    $segunda = $this->importador->importar($fichero);

    expect($segunda->hayCambios())->toBeFalse()
        ->and($segunda->nuevos)->toBeEmpty()
        ->and($segunda->modificados)->toBeEmpty()
        ->and($segunda->retirados)->toBeEmpty()
        ->and($segunda->sinCambios)->toBe(2);

    expect(Requisito::query()->count())->toBe(2)
        ->and(AplicabilidadEns::query()->count())->toBe(3);
});

it('el diff distingue lo nuevo, lo modificado y lo desaparecido', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
      - codigo: org.2
        tipo: medida
        titulo: Normativa de seguridad
      - codigo: org.3
        tipo: medida
        titulo: Procedimientos de seguridad
    YAML));

    $this->importador->importar($fichero);

    // Revisión del marco: org.1 cambia de título, org.3 desaparece y entra org.4.
    file_put_contents($fichero, marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad de la información
      - codigo: org.2
        tipo: medida
        titulo: Normativa de seguridad
      - codigo: org.4
        tipo: medida
        titulo: Proceso de autorización
    YAML));

    $resultado = $this->importador->importar($fichero);

    expect($resultado->nuevos)->toBe(['org.4'])
        ->and($resultado->retirados)->toBe(['org.3'])
        ->and($resultado->sinCambios)->toBe(1)
        ->and($resultado->modificados)->toHaveCount(1)
        ->and($resultado->modificados[0]['codigo'])->toBe('org.1')
        ->and($resultado->modificados[0]['cambios'])->toContain('titulo');
});

it('no borra los requisitos que desaparecen: los marca como no vigentes', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
      - codigo: org.9
        tipo: medida
        titulo: Medida que desaparece en la siguiente revisión
    YAML));

    $this->importador->importar($fichero);

    file_put_contents($fichero, marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
    YAML));

    $this->importador->importar($fichero);

    $retirada = Requisito::query()->where('codigo', 'org.9')->first();

    expect($retirada)->not->toBeNull()
        ->and($retirada->vigente)->toBeFalse()
        ->and($retirada->retirado_en)->not->toBeNull();

    expect(Requisito::query()->vigentes()->count())->toBe(1);
});

it('reactiva un requisito que vuelve a aparecer en el fichero', function (): void {
    $completo = marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
      - codigo: org.2
        tipo: medida
        titulo: Normativa de seguridad
    YAML);

    $recortado = marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
    YAML);

    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', $completo);
    $this->importador->importar($fichero);

    file_put_contents($fichero, $recortado);
    $this->importador->importar($fichero);

    file_put_contents($fichero, $completo);
    $resultado = $this->importador->importar($fichero);

    expect($resultado->reactivados)->toBe(['org.2'])
        ->and(Requisito::query()->where('codigo', 'org.2')->value('vigente'))->toBeTrue();
});

it('en modo simulación no escribe nada pero informa del diff', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
        aplicabilidad: {basica: aplica, media: aplica, alta: aplica}
    YAML));

    $resultado = $this->importador->importar($fichero, simulacion: true);

    expect($resultado->simulacion)->toBeTrue()
        ->and($resultado->nuevos)->toBe(['org.1']);

    expect(Marco::query()->count())->toBe(0)
        ->and(Requisito::query()->count())->toBe(0)
        ->and(AplicabilidadEns::query()->count())->toBe(0);
});

it('aborta sin escribir nada si el YAML está malformado', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'roto.yaml', <<<'YAML'
    marco:
      codigo: MARCO-TEST
       nombre: indentación rota
    requisitos: []
    YAML);

    expect(fn () => $this->importador->importar($fichero))
        ->toThrow(CatalogoInvalido::class);

    expect(Marco::query()->count())->toBe(0);
});

it('rechaza el fichero entero si una exigencia no es válida', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
        aplicabilidad: {basica: obligatoria}
    YAML));

    expect(fn () => $this->importador->importar($fichero))
        ->toThrow(CatalogoInvalido::class);

    expect(Marco::query()->count())->toBe(0)
        ->and(Requisito::query()->count())->toBe(0);
});

it('rechaza un requisito con tipo desconocido', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: recomendacion
        titulo: Política de seguridad
    YAML));

    expect(fn () => $this->importador->importar($fichero))
        ->toThrow(CatalogoInvalido::class);
});

it('rechaza códigos duplicados dentro del mismo marco', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
      - codigo: org.1
        tipo: medida
        titulo: Repetida
    YAML));

    expect(fn () => $this->importador->importar($fichero))
        ->toThrow(CatalogoInvalido::class);
});

it('sincroniza los refuerzos: los que dejan de estar en el fichero desaparecen', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: op.acc.5
        tipo: medida
        titulo: Mecanismo de autenticación
        refuerzos:
          - codigo: R1
            descripcion: Segundo factor.
          - codigo: R2
            descripcion: Certificado cualificado.
    YAML));

    $this->importador->importar($fichero);
    expect(Refuerzo::query()->count())->toBe(2);

    file_put_contents($fichero, marcoDePrueba(<<<'YAML'
      - codigo: op.acc.5
        tipo: medida
        titulo: Mecanismo de autenticación
        refuerzos:
          - codigo: R1
            descripcion: Segundo factor.
    YAML));

    $this->importador->importar($fichero);

    expect(Refuerzo::query()->count())->toBe(1)
        ->and(Refuerzo::query()->value('codigo'))->toBe('R1');
});

it('reconoce la modulación por nivel de dimensión', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: op.cont.2
        tipo: medida
        titulo: Plan de continuidad
        aplicabilidad:
          basica: {exigencia: no_aplica, dimension: D}
          media: {exigencia: no_aplica, dimension: D}
          alta: {exigencia: aplica, dimension: D}
    YAML));

    $this->importador->importar($fichero);

    $celda = AplicabilidadEns::query()->where('categoria', 'alta')->firstOrFail();

    expect($celda->moduladaPorDimension())->toBeTrue()
        ->and($celda->dimension_moduladora->value)->toBe('D')
        ->and($celda->exigencia->esAplicable())->toBeTrue();
});

it('importa perfiles de cumplimiento como vista filtrada del catálogo', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
      - codigo: org.2
        tipo: medida
        titulo: Normativa de seguridad
      - codigo: op.acc.1
        tipo: medida
        titulo: Identificación
    YAML)."\nperfiles:\n  - codigo: PERFIL-ESENCIALES\n    nombre: Requisitos esenciales\n    referencia: CCN-STIC 890\n    requisitos:\n      - codigo: org.1\n      - codigo: op.acc.1\n        exigencia: R1\n");

    $resultado = $this->importador->importar($fichero);

    expect($resultado->perfiles)->toBe(1);

    $perfil = PerfilCumplimiento::query()->where('codigo', 'PERFIL-ESENCIALES')->firstOrFail();

    expect($perfil->requisitos)->toHaveCount(2)
        ->and($perfil->requisitos->firstWhere('codigo', 'op.acc.1')->pivot->exigencia)->toBe('R1');
});

it('rechaza un perfil que referencia un requisito inexistente', function (): void {
    $fichero = escribirCatalogo($this->directorio, 'marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
    YAML)."\nperfiles:\n  - codigo: PERFIL-ROTO\n    nombre: Perfil roto\n    requisitos:\n      - codigo: op.inexistente\n");

    expect(fn () => $this->importador->importar($fichero))
        ->toThrow(CatalogoInvalido::class);

    expect(PerfilCumplimiento::query()->count())->toBe(0);
});

it('importa mapeos entre marcos y rechaza los que apuntan a un requisito inexistente', function (): void {
    $origen = escribirCatalogo($this->directorio, 'a-marco-uno.yaml', <<<'YAML'
    marco:
      codigo: MARCO-A
      nombre: Marco A
      version: '1.0'
    requisitos:
      - codigo: A.1
        tipo: control
        titulo: Control uno
    YAML);

    $destino = escribirCatalogo($this->directorio, 'b-marco-dos.yaml', <<<'YAML'
    marco:
      codigo: MARCO-B
      nombre: Marco B
      version: '1.0'
    requisitos:
      - codigo: b.1
        tipo: medida
        titulo: Medida uno
    YAML);

    $this->importador->importar($origen);
    $this->importador->importar($destino);

    $mapeos = escribirCatalogo($this->directorio, 'c-mapeos.yaml', <<<'YAML'
    mapeos:
      - origen: {marco: MARCO-A, codigo: A.1}
        destino: {marco: MARCO-B, codigo: b.1}
        tipo: parcial
        nota: Cubre solo la parte organizativa.
    YAML);

    $resultado = $this->importador->importar($mapeos);

    expect($resultado->mapeosNuevos)->toBe(1)
        ->and(Mapeo::query()->first()->tipo_correspondencia->value)->toBe('parcial');

    // Reimportar el mismo fichero no duplica ni actualiza nada.
    $segunda = $this->importador->importar($mapeos);
    expect($segunda->hayCambios())->toBeFalse()
        ->and(Mapeo::query()->count())->toBe(1);

    // Un extremo inexistente aborta el fichero entero.
    file_put_contents($mapeos, <<<'YAML'
    mapeos:
      - origen: {marco: MARCO-A, codigo: A.1}
        destino: {marco: MARCO-B, codigo: b.999}
        tipo: equivalente
    YAML);

    expect(fn () => $this->importador->importar($mapeos))
        ->toThrow(CatalogoInvalido::class);

    expect(Mapeo::query()->count())->toBe(1);
});

it('ordena los ficheros de un directorio dejando los mapeos para el final', function (): void {
    escribirCatalogo($this->directorio, 'z-mapeos.yaml', "mapeos: []\n");
    escribirCatalogo($this->directorio, 'a-marco.yaml', marcoDePrueba(<<<'YAML'
      - codigo: org.1
        tipo: medida
        titulo: Política de seguridad
    YAML));

    $ficheros = array_map('basename', $this->importador->ficherosDe($this->directorio));

    expect($ficheros)->toBe(['a-marco.yaml', 'z-mapeos.yaml']);
});
