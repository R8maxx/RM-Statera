@extends('documentos.layout')

@section('cuerpo')
    @include('documentos.parciales.derivacion', ['contenido' => $contenido])

    @include('documentos.parciales.resumen', [
        'contenido' => $contenido,
        'barra' => $barra,
        'etiquetaTotal' => 'Medidas del Anexo II',
    ])

    <section class="seccion">
        <h2>Medidas del Anexo II</h2>
        {{-- Estos dos párrafos estaban aquí clavados; ahora son texto de
             fábrica de la plantilla. El segundo importa: sin él, «Refuerzo 9»
             se lee como «sólo el noveno». --}}
        @include('documentos.parciales.narrativa', ['contenido' => $contenido, 'seccion' => 'nota_tabla'])

        @include('documentos.parciales.tabla-requisitos', [
            'contenido' => $contenido,
            'columnas' => [
                ['clave' => 'codigo', 'titulo' => 'Medida', 'ancho' => '0.72in'],
                ['clave' => 'titulo', 'titulo' => 'Título', 'ancho' => '1.95in'],
                ['clave' => 'exigencia', 'titulo' => 'Exigencia', 'ancho' => '0.72in'],
                ['clave' => 'origenExigencia', 'titulo' => 'Origen de la exigencia', 'ancho' => '1.25in'],
                ['clave' => 'aplica', 'titulo' => 'Aplica', 'ancho' => '0.42in'],
                ['clave' => 'justificacion', 'titulo' => 'Justificación', 'ancho' => '1.4in'],
                ['clave' => 'estado', 'titulo' => 'Estado', 'ancho' => '0.78in'],
                ['clave' => 'madurez', 'titulo' => 'Madurez', 'ancho' => '0.55in'],
                ['clave' => 'responsable', 'titulo' => 'Responsable', 'ancho' => '0.95in'],
                ['clave' => 'evidencias', 'titulo' => 'Evidencia', 'ancho' => '1.45in'],
                ['clave' => 'correspondencias', 'titulo' => 'Correspondencia ISO', 'ancho' => '1.05in'],
            ],
        ])
    </section>

    @include('documentos.parciales.anexo-ii', ['contenido' => $contenido])
@endsection
