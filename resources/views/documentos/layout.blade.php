{{--
    El esqueleto de todo documento generado.

    La hoja de estilos va como `<link>` a un fichero del multipart, no como un
    `<style>` incrustado: Gotenberg deja todos los ficheros del multipart en el
    mismo directorio temporal, así que `documento.css` resuelve a
    `file:///tmp/.../documento.css` y pasa la allow-list del contenedor. Ninguna
    URL remota entra aquí; si entrara, `failOnResourceLoadingFailed()` tumbaría
    la generación, que es justo lo que se quiere.

    `lang` explícito: sin él, un lector de pantalla lee el documento en inglés y
    PDF/UA lo exige.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $contenido->titulo }} — {{ $contenido->portada['documentoCodigo'] ?? '' }}</title>
    <link rel="stylesheet" href="{{ \App\Domain\Documento\Render\AssetsDocumento::HOJA }}">
</head>
<body>
@include('documentos.parciales.portada', ['contenido' => $contenido])

{{-- Lo que la organización redacta para abrir el documento. --}}
@foreach ($contenido->textos->aperturas() as $apertura)
    @include('documentos.parciales.seccion-narrativa', ['contenido' => $contenido, 'seccion' => $apertura])
@endforeach

@yield('cuerpo')

{{-- Y lo que redacta para cerrarlo, antes de las limitaciones. --}}
@foreach ($contenido->textos->cierres() as $cierre)
    @include('documentos.parciales.seccion-narrativa', ['contenido' => $contenido, 'seccion' => $cierre])
@endforeach

@include('documentos.parciales.limitaciones', ['contenido' => $contenido])
@include('documentos.parciales.control-versiones', ['contenido' => $contenido])
</body>
</html>
