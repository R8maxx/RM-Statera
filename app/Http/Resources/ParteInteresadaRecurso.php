<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\TipoParteInteresada;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las partes interesadas y lo que exigen: la cláusula 4.2.
 *
 * La columna que de verdad se mira es **«Obligan»**, con su denominador: de todo lo
 * que este regulador nos pide, cuánto tiene una medida detrás. Un «0 de 4» es la
 * pregunta entera de la cláusula puesta en una celda.
 *
 * Los recuentos llegan **por subconsulta y no por join** —una parte con cuatro
 * requisitos saldría cuatro veces y la paginación contaría mal—, y van en SQL crudo
 * sin pasar por el scope de Eloquent: ahí quien filtra es RLS.
 *
 * @extends Recurso<ParteInteresada>
 */
final class ParteInteresadaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'partes-interesadas';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Parte interesada',
            plural: 'Partes interesadas',
            descripcion: 'Quién tiene algo que decir sobre la seguridad de la organización y qué le exige a cada uno. Es la cláusula 4.2 de ISO 27001.',
            vacio: 'No hay partes interesadas registradas. Empieza por quien os obliga: el regulador, el cliente grande, la propiedad.',
        );
    }

    /** @return Builder<ParteInteresada> */
    public function consulta(): Builder
    {
        $obligan = <<<'SQL'
            (select count(*) from requisitos_interesados ri
                where ri.parte_interesada_id = partes_interesadas.id
                  and ri.naturaleza in ('legal', 'contractual'))
        SQL;

        return ParteInteresada::query()
            ->select('partes_interesadas.*')
            ->selectRaw('(select count(*) from requisitos_interesados ri where ri.parte_interesada_id = partes_interesadas.id) as requisitos_total')
            ->selectRaw($obligan.' as requisitos_obligan')
            ->selectRaw(<<<SQL
                ({$obligan} - (select count(*) from requisitos_interesados ri
                    where ri.parte_interesada_id = partes_interesadas.id
                      and ri.naturaleza in ('legal', 'contractual')
                      and not exists (select 1 from implantacion_requisito_interesado iri
                          where iri.requisito_interesado_id = ri.id))) as obligan_cubiertos
            SQL)
            ->with('responsable');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('6rem'),

            Columna::texto('nombre', 'Parte interesada')->ordenable(),

            Columna::texto('tipo', 'Tipo')
                ->ordenable()
                ->ancho('11rem')
                ->formato(fn (ParteInteresada $fila): string => $fila->tipo->etiqueta()),

            Columna::texto('ambito', 'Ámbito')
                ->ordenable()
                ->ancho('7rem')
                ->formato(fn (ParteInteresada $fila): string => $fila->ambito->etiqueta()),

            Columna::texto('requisitos', 'Requisitos')
                ->alinear(Alineacion::Derecha)
                ->ancho('7rem')
                ->ayuda('Todo lo que esta parte exige o espera.')
                ->formato(fn (ParteInteresada $fila): string => (string) (int) $fila->getAttribute('requisitos_total')),

            /*
             * Con su denominador, como toda cifra del producto: «2» no dice nada y
             * «2 de 4» sí. Sólo cuenta lo que obliga —legal y contractual—, porque
             * una expectativa sin medida detrás no es una laguna, es una expectativa.
             */
            Columna::texto('obligan', 'Cubiertos')
                ->alinear(Alineacion::Derecha)
                ->ancho('8rem')
                ->ayuda('De lo que obliga (legal y contractual), cuánto tiene alguna implantación detrás.')
                ->formato(fn (ParteInteresada $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('obligan_cubiertos'),
                    (int) $fila->getAttribute('requisitos_obligan'),
                )),

            Columna::badge('situacion', 'Situación')
                ->ancho('8rem')
                ->formato(fn (ParteInteresada $fila): ValorEtiquetado => $fila->estaVigente()
                    ? new ValorEtiquetado('vigente', 'Vigente', 'implantado', 'CircleCheck')
                    : new ValorEtiquetado('retirada', 'Retirada', 'no_aplica', 'Archive')),

            Columna::texto('responsable', 'Quién la atiende')
                ->oculta()
                ->formato(fn (ParteInteresada $fila): ?string => $fila->responsable?->name),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'nombre' => 'nombre',
                'descripcion' => 'nombre',
            ])->placeholder('Buscar por código, nombre o descripción…'),

            Filtro::multiSelect('tipo', 'Tipo', array_map(
                static fn (TipoParteInteresada $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoParteInteresada::cases(),
            )),

            Filtro::select('ambito', 'Ámbito', array_map(
                static fn (Ambito $ambito): Opcion => new Opcion($ambito->value, $ambito->etiqueta()),
                Ambito::cases(),
            )),

            Filtro::porScope('vigentes', 'Sólo vigentes', 'vigentes')->enColumna('situacion'),
            Filtro::porScope('obligacion_sin_cubrir', 'Con obligaciones sin cubrir', 'conObligacionSinCubrir')->enColumna('obligan'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/partes-interesadas/{id}'),
            Accion::eliminar(
                '/partes-interesadas/{id}',
                '¿Eliminar la parte interesada? Se van con ella todos sus requisitos y los vínculos a las medidas que los cubrían. '
                .'Si ha dejado de tener relación con la organización, retírala con su motivo.',
            )->permiso(Permiso::ContextoGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva parte interesada', '/partes-interesadas/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ContextoGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }
}
