<?php

declare(strict_types=1);

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Cuerpo\ColumnasTabla;
use App\Domain\Documento\Cuerpo\EsquemaCuerpo;
use App\Domain\Documento\Cuerpo\MaterializarCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;

/**
 * Que ningún hueco calculado se quede sin quien lo rellene.
 *
 * `MaterializarCuerpo` reparte el trabajo con dos `match` sobre **cadenas** —la
 * fuente de un bloque y la clave de una columna—, así que PHPStan no puede
 * señalar la rama que falta como sí hace con los `match` sobre `TipoDocumento`.
 * Los dos cerraban con un `default` silencioso: un bloque vacío en el PDF y una
 * columna entera de rayas, sin ningún error y sin nada que lo destape hasta que
 * alguien abre el documento entregado.
 *
 * Ahora los dos `default` lanzan, y esto es lo que comprueba que ninguno salta
 * con lo que hay declarado hoy. **Se parametriza solo**: el día que entre un
 * cuarto documento calculado con sus fuentes y sus columnas, estos tests ya lo
 * están recorriendo.
 */
function materializador(): MaterializarCuerpo
{
    return new MaterializarCuerpo;
}

/** Un contenido cualquiera: aquí no se comprueba qué se pinta, sino que se pinta. */
function contenidoVacio(): ContenidoDocumento
{
    return new ContenidoDocumento(
        titulo: 'Documento de prueba',
        subtitulo: 'Sin datos',
        portada: [],
        resumen: [],
        filas: [],
    );
}

/** Una fila con los diecinueve campos llenos, para que ninguna celda se escape por un nulo. */
function filaCompleta(): FilaRequisito
{
    return new FilaRequisito(
        grupo: 'op.acc · Control de acceso',
        codigo: 'op.acc.4',
        titulo: 'Proceso de gestión de derechos de acceso',
        aplica: true,
        estado: 'en_progreso',
        estadoEtiqueta: 'En progreso',
        estadoTono: 'en_progreso',
        justificacion: 'Justificación de prueba',
        justificacionInclusion: 'Anexo A',
        exigencia: 'R1',
        origenExigencia: 'Categoría básica',
        dimensionModuladora: 'C',
        madurez: 'L2 — Reproducible',
        madurezValor: 2,
        responsable: 'Nombre Apellido',
        evidencias: ['Captura de la consola (2026-01-15)'],
        correspondencias: ['A.5.18'],
    );
}

it('materializa toda fuente declarada, sin ninguna rama olvidada', function (string $fuente): void {
    $bloque = invocarPrivado(materializador(), 'bloque', [
        $fuente, contenidoVacio(), TipoDocumento::DdaEns, false, [],
    ]);

    expect($bloque)->toBeArray()
        ->and($bloque['attrs']['fuente'] ?? null)->toBe($fuente);
})->with(EsquemaCuerpo::FUENTES);

it('nombra en castellano toda fuente declarada', function (): void {
    $nombres = (new ReflectionClass(MaterializarCuerpo::class))->getConstant('NOMBRES_DE_BLOQUE');

    expect(array_keys($nombres))->toEqualCanonicalizing(EsquemaCuerpo::FUENTES);
});

/*
 * Sin esto, una columna nueva declarada en `ColumnasTabla` y olvidada en
 * `celdaDeFila()` salía como una raya en las noventa y tres filas del documento.
 */
it('pinta toda columna declarada de la tabla larga', function (TipoDocumento $tipo): void {
    $fila = filaCompleta();

    foreach (ColumnasTabla::para($tipo) as $columna) {
        $celda = invocarPrivado(materializador(), 'celdaDeFila', [$fila, $columna['clave']]);

        expect($celda['type'] ?? null)->toBe('tableCell');
    }
    // Sólo los tipos que tienen tabla larga: en el resto el bucle no entra y el
    // caso saldría «risky» sin comprobar nada.
})->with(fn () => array_values(array_filter(
    TipoDocumento::cases(),
    static fn (TipoDocumento $tipo): bool => ColumnasTabla::para($tipo) !== [],
)));

/**
 * @param  list<mixed>  $argumentos
 */
function invocarPrivado(object $objeto, string $metodo, array $argumentos): mixed
{
    $reflejo = new ReflectionMethod($objeto, $metodo);
    $reflejo->setAccessible(true);

    return $reflejo->invokeArgs($objeto, $argumentos);
}
