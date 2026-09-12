{{--
    El esqueleto de todo documento generado.

    Ya no queda nada de documento en las plantillas Blade: el cuerpo entero
    —portada, tablas, cifras y texto— lo produce `RenderizadorCuerpo` a partir
    del JSON editable, y aquí sólo queda el `<head>`. `soa-iso.blade.php`,
    `dda-ens.blade.php` y los once parciales se retiraron con esa entrega; lo que
    hacían se lee ahora en `MaterializarCuerpo`, que emite el mismo marcado y las
    mismas clases de `documento.css`.

    El `{!! !!}` del cuerpo es la única salida sin escapar del documento, y es
    segura por construcción: lo que llega ha pasado por `RenderizadorCuerpo`, que
    escapa todo texto con `e()` y **no tiene ninguna rama que pinte un nodo que
    no esté en `EsquemaCuerpo`**. No hay nodo de HTML crudo en el esquema, así
    que no existe la puerta que habría que cerrar.

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
{!! $cuerpo !!}
</body>
</html>
