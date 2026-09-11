@php($r = $contenido->resumen)
<section class="seccion">
    <h2>Resumen</h2>

    @include('documentos.parciales.narrativa', ['contenido' => $contenido, 'seccion' => 'nota_resumen'])

    <div class="cifras">
        <div class="cifras__dato">
            {{-- Con su denominador: el marco puede exigir menos de lo que tiene. --}}
            <div class="cifras__valor">
                {{ $r['total'] }}<span class="cifras__de"> de {{ $r['enElMarco'] }}</span>
            </div>
            <div class="cifras__etiqueta">{{ $etiquetaTotal ?? 'Requisitos' }}</div>
        </div>
        <div class="cifras__dato">
            <div class="cifras__valor">{{ $r['aplicables'] }}<span class="cifras__de"> de {{ $r['total'] }}</span></div>
            <div class="cifras__etiqueta">Aplicables</div>
        </div>
        <div class="cifras__dato">
            <div class="cifras__valor">{{ $r['excluidos'] }}</div>
            <div class="cifras__etiqueta">Excluidos</div>
        </div>
        <div class="cifras__dato">
            {{-- Toda cifra con su denominador: «41» no dice nada, «41 de 93» sí. --}}
            <div class="cifras__valor">{{ $r['implantados'] }}<span class="cifras__de"> de {{ $r['aplicables'] }}</span></div>
            <div class="cifras__etiqueta">Implantados{{ $r['porcentaje'] === null ? '' : ' · '.$r['porcentaje'].'%' }}</div>
        </div>
        <div class="cifras__dato">
            {{-- Sin ninguna valorada la media no es cero: es que no se sabe. --}}
            <div class="cifras__valor">
                {{ $r['madurezMedia'] === null ? '—' : 'L'.$r['madurezMedia'] }}<span class="cifras__de"> sobre {{ $r['madurezEvaluadas'] }}</span>
            </div>
            <div class="cifras__etiqueta">Madurez media</div>
        </div>
        <div class="cifras__dato">
            <div class="cifras__valor">{{ $r['sinEvidencia'] }}<span class="cifras__de"> de {{ $r['aplicables'] }}</span></div>
            <div class="cifras__etiqueta">Sin evidencia</div>
        </div>
    </div>

    @if ($r['total'] < $r['enElMarco'])
        <p class="suave">
            El marco tiene {{ $r['enElMarco'] }} requisitos y a este sistema se le exigen
            {{ $r['total'] }}. Los {{ $r['enElMarco'] - $r['total'] }} restantes no se le exigen por su
            categoría, y por eso no figuran en la tabla: la exigencia se deriva de la valoración de
            las cinco dimensiones, no se marca a mano.
        </p>
    @endif

    @if ($barra !== '')
        <div class="grafica">
            {!! $barra !!}
            @include('documentos.parciales.leyenda', ['segmentos' => $r['segmentos']])
        </div>
    @endif
</section>
