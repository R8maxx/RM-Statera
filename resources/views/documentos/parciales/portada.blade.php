@php($p = $contenido->portada)
<section class="portada">
    {{-- La única aparición del violeta en todo el documento (DESIGN.md §3). --}}
    <div class="portada__filete"></div>

    <div class="portada__marca">Statera</div>
    <h1 class="portada__titulo">{{ $contenido->titulo }}</h1>
    <p class="portada__subtitulo">{{ $contenido->subtitulo }}</p>

    @if ($p['esBorrador'] ?? false)
        <div class="caja" style="border-left: 3px solid var(--estado-en-progreso); background: var(--estado-en-progreso-suave);">
            <strong>Borrador — no es una entrega.</strong>
            Este PDF se regenera cada vez que se pide. Sólo las versiones emitidas quedan
            registradas de forma inmutable con su huella SHA-256.
        </div>
    @endif

    <div class="ficha">
        <div class="ficha__clave">Organización</div>
        <div class="ficha__valor">
            {{ $p['organizacion'] ?? '—' }}@if (! empty($p['cif'])) · CIF {{ $p['cif'] }}@endif
        </div>

        <div class="ficha__clave">Sistema</div>
        <div class="ficha__valor">
            @if (! empty($p['sistemaCodigo']))<span class="cifra">{{ $p['sistemaCodigo'] }}</span> · @endif
            {{ $p['sistemaNombre'] ?? '—' }}
        </div>

        <div class="ficha__clave">Marco</div>
        <div class="ficha__valor">{{ $p['marco'] ?? '—' }}@if (! empty($p['marcoVersion'])) ({{ $p['marcoVersion'] }})@endif</div>

        @isset($p['categoria'])
            <div class="ficha__clave">Categoría ENS</div>
            <div class="ficha__valor"><strong>{{ $p['categoria'] ?? 'Sin valorar' }}</strong></div>
        @endisset

        <div class="ficha__clave">Documento</div>
        <div class="ficha__valor"><span class="cifra">{{ $p['documentoCodigo'] ?? '—' }}</span> · {{ $p['version'] ?? '—' }}</div>

        <div class="ficha__clave">Fecha</div>
        <div class="ficha__valor">{{ $p['fecha'] ?? '—' }}</div>

        <div class="ficha__clave">Clasificación</div>
        <div class="ficha__valor">{{ $p['clasificacion'] ?? '—' }}</div>

        <div class="ficha__clave">Responsable</div>
        <div class="ficha__valor">{{ $p['responsable'] ?? 'Sin asignar' }}</div>
    </div>

    @if (! empty($p['alcance']))
        <div class="caja caja--marca">
            <h4>Alcance declarado</h4>
            <p>{{ $p['alcance'] }}</p>
            @if (! empty($p['exclusionesAlcance']))
                <h4>Exclusiones del alcance</h4>
                <p>{{ $p['exclusionesAlcance'] }}</p>
            @endif
        </div>
    @endif

    {{--
        Esta frase decía «No se mantiene a mano», y dejó de ser del todo cierta
        el día que la narrativa se volvió editable. Ahora distingue las dos
        cosas, porque la distinción es justo lo que el auditor necesita saber.
    --}}
    <div class="portada__pie pequeno suave">
        Statera — un producto de RM Technology.
        Las tablas, las cifras y la derivación de la categoría se generan desde el registro de
        implantaciones y no se mantienen a mano; los textos de presentación los redacta la
        organización.
    </div>
</section>
