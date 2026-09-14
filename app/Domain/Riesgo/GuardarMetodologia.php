<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Riesgo\Models\MetodologiaRiesgo;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Guarda la metodología de la organización — o borra la fila si coincide con la
 * de fábrica.
 *
 * **Ese borrado no es una optimización.** Sin él bastaría con abrir la pantalla y
 * darle a guardar para que la metodología de esa organización quedara congelada y
 * dejara de recibir cualquier mejora futura de la de Statera, sin haberlo decidido
 * y sin enterarse. «No lo he tocado» y «no hay fila» tienen que ser lo mismo, que
 * es exactamente lo que hace `GuardarPlantilla` con los textos de un documento.
 *
 * La aprobación es la excepción: una fila **aprobada** no se borra aunque su
 * contenido coincida con el de fábrica. Ahí ya no dice «no lo he tocado», dice «lo
 * he mirado y lo firmo», y esa firma con su fecha es precisamente lo que el
 * auditor pide. Perderla por parecerse a la de fábrica sería el peor borrado
 * posible.
 */
final class GuardarMetodologia
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly MetodologiaVigente $vigente,
    ) {}

    /**
     * @param  array{
     *     nombre: string,
     *     referencia?: ?string,
     *     escala_probabilidad: list<array{valor: int, etiqueta: string, descripcion?: ?string}>,
     *     escala_impacto: list<array{valor: int, etiqueta: string, descripcion?: ?string}>,
     *     umbral_aceptacion: int,
     *     umbral_critico: int,
     *     periodicidad_revision_meses: int,
     *     notas?: ?string,
     * }  $datos
     */
    public function __invoke(array $datos, ?User $aprobador = null): ?MetodologiaRiesgo
    {
        $fila = MetodologiaRiesgo::query()->first();

        $atributos = [
            'organizacion_id' => $this->contexto->idObligatorio(),
            'nombre' => $datos['nombre'],
            'referencia' => $datos['referencia'] ?? null,
            'escala_probabilidad' => $datos['escala_probabilidad'],
            'escala_impacto' => $datos['escala_impacto'],
            'umbral_aceptacion' => $datos['umbral_aceptacion'],
            'umbral_critico' => $datos['umbral_critico'],
            'periodicidad_revision_meses' => $datos['periodicidad_revision_meses'],
            'notas' => $datos['notas'] ?? null,
        ];

        /*
         * Se construye el value object ANTES de escribir: es quien valida que las
         * escalas sean contiguas y que los umbrales tengan sentido. Que lo haga el
         * dominio y no el FormRequest es lo que hace que la regla valga también
         * para un seeder o un importador.
         */
        $propuesta = (new MetodologiaRiesgo)->forceFill($atributos)->aValueObject();

        $aprobacion = $this->aprobacion($aprobador);

        if ($aprobador === null && $propuesta->equivale(MetodologiaDeFabrica::metodologia())) {
            $fila?->delete();
            $this->vigente->olvidar();

            return null;
        }

        $fila = $fila === null
            ? MetodologiaRiesgo::query()->create([...$atributos, ...$aprobacion])
            : tap($fila)->update([...$atributos, ...$aprobacion]);

        $this->vigente->olvidar();

        return $fila->refresh();
    }

    /**
     * Quién y cuándo la aprobó.
     *
     * **Tocar la metodología invalida la aprobación anterior**: la dirección firmó
     * unas escalas y unos umbrales concretos, no un formulario. Por eso la firma se
     * pierde al guardar salvo que se vuelva a firmar en el mismo acto. Es la misma
     * idea que hay detrás de que un documento materializado no se entere si la
     * plantilla cambia después.
     *
     * @return array{aprobada_por_id: ?int, aprobada_en: ?Carbon}
     */
    private function aprobacion(?User $aprobador): array
    {
        if ($aprobador instanceof User) {
            return ['aprobada_por_id' => $aprobador->id, 'aprobada_en' => Carbon::today()];
        }

        return ['aprobada_por_id' => null, 'aprobada_en' => null];
    }
}
