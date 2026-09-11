{{--
    Las dos brechas de modelo que el catálogo declara, impresas en el documento.

    Una DdA que exige de más en silencio, o que se come una alternativa entre
    refuerzos, es exactamente lo que un auditor detecta con el Anexo II delante.
    Declararlo convierte un fallo silencioso en una nota al pie.
--}}
<section class="seccion">
    <h2>Notas sobre la lectura del Anexo II</h2>

    <div class="limitaciones">
        <ul>
            @foreach ($contenido->extras['notasAnexoII'] ?? [] as $nota)
                <li>{!! \App\Domain\Documento\Contenido\ContenidoDocumento::realce($nota) !!}</li>
            @endforeach
        </ul>
    </div>

    <h3 style="margin-top: 0.2in">Madurez por marco</h3>
    @include('documentos.parciales.narrativa', ['contenido' => $contenido, 'seccion' => 'nota_madurez'])

    <table>
        <thead>
            <tr>
                <th scope="col" style="width: 0.6in">Marco</th>
                <th scope="col" style="width: 2.2in">Nombre</th>
                <th scope="col" style="width: 1in">Exigibles</th>
                <th scope="col" style="width: 1in">Evaluadas</th>
                <th scope="col">Madurez media</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($contenido->extras['madurezPorMarco'] ?? [] as $marco)
                <tr>
                    <td class="codigo">{{ $marco['codigo'] }}</td>
                    <td>{{ $marco['nombre'] }}</td>
                    <td>{{ $marco['exigibles'] }}</td>
                    <td>{{ $marco['evaluadas'] }} de {{ $marco['exigibles'] }}</td>
                    {{-- Sin ninguna evaluada la media no es L0: es que no se sabe. --}}
                    <td>{{ $marco['media'] === null ? 'Sin evaluar' : 'L'.$marco['media'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
