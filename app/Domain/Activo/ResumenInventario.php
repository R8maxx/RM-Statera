<?php

declare(strict_types=1);

namespace App\Domain\Activo;

use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Http\Resources\Panel\IndicadorInventario;
use App\Http\Resources\Panel\RepartoInventario;
use App\Http\Resources\Panel\ResumenInventarioPanel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que hay que saber del inventario sin abrirlo.
 *
 * Vive en el dominio y no en el controlador por el mismo motivo que
 * `ResumenCumplimiento`: son las preguntas que contestará el informe de estado
 * y las que el auditor hace en voz alta.
 *
 * **Se cuenta sobre activos vigentes**, igual que el cumplimiento se cuenta
 * sobre lo exigible. Un portátil dado de baja sin copia de seguridad no está
 * pendiente de nada: está cerrado. La excepción es `porCicloDeVida()`, donde la
 * pregunta es justamente cuántos hay de cada cosa, retirados incluidos.
 *
 * **Antes esto devolvía nueve recuentos del mismo tamaño**, varios a cero y
 * ninguno con denominador. Había que leerse los nueve para saber si algo iba
 * mal, y mezclaban tres cosas distintas: incumplimiento real, dato que falta y
 * perfil del inventario. Ahora cada una tiene su método y su forma de pintarse.
 */
final class ResumenInventario
{
    /**
     * Lo que pide acción hoy. Nada más.
     *
     * Lo que falta por rellenar no entra aquí: son datos incompletos, no
     * incumplimientos, y mezclarlos hacía que un campo vacío pesara lo mismo
     * que un disco sin cifrar.
     *
     * @return list<IndicadorInventario>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'sin_cifrado',
                'Sin cifrado en reposo',
                'sinCifrado',
                'caducada',
                'Activos que declaran no cifrar. Los que están «por confirmar» no cuentan aquí: son una pregunta abierta, no un incumplimiento.',
            ),
            $this->indicador(
                'sin_copia',
                'Sin copia de seguridad',
                'sinCopia',
                'caducada',
            ),
            $this->indicador(
                'sin_soporte',
                'Soporte o garantía vencidos',
                'sinSoporte',
                'caducada',
                'Sistema operativo que ya no recibe parches, o equipo fuera de garantía. Es op.exp.4.',
            ),
            $this->indicador(
                'espera_borrado',
                'Retirados sin borrado seguro',
                'esperaBorradoSeguro',
                'caducada',
                'Ya no prestan servicio y nadie ha registrado qué se hizo con lo que contenían. Es mp.si.5, y el soporte sigue por ahí con los datos dentro.',
                sobreVigentes: false,
            ),
            $this->indicador(
                'sin_revisar',
                'Sin revisar en 12 meses',
                'sinRevisar',
                'en_progreso',
                'Incluye los que no se han revisado nunca desde el alta.',
            ),
        ];
    }

    /**
     * Lo que falta por rellenar.
     *
     * Se separa de las alertas porque es otra clase de deuda: aquí no hay nada
     * roto, hay una ficha a medias. En la interfaz va en una línea de texto y
     * no en tarjetas, para que no compita con lo que sí arde.
     *
     * @return list<IndicadorInventario>
     */
    public function pendientesDeCompletar(): array
    {
        return [
            $this->indicador('sin_propietario', 'propietario', 'sinPropietario', 'en_progreso'),
            $this->indicador('sin_identificador', 'nº de serie', 'sinIdentificador', 'en_progreso'),
            $this->indicador('sin_ubicacion', 'ubicación', 'sinUbicacion', 'en_progreso'),
        ];
    }

    /** Todo lo que el panel necesita, resuelto de una vez. */
    public function paraElPanel(): ResumenInventarioPanel
    {
        return new ResumenInventarioPanel(
            vigentes: $this->vigentes(),
            resueltos: $this->controlesResueltos(),
            restringidos: Activo::query()->vigentes()->informacionRestringida()->count(),
            cifrado: $this->cobertura('cifrado'),
            copia: $this->cobertura('copia_seguridad'),
            porTipo: $this->porTipo(),
            porCicloDeVida: $this->porCicloDeVida(),
        );
    }

    /** Cuántos activos vigentes hay. El denominador de casi todo lo demás. */
    public function vigentes(): int
    {
        return Activo::query()->vigentes()->count();
    }

