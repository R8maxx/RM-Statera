{{--
    Leyenda siempre que haya dos tramos o más. La identidad de un estado nunca
    depende sólo del color: DESIGN.md §3 deja tres estados por debajo de 4.5:1,
    y `implantado` y `en_progreso` no se distinguen con protanopia.
--}}
<div class="leyenda">
    @foreach ($segmentos as $segmento)
        @continue($segmento->valor === 0)
        <span class="badge badge--{{ $segmento->clave }}">{{ $segmento->etiqueta }}: {{ $segmento->valor }}</span>
    @endforeach
</div>
