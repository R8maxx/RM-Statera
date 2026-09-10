<?php

declare(strict_types=1);

namespace App\Domain\Activo;

use App\Domain\Activo\Models\Activo;
use App\Http\Resources\Panel\IndicadorInventario;

/**
 * Los indicadores de control del inventario.
 *
 * Vive en el dominio y no en el controlador por el mismo motivo que
 * `ResumenCumplimiento`: son las preguntas que contestará el informe de estado
 * y las que el auditor hace en voz alta. El controlador sólo las lanza.
 *
 * **Todo se cuenta sobre activos vigentes**, igual que el cumplimiento se cuenta
 * sobre lo exigible. Un portátil dado de baja sin copia de seguridad no está
 * pendiente de nada: está cerrado. Contarlo inflaría las cifras con trabajo que
 * no existe y escondería el que sí. Lo que un activo retirado sí puede deber es
 * el borrado seguro, y eso lo señala `Activo::esperaBorradoSeguro()` en su
 * ficha, que es donde se acciona.
 *
 * Ocho indicadores salen tal cual de la hoja «Resumen» del Excel. El noveno de
 * aquella —«instancias detenidas», coste de AWS sin uso— es de un importador que
 * todavía no existe; en su lugar va **soporte o garantía vencidos**, que
 * responde a la misma pregunta —algo que ya no debería seguir así— y además es
 * `op.exp.4`.
 */
final class ResumenInventario
{
    /**
     * @return list<IndicadorInventario>
     */
    public function indicadores(): array
    {
        return [
            $this->indicador(
                'sin_cifrado',
                'Sin cifrado en reposo',
                'sinCifrado',
                'caducada',
                'Activos que declaran no cifrar. Los que están «por confirmar» se cuentan aparte.',
            ),
            $this->indicador(
                'sin_copia',
                'Sin copia de seguridad',
                'sinCopia',
                'caducada',
            ),
            $this->indicador(
                'por_confirmar',
                'Cifrado o copia por confirmar',
                'controlPorConfirmar',
                'en_progreso',
                'Preguntas abiertas, no incumplimientos: nadie ha dicho que no, es que nadie lo ha comprobado.',
            ),
            $this->indicador(
                'sin_propietario',
                'Sin propietario asignado',
                'sinPropietario',
                'en_progreso',
                'Un activo del que no responde nadie es un activo que nadie revisa.',
            ),
            $this->indicador(
                'sin_identificador',
                'Sin nº de serie ni identificador',
                'sinIdentificador',
                'en_progreso',
                'Sin el nº de serie, el ARN o el hostname no se puede demostrar que la fila y el aparato son el mismo.',
            ),
            $this->indicador(
                'sin_ubicacion',
                'Sin ubicación',
                'sinUbicacion',
                'en_progreso',
            ),
            $this->indicador(
                'sin_revisar',
                'Sin revisar en 12 meses',
                'sinRevisar',
                'caducada',
                'Incluye los que no se han revisado nunca desde el alta.',
            ),
            $this->indicador(
                'restringida',
                'Con información restringida',
                'informacionRestringida',
                'alta',
                'No es un problema: es lo que hay que vigilar de cerca.',
            ),
            $this->indicador(
                'sin_soporte',
                'Soporte o garantía vencidos',
                'sinSoporte',
                'caducada',
                'Sistema operativo que ya no recibe parches, o equipo fuera de garantía. Es op.exp.4.',
            ),
        ];
    }

    /** Cuántos activos vigentes hay en total. El denominador de todo lo demás. */
    public function vigentes(): int
    {
        return Activo::query()->vigentes()->count();
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
    ): IndicadorInventario {
        return new IndicadorInventario(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Activo::query()->vigentes()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            ayuda: $ayuda,
        );
    }
}
