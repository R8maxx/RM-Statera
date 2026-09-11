{{--
    Un texto redactado por la organización, ya convertido a HTML.

    El `{!! !!}` es deliberado y es seguro: lo que llega ha pasado por
    `MarkdownDocumento`, que escapa el HTML de entrada, quita los enlaces
    peligrosos, poda las imágenes —que tumbarían la generación— y baja los
    encabezados del usuario a `h3`/`h4` para no romper la jerarquía que exige
    PDF/UA. Escaparlo aquí otra vez imprimiría las etiquetas en el PDF.

    Si no hay nada, no se imprime nada: ni el `<div>` vacío.
--}}
@if ($contenido->textos->tiene($seccion))
    <div class="narrativa">{!! $contenido->textos->html($seccion) !!}</div>
@endif
