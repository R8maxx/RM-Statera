@if ($contenido->limitaciones !== [])
    <section class="seccion">
        <h2>Limitaciones de esta declaración</h2>
        <p class="suave">
            Lo que este documento <strong>no</strong> puede afirmar hoy, y por qué. Se declara
            expresamente en lugar de omitirse.
        </p>
        <div class="limitaciones">
            <ul>
                @foreach ($contenido->limitaciones as $limitacion)
                    <li>{!! \App\Domain\Documento\Contenido\ContenidoDocumento::realce($limitacion) !!}</li>
                @endforeach
            </ul>
        </div>

        {{--
            Las de la organización van DEBAJO y en su propio bloque, nunca
            mezcladas con las anteriores ni en lugar de ellas: saber qué declara
            la herramienta y qué declara la organización es parte de lo que se
            está declarando. Las del sistema no se pueden quitar desde ninguna
            parte —no hay hueco que apunte a ellas—, y esto es lo que permite
            añadir sin necesidad de borrar.
        --}}
        @if ($contenido->textos->tiene('limitaciones_propias'))
            <h3>Limitaciones declaradas por la organización</h3>
            <div class="limitaciones narrativa">
                {!! $contenido->textos->html('limitaciones_propias') !!}
            </div>
        @endif
    </section>
@endif
