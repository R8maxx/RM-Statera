{{--
    Repite, completas, las filas excluidas que ya salen en la tabla general.

    La duplicación es deliberada: ésta es la sección que el auditor abre primero,
    porque la cláusula 6.1.3 d) le obliga a comprobar que toda exclusión del
    Anexo A está justificada. Obligarle a filtrar noventa y tres filas para
    encontrar cuatro sería hacerle trabajar de más para lo único que va a mirar
    seguro.
--}}
<section class="seccion">
    <h2>Controles excluidos y su justificación</h2>

    @php($excluidas = $contenido->excluidas())

    @if ($excluidas === [])
        <p class="vacio">
            No se ha excluido ningún control del Anexo A. Los {{ $contenido->resumen['total'] }} controles
            son aplicables al sistema.
        </p>
    @else
        <p class="suave">
            {{ count($excluidas) }} de {{ $contenido->resumen['total'] }} controles quedan fuera del alcance,
            cada uno con el motivo que registró la organización.
        </p>

        @include('documentos.parciales.narrativa', ['contenido' => $contenido, 'seccion' => 'nota_exclusiones'])

        <table class="tabla--fija">
            <thead>
                <tr>
                    <th scope="col" style="width: 0.9in">Control</th>
                    <th scope="col" style="width: 2.6in">Título</th>
                    <th scope="col">Justificación de la exclusión</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($excluidas as $fila)
                    <tr>
                        <td class="codigo">{{ $fila->codigo }}</td>
                        <td>{{ $fila->titulo }}</td>
                        <td>{{ $fila->justificacion ?? 'SIN JUSTIFICAR' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