    /**
     * Activos cuyos dos controles están **decididos**: ni «no» ni «por
     * confirmar».
     *
     * `no_aplica` cuenta como resuelto, y no es una concesión: un router no
     * cifra en reposo porque no almacena nada, y contarlo como pendiente
     * pondría un techo que la organización no puede alcanzar por mucho que
     * trabaje. Un indicador que nunca puede llegar al cien por cien se deja de
     * mirar.
     */
    public function controlesResueltos(): int
    {
        $decididos = [EstadoControl::Si->value, EstadoControl::NoAplica->value];

        return Activo::query()
            ->vigentes()
            ->whereIn('cifrado', $decididos)
            ->whereIn('copia_seguridad', $decididos)
            ->count();
    }

    /**
     * El reparto de un control por sus cuatro valores.
     *
     * Sustituye a tres cifras sueltas —sin cifrado, sin copia, por confirmar— y
     * enseña de un vistazo cuánto es incumplimiento y cuánto es duda todavía sin
     * resolver, que es la distinción que el enum existe para sostener.
     *
     * @param  'cifrado'|'copia_seguridad'  $columna
     * @return list<RepartoInventario>
     */
    public function cobertura(string $columna): array
    {
        $conteos = $this->contar($columna);
        $filtro = $columna === 'cifrado' ? 'cifrado' : 'copia_seguridad';

        return array_map(
            static fn (EstadoControl $control): RepartoInventario => new RepartoInventario(
                clave: $control->value,
                etiqueta: $control->etiqueta(),
                valor: $conteos[$control->value] ?? 0,
                tono: $control->tono(),
                filtro: "filter[{$filtro}]={$control->value}",
            ),
            EstadoControl::cases(),
        );
    }

    /**
     * Cuántos activos hay de cada tipo MAGERIT.
     *
     * Es la hoja «activos por categoría» de la hoja de cálculo de la que viene
     * el módulo, que Statera había perdido por el camino. Los tipos a cero **no**
     * se pintan: nueve barras de las que seis están vacías no es un reparto, es
     * una lista de tipos.
     *
     * @return list<RepartoInventario>
     */
    public function porTipo(): array
    {
        $conteos = $this->contar('tipo');

        $filas = array_map(
            static fn (TipoActivo $tipo): RepartoInventario => new RepartoInventario(
                clave: $tipo->value,
                etiqueta: $tipo->etiqueta(),
                valor: $conteos[$tipo->value] ?? 0,
                tono: 'tipo:'.$tipo->value,
                filtro: "filter[tipo]={$tipo->value}",
            ),
            TipoActivo::cases(),
        );

        return $this->conValor($filas);
    }

    /**
     * El reparto por ciclo de vida, **retirados incluidos**.
     *
     * Aquí la pregunta es cuántos hay de cada cosa, así que la criba de
     * vigentes no aplica: lo interesante de esta gráfica es precisamente el
     * parque que ya no está en uso y sigue en un armario.
     *
     * @return list<RepartoInventario>
     */
    public function porCicloDeVida(): array
    {
        $conteos = $this->contar('estado_ciclo_vida', soloVigentes: false);

        $filas = array_map(
            static fn (EstadoCicloVida $estado): RepartoInventario => new RepartoInventario(
                clave: $estado->value,
                etiqueta: $estado->etiqueta(),
                valor: $conteos[$estado->value] ?? 0,
                tono: $estado->tono(),
                filtro: "filter[estado_ciclo_vida]={$estado->value}",
            ),
            EstadoCicloVida::cases(),
        );

        return $this->conValor($filas);
    }

    /**
     * Un `GROUP BY` por columna, no una consulta por casilla.
     *
     * @return array<string, int>
     */
    private function contar(string $columna, bool $soloVigentes = true): array
    {
        $consulta = Activo::query()->when($soloVigentes, fn (Builder $q) => $q->vigentes());

        return $consulta
            ->groupBy($columna)
            ->selectRaw("{$columna} as clave, count(*) as total")
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @param  list<RepartoInventario>  $filas
     * @return list<RepartoInventario>
     */
    private function conValor(array $filas): array
    {
        return array_values(array_filter($filas, static fn (RepartoInventario $fila): bool => $fila->valor > 0));
    }

    /**
     * Cada indicador cuenta con **el mismo scope** que usa su filtro de la tabla.
     *
     * No es una comodidad: es lo que garantiza que pulsar una cifra enseñe
     * exactamente esa cifra. Con la condición escrita dos veces, el día que una
     * de las dos cambie el panel dirá 12 y la lista enseñará 9, y a partir de ahí
     * nadie vuelve a fiarse del panel.
     */
    private function indicador(
        string $clave,
        string $etiqueta,
        string $scope,
        string $tono,
        ?string $ayuda = null,
        bool $sobreVigentes = true,
    ): IndicadorInventario {
        $consulta = Activo::query()->when($sobreVigentes, fn (Builder $q) => $q->vigentes());

        return new IndicadorInventario(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: $consulta->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            ayuda: $ayuda,
        );
    }
}
