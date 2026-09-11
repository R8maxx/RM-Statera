{{--
    La tabla larga, compartida por las dos declaraciones.

    Las columnas llegan declaradas porque lo que cambia entre la SoA y la DdA son
    exactamente las columnas: en ISO la aplicabilidad es una decisión que hay que
    justificar, y en el ENS un cálculo que hay que poder rastrear. Lo demás
    —agrupación, badges, leyenda, cortes de página— es idéntico y se escribe una
    vez.

    `$columnas` es una lista de ['clave', 'titulo', 'ancho' => opcional].
--}}
<table class="tabla--fija">
    <thead>
        <tr>
            @foreach ($columnas as $columna)
                <th scope="col" @isset($columna['ancho']) style="width: {{ $columna['ancho'] }}" @endisset>
                    {{ $columna['titulo'] }}
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($contenido->filasPorGrupo() as $grupo => $filas)
            <tr class="grupo">
                <th scope="colgroup" colspan="{{ count($columnas) }}">{{ $grupo }}</th>
            </tr>

            @foreach ($filas as $fila)
                <tr>
                    @foreach ($columnas as $columna)
                        @php($clave = $columna['clave'])
                        <td @class(['codigo' => $clave === 'codigo'])>
                            @switch($clave)
                                @case('codigo')
                                    {{ $fila->codigo }}
                                    @break

                                @case('titulo')
                                    {{ $fila->titulo }}
                                    @break

                                @case('aplica')
                                    {{-- Nunca sólo color: el badge lleva punto y texto. --}}
                                    @if ($fila->aplica)
                                        Sí
                                    @else
                                        <strong>No</strong>
                                    @endif
                                    @break

                                @case('estado')
                                    <span class="badge badge--{{ $fila->estadoTono }}">{{ $fila->estadoEtiqueta }}</span>
                                    @break

                                @case('justificacionInclusion')
                                    {{ $fila->justificacionInclusion ?? '—' }}
                                    @break

                                @case('justificacion')
                                    {{ $fila->justificacion ?? ($fila->aplica ? '—' : 'SIN JUSTIFICAR') }}
                                    @break

                                @case('exigencia')
                                    @if ($fila->exigencia !== null)
                                        <span class="badge badge--neutro">{{ $fila->exigencia }}</span>
                                    @else
                                        —
                                    @endif
                                    @break

                                @case('origenExigencia')
                                    {{ $fila->origenExigencia ?? '—' }}
                                    @if ($fila->dimensionModuladora !== null)
                                        <div class="pequeno suave">Dimensión: {{ $fila->dimensionModuladora }}</div>
                                    @endif
                                    @break

                                @case('madurez')
                                    {{ $fila->madurez ?? '—' }}
                                    @break

                                @case('responsable')
                                    {{ $fila->responsable ?? 'Sin asignar' }}
                                    @break

                                @case('evidencias')
                                    {{-- Se dice explícitamente: una celda vacía se lee como un descuido. --}}
                                    @if ($fila->tieneEvidencia())
                                        {{ $fila->evidenciaODefecto() }}
                                    @else
                                        <span class="suave">Sin evidencia registrada</span>
                                    @endif
                                    @break

                                @case('correspondencias')
                                    @if ($fila->correspondencias === [])
                                        <span class="suave">—</span>
                                    @else
                                        <span class="cifra">{{ implode(', ', $fila->correspondencias) }}</span>
                                    @endif
                                    @break
                            @endswitch
                        </td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
