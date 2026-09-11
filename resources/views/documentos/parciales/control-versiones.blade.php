<section class="seccion">
    <h2>Control de versiones</h2>

    @if ($contenido->historial === [])
        <p class="vacio">No hay versiones emitidas anteriores. Ésta sería la primera entrega.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 0.7in">Versión</th>
                    <th style="width: 1in">Emitida</th>
                    <th style="width: 1.6in">Emitida por</th>
                    <th>Motivo</th>
                    <th style="width: 3.4in">SHA-256</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contenido->historial as $version)
                    <tr>
                        <td class="codigo">v{{ $version['numero'] }}</td>
                        <td>{{ $version['emitida'] ?? '—' }}</td>
                        <td>{{ $version['quien'] ?? '—' }}</td>
                        <td>{{ $version['motivo'] ?? '—' }}</td>
                        <td class="huella">{{ $version['huella'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{--
        La huella de ESTA versión no puede ir aquí: sería autorreferencia, porque
        el SHA-256 se calcula sobre el PDF ya generado. Vive en el registro de la
        herramienta y en la ficha del documento.
    --}}
    <p class="pequeno suave" style="margin-top: 0.12in">
        La huella SHA-256 de esta versión se calcula sobre el PDF ya generado, así que no
        puede figurar dentro de él. Consta en el registro del documento en Statera, y es con
        ella con la que se comprueba que este fichero es el que se emitió.
    </p>
</section>
