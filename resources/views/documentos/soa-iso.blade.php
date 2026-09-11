@extends('documentos.layout')

@section('cuerpo')
    @include('documentos.parciales.resumen', [
        'contenido' => $contenido,
        'barra' => $barra,
        'etiquetaTotal' => 'Controles del Anexo A',
    ])

    <section class="seccion">
        <h2>Controles del Anexo A</h2>
        {{-- Estos dos párrafos estaban aquí clavados; ahora son texto de
             fábrica de la plantilla y la organización puede reescribirlos. --}}
        @include('documentos.parciales.narrativa', ['contenido' => $contenido, 'seccion' => 'nota_tabla'])

        @include('documentos.parciales.tabla-requisitos', [
            'contenido' => $contenido,
            'columnas' => [
                ['clave' => 'codigo', 'titulo' => 'Control', 'ancho' => '0.62in'],
                ['clave' => 'titulo', 'titulo' => 'Título', 'ancho' => '2.2in'],
                ['clave' => 'aplica', 'titulo' => 'Aplica', 'ancho' => '0.42in'],
                ['clave' => 'justificacionInclusion', 'titulo' => 'Origen de la inclusión', 'ancho' => '1.6in'],
                ['clave' => 'justificacion', 'titulo' => 'Justificación de exclusión', 'ancho' => '1.65in'],
                ['clave' => 'estado', 'titulo' => 'Estado', 'ancho' => '0.78in'],
                ['clave' => 'madurez', 'titulo' => 'Madurez', 'ancho' => '0.55in'],
                ['clave' => 'responsable', 'titulo' => 'Responsable', 'ancho' => '0.95in'],
                ['clave' => 'evidencias', 'titulo' => 'Evidencia', 'ancho' => '1.5in'],
                ['clave' => 'correspondencias', 'titulo' => 'Correspondencia ENS', 'ancho' => '1.05in'],
            ],
        ])
    </section>

    @include('documentos.parciales.exclusiones', ['contenido' => $contenido])
@endsection
