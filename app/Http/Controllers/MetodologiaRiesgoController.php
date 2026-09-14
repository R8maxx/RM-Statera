<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Riesgo\CalculoRiesgo;
use App\Domain\Riesgo\GuardarMetodologia;
use App\Domain\Riesgo\MetodologiaDeFabrica;
use App\Domain\Riesgo\MetodologiaVigente;
use App\Http\Requests\GuardarMetodologiaRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Con qué mide los riesgos la organización.
 *
 * Una pantalla y no una pestaña del registro: es una decisión de dirección que se
 * toma una vez y se revisa al año, no algo que se toque mientras se trabaja. Y
 * tiene permiso propio —`riesgos.aceptar`— porque fijar el apetito de riesgo es
 * decidir de antemano qué se va a poder aceptar.
 *
 * **La pantalla avisa cuando la metodología es la de fábrica**, igual que la de
 * plantillas avisa de que un documento ya materializado no se entera si la
 * plantilla cambia. Sin ese aviso, cualquiera daría por hecho que la escala que
 * está viendo la aprobó alguien de la casa.
 */
class MetodologiaRiesgoController extends Controller
{
    public function edit(MetodologiaVigente $vigente, CalculoRiesgo $calculo): Response
    {
        $metodologia = $vigente->para();
        $fila = $vigente->fila();

        return Inertia::render('riesgos/Metodologia', [
            'metodologia' => [
                'nombre' => $metodologia->nombre,
                'referencia' => $metodologia->referencia,
                'escala_probabilidad' => $metodologia->probabilidad->aArray(),
                'escala_impacto' => $metodologia->impacto->aArray(),
                'umbral_aceptacion' => $metodologia->umbralAceptacion,
                'umbral_critico' => $metodologia->umbralCritico,
                'periodicidad_revision_meses' => $metodologia->periodicidadRevisionMeses,
                'notas' => $fila?->notas,
            ],

            /*
             * Las tres cosas que el usuario tiene que saber antes de tocar nada:
             * si lo que ve es de fábrica, si alguien lo ha firmado, y que cambiarlo
             * NO revalúa lo ya medido —cada valoración se lleva su escala dentro—.
             */
            'esDeFabrica' => $metodologia->esDeFabrica,
            'estaAprobada' => $metodologia->estaAprobada(),
            'aprobadaPor' => $metodologia->aprobadaPor,
            'aprobadaEn' => $metodologia->aprobadaEn?->toDateString(),

            'bandas' => $calculo->bandas($metodologia),
            'riesgoMaximo' => $metodologia->riesgoMaximo(),

            'fabrica' => [
                'nombre' => MetodologiaDeFabrica::NOMBRE,
                'escala_probabilidad' => MetodologiaDeFabrica::probabilidad()->aArray(),
                'escala_impacto' => MetodologiaDeFabrica::impacto()->aArray(),
                'umbral_aceptacion' => MetodologiaDeFabrica::UMBRAL_ACEPTACION,
                'umbral_critico' => MetodologiaDeFabrica::UMBRAL_CRITICO,
                'periodicidad_revision_meses' => MetodologiaDeFabrica::PERIODICIDAD_MESES,
            ],
        ]);
    }

    public function update(GuardarMetodologiaRequest $request, GuardarMetodologia $guardar): RedirectResponse
    {
        $datos = $request->validated();
        $aprobar = (bool) ($datos['aprobar'] ?? false);
        unset($datos['aprobar']);

        /** @var array{nombre: string, escala_probabilidad: list<array{valor: int, etiqueta: string}>, escala_impacto: list<array{valor: int, etiqueta: string}>, umbral_aceptacion: int, umbral_critico: int, periodicidad_revision_meses: int} $datos */
        $fila = $guardar($datos, $aprobar ? $request->user() : null);

        Inertia::flash('exito', $fila === null
            // Que no quede fila es el comportamiento correcto y hay que decirlo, o
            // parecerá que no se ha guardado nada.
            ? 'La metodología coincide con la de partida de Statera, así que no se ha guardado ninguna: la organización sigue recibiendo sus mejoras.'
            : 'Metodología guardada. Las valoraciones ya hechas conservan la escala con la que se midieron.');

        return to_route('riesgos.metodologia.edit');
    }
}
