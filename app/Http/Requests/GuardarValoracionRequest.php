<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La única fuente de verdad de la validación de una valoración.
 *
 * Las cinco dimensiones son obligatorias. No valorar una y valorarla `na` son lo
 * mismo a efectos del Anexo I, pero dejar el campo vacío no deja constancia de
 * que alguien lo haya decidido: la categoría del sistema —y con ella todo lo que
 * se le exige— sale de aquí.
 *
 * La categoría NO se acepta como entrada. Se deriva (invariante 4).
 */
class GuardarValoracionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [];

        foreach (Dimension::cases() as $dimension) {
            $reglas[$this->claveNivel($dimension)] = ['required', Rule::enum(NivelDimension::class)];
            $reglas[$this->claveJustificacion($dimension)] = ['nullable', 'string', 'max:2000'];
        }

        return $reglas;
    }

    /** La valoración validada, como value object: la entrada del motor. */
    public function valoracion(): ValoracionDimensiones
    {
        $niveles = [];

        foreach (Dimension::cases() as $dimension) {
            $niveles[$dimension->value] = $this->string($this->claveNivel($dimension))->toString();
        }

        return ValoracionDimensiones::desdeArray($niveles);
    }

    /** @return array<string, ?string> Indexadas por código de dimensión. */
    public function justificaciones(): array
    {
        $justificaciones = [];

        foreach (Dimension::cases() as $dimension) {
            $texto = trim((string) $this->input($this->claveJustificacion($dimension), ''));

            $justificaciones[$dimension->value] = $texto === '' ? null : $texto;
        }

        return $justificaciones;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $atributos = [];

        foreach (Dimension::cases() as $dimension) {
            $nombre = mb_strtolower($dimension->nombre());

            $atributos[$this->claveNivel($dimension)] = "nivel de {$nombre}";
            $atributos[$this->claveJustificacion($dimension)] = "justificación de {$nombre}";
        }

        return $atributos;
    }

    /**
     * Claves planas y no anidadas (`nivel_C`, no `dimensiones[C][nivel]`) para
     * que el nombre del control y la clave del error coincidan: es lo que usa el
     * formulario para llevar el foco al campo que falla.
     */
    private function claveNivel(Dimension $dimension): string
    {
        return "nivel_{$dimension->value}";
    }

    private function claveJustificacion(Dimension $dimension): string
    {
        return "justificacion_{$dimension->value}";
    }
}
