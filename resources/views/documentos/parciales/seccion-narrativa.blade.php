{{--
    Una sección entera del documento escrita por la organización.

    **Un hueco vacío no imprime su sección.** Un `<h2>` con nada debajo se lee
    como un documento roto, y estos huecos están vacíos de fábrica: la mayoría
    de las organizaciones no van a rellenarlos todos.
--}}
@if ($contenido->textos->tiene($seccion))
    <section class="seccion">
        <h2>{{ $seccion->titulo() }}</h2>
        <div class="narrativa">{!! $contenido->textos->html($seccion) !!}</div>
    </section>
@endif
