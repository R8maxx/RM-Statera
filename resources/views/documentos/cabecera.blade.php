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
</style>
<div class="cab">
    <div class="cab__fila">
        <span><span class="cab__marca">Statera</span> · {{ $organizacion }}</span>
        <span>{{ $codigo }} — {{ $titulo }}</span>
    </div>
</div>
