<?php

declare(strict_types=1);

namespace App\Domain\Categorizacion\Enums;

/**
 * Por qué se le exige a una organización una medida concreta.
 *
 * No es decorativo: cuando el auditor pregunte de dónde sale la exigencia de
 * `op.exp.10`, la respuesta tiene que estar en el dato, no en releer la matriz
 * del Anexo II a mano.
 */
enum OrigenExigencia: string
{
    /** La dicta la categoría del sistema, que es el máximo de las cinco dimensiones. */
    case Categoria = 'categoria';

    /** La dicta el nivel de una dimensión concreta, no la categoría global. */
    case ModulacionDimension = 'modulacion_dimension';

    /** El perfil de cumplimiento endurece lo que dictaba la categoría. */
    case Perfil = 'perfil';

    /**
     * El requisito forma parte del marco y punto: no hay matriz que derivar.
     *
     * Es el caso de ISO 27001, donde la Declaración de Aplicabilidad parte de
     * que los 93 controles del Anexo A aplican y excluir uno es una decisión
     * motivada. No contradice el invariante 4, que habla de la categorización
     * del ENS.
     */
    case Catalogo = 'catalogo';

    /**
     * La respuesta a «¿de dónde sale esta exigencia?», en una línea.
     *
     * Sin esto la columna enseñaba `modulacion_dimension` tal cual, que es un
     * identificador, no una explicación.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Categoria => 'Categoría del sistema',
            self::ModulacionDimension => 'Modulación por dimensión',
            self::Perfil => 'Perfil de cumplimiento',
            self::Catalogo => 'El propio marco',
        };
    }
}
