{{--
    Mismas reglas que la cabecera: contexto aparte, sin CSS heredado, sin
    fuentes y con `font-size` explícito.

    `.pageNumber` y `.totalPages` son clases mágicas de Chromium y sólo
    funcionan dentro de estas plantillas: fuera de aquí no las sustituye nadie.
--}}
<style>
    * { box-sizing: border-box; }
    .pie {
        width: 100%;
        padding: 0 0.71in;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 7pt;
        color: #5D6C72;
        -webkit-print-color-adjust: exact;
    }
    .pie__fila {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        border-top: 0.5pt solid #DAE1E3;
        padding-top: 2pt;
    }
    .pie__sello { font-weight: 700; letter-spacing: 0.08em; }
    /* Sin cursiva: no se envía ninguna cara itálica y caería a otra fuente. */
    .pie__aviso { opacity: 0.85; }
</style>
<div class="pie">
    <div class="pie__fila">
        <span>
            <span class="pie__sello">{{ $clasificacion }}</span>
            · {{ $version }} · {{ $fecha }}
        </span>
        <span class="pie__aviso">Generado por Statera — no válido sin su registro</span>
        <span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>
    </div>
</div>
