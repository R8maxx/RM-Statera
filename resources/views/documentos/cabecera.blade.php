{{--
    Chromium renderiza la cabecera en un contexto APARTE: no carga recursos
    externos, no hereda el CSS de la página y no hereda las fuentes. Por eso va
    autocontenida, con su `<style>` en línea y una familia genérica.

    Y por eso lleva `font-size` explícito: sin él, todo lo que hay aquí sale
    minúsculo por la escala de impresión. Es el detalle que se lleva una tarde
    por delante.

    El ancho útil es la hoja ENTERA, no el área de contenido, así que el padding
    lateral replica a mano los márgenes que fija `GotenbergHttp`.
--}}
<style>
    * { box-sizing: border-box; }
    .cab {
        width: 100%;
        padding: 0 0.71in;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 7pt;
        color: #5D6C72;
        -webkit-print-color-adjust: exact;
    }
    .cab__fila {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        border-bottom: 0.5pt solid #DAE1E3;
        padding-bottom: 2pt;
    }
    .cab__marca { font-weight: 700; color: #007E81; letter-spacing: 0.08em; text-transform: uppercase; }
@if (! empty($simbolo))
    /*
        El símbolo de la organización, incrustado como data URI. No puede ser un
        fichero del multipart: este documento se renderiza en un contexto aparte
        que no lo recibe.

        8 pt de alto, que es lo que cabe al lado de un texto de 7 pt sin engordar
        la banda. `width: auto` para no deformar: un símbolo que llegue apaisado
        sale apaisado, porque el logo del cliente no se recorta (DESIGN.md §2).

        La regla va dentro de la condición y no suelta: sin símbolo, la cabecera
        tiene que salir byte a byte como salía antes de que existiera la marca.
        Es lo que permite meter esto sin revisar ningún documento anterior.

        Y ojo con escribir una directiva de Blade dentro de un comentario CSS de
        esta plantilla: Blade compila el fichero entero antes de que exista
        ningún CSS, así que la interpreta igual. Costó 71 tests en rojo.
    */
    .cab__simbolo { height: 8pt; width: auto; vertical-align: -1pt; margin-right: 4pt; }
@endif
</style>
<div class="cab">
    <div class="cab__fila">
        <span>
            {{-- Sin símbolo subido, la cabecera sale exactamente como salía. --}}
            @if (! empty($simbolo))
                <img class="cab__simbolo" src="{{ $simbolo }}" alt="">
            @endif
            <span class="cab__marca">Statera</span> · {{ $organizacion }}
        </span>
        <span>{{ $codigo }} — {{ $titulo }}</span>
    </div>
</div>
