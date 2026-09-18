<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Carbon;

/**
 * Congela el contexto tal como estaba el día en que se aprobó el análisis.
 *
 * **No es redundante con las tablas.** Las cuestiones y las partes viven y se
 * editan —tienen que hacerlo, o los vínculos a riesgos e implantaciones se
 * quedarían apuntando a filas muertas—, así que sin esto, retocar una debilidad en
 * 2027 cambiaría lo que dijo el análisis de 2026 y «¿qué ha cambiado en el
 * contexto?» —entrada obligatoria de la cláusula 9.3— no se contestaría. Es el
 * mismo razonamiento de `riesgo_valoraciones.salvaguardas` y de
 * `documento_versiones.instantanea`.
 *
 * **Y es lo que le da histórico al alcance sin tocar `sistemas`.** La cláusula 4.3
 * ya estaba resuelta con dos columnas de texto en `sistemas`, y lo único que le
 * faltaba era poder decir qué ponía el año pasado. Copiarlas aquí lo resuelve sin
 * una tabla nueva y sin migrar nada.
 *
 * Lo que se guarda son **arrays y cadenas, nunca modelos**, que es el mismo
 * contrato que tiene el generador de documentos con sus plantillas: lo que se pinta
 * y lo que se congela tienen que ser literalmente lo mismo.
 */
final class InstantaneaContexto
{
    /**
     * @return array<string, mixed>
     */
    public function para(AnalisisContexto $analisis): array
    {
        return [
            'congeladaEn' => Carbon::now()->toIso8601String(),
            'fechaAnalisis' => $analisis->fecha_analisis->toDateString(),
            'clima' => [
                'pertinente' => $analisis->clima_pertinente,
                'justificacion' => $analisis->clima_justificacion,
            ],
            'nota' => $analisis->nota,
            'dafo' => $this->dafo(),
            'partes' => $this->partes(),
            'alcance' => $this->alcance(),
        ];
    }

    /**
     * El DAFO, agrupado por cuadrante y en el orden en que se lee la matriz.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function dafo(): array
    {
        $cuestiones = CuestionContexto::query()
            ->vigentes()
            ->with(['responsable', 'riesgos'])
            ->withCount('tareas')
            ->orderBy('codigo')
            ->get();

        $dafo = [];

        foreach (TipoCuestion::enOrdenDeMatriz() as $tipo) {
            $dafo[$tipo->value] = $cuestiones
                ->where('tipo', $tipo)
                ->map(fn (CuestionContexto $cuestion): array => $this->cuestion($cuestion))
                ->values()
                ->all();
        }

        return $dafo;
    }

    /**
     * @return array<string, mixed>
     */
    private function cuestion(CuestionContexto $cuestion): array
    {
        return [
            'codigo' => $cuestion->codigo,
            'titulo' => $cuestion->titulo,
            'descripcion' => $cuestion->descripcion,
            'tipo' => $cuestion->tipo->value,
            'tipoEtiqueta' => $cuestion->tipo->etiqueta(),
            'ambito' => $cuestion->ambito()->value,
            'materia' => $cuestion->materia->value,
            'materiaEtiqueta' => $cuestion->materia->etiqueta(),
            'esClimatica' => $cuestion->es_climatica,
            'responsable' => $cuestion->responsable?->name,
            // Los códigos y no los identificadores: una instantánea que guardara
            // ids no se podría leer dentro de tres años sin la base al lado.
            'riesgos' => $cuestion->riesgos->pluck('codigo')->values()->all(),
            'tareas' => $cuestion->tareas_count,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partes(): array
    {
        return ParteInteresada::query()
            ->vigentes()
            ->with([
                'responsable',
                'requisitos.implantaciones.requisito',
                'requisitos.implantaciones.sistema',
            ])
            ->orderBy('codigo')
            ->get()
            ->map(fn (ParteInteresada $parte): array => [
                'codigo' => $parte->codigo,
                'nombre' => $parte->nombre,
                'tipo' => $parte->tipo->value,
                'tipoEtiqueta' => $parte->tipo->etiqueta(),
                'ambito' => $parte->ambito->value,
                'ambitoEtiqueta' => $parte->ambito->etiqueta(),
                'descripcion' => $parte->descripcion,
                'responsable' => $parte->responsable?->name,
                'requisitos' => $parte->requisitos
                    ->map(fn (RequisitoInteresado $requisito): array => $this->requisito($requisito))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function requisito(RequisitoInteresado $requisito): array
    {
        return [
            'descripcion' => $requisito->descripcion,
            'naturaleza' => $requisito->naturaleza->value,
            'naturalezaEtiqueta' => $requisito->naturaleza->etiqueta(),
            'obliga' => $requisito->naturaleza->obliga(),
            'esClimatico' => $requisito->es_climatico,
            'referencia' => $requisito->referencia,
            'comoSeAtiende' => $requisito->como_se_atiende,
            'implantaciones' => $requisito->implantaciones
                ->map(fn (Implantacion $implantacion): array => [
                    'requisito' => $implantacion->requisito?->codigo,
                    'titulo' => $implantacion->requisito?->titulo,
                    'sistema' => $implantacion->sistema?->codigo,
                    'estado' => $implantacion->estado->value,
                    'estadoEtiqueta' => $implantacion->estado->etiqueta(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * La cláusula 4.3, tal como estaba ese día.
     *
     * Sólo los sistemas **activos**: un sistema en borrador es un alcance que nadie
     * ha declarado todavía, y uno archivado ya no está dentro. Congelar los tres
     * dejaría el documento afirmando que el SGSI cubre algo que la organización
     * decidió sacar.
     *
     * @return list<array<string, mixed>>
     */
    private function alcance(): array
    {
        return Sistema::query()
            ->where('estado', EstadoSistema::Activo)
            ->with('marco')
            ->orderBy('codigo')
            ->get()
            ->map(fn (Sistema $sistema): array => [
                'codigo' => $sistema->codigo,
                'nombre' => $sistema->nombre,
                'marco' => $sistema->marco?->codigo,
                'alcanceDeclarado' => $sistema->alcance_declarado,
                'exclusiones' => $sistema->exclusiones_justificadas,
            ])
            ->values()
            ->all();
    }
}
