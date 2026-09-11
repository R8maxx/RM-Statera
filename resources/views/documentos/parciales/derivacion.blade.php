{{--
    El corazón de la DdA: de dónde sale la categoría.

    La aplicabilidad del ENS no se marca a mano, se deriva de valorar las cinco
    dimensiones (invariante 4). Enseñar la categoría sin las cinco dimensiones y
    sus justificaciones obligaría al auditor a fiarse, y un auditor no se fía:
    comprueba.
--}}
@php($d = $contenido->extras['derivacion'] ?? [])

<section class="seccion">
    <h2>Categorización del sistema</h2>

    @if (($d['dimensiones'] ?? []) === [])
        <p class="vacio">
            El sistema no tiene valoradas las cinco dimensiones, así que no hay categoría de la que
            derivar el conjunto de medidas exigibles.
        </p>
    @else
        <div class="caja caja--marca">
            <h4>Derivación</h4>
            <p style="font-size: 10pt; margin-bottom: 0">
                <strong>{{ $d['formula'] ?? '—' }}</strong>
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th scope="col" style="width: 0.5in">Dim.</th>
                    <th scope="col" style="width: 1.7in">Dimensión</th>
                    <th scope="col" style="width: 0.9in">Nivel</th>
                    <th scope="col">Justificación de la valoración</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($d['dimensiones'] as $dimension)
                    <tr>
                        <td class="codigo">{{ $dimension['codigo'] }}</td>
                        <td>{{ $dimension['nombre'] }}</td>
                        <td>{{ $dimension['nivel'] }}</td>
                        <td>{{ $dimension['justificacion'] ?? 'Sin justificar' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @include('documentos.parciales.narrativa', ['contenido' => $contenido, 'seccion' => 'nota_derivacion'])
    @endif
</section>
