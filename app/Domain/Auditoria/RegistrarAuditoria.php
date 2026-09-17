<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Models\Auditoria;

/**
 * Da de alta una auditoría.
 *
 * Existe por una línea: el `refresh()`. `estado` lo pone la base con su valor por
 * defecto —repetirlo en el modelo sería el mismo dato en dos sitios que pueden
 * desincronizarse—, así que la instancia recién creada llega **sin estado**, y lo
 * primero que lo lee revienta con un «call to a member function on null» que no
 * menciona la palabra «estado».
 *
 * Es exactamente lo que ya le pasó a `CrearTarea` y a `GenerarDocumento::encolar()`,
 * y por eso los tres hacen lo mismo. Aquí mordió en el seeder, al precargar la
 * checklist de una auditoría recién dada de alta.
 */
final readonly class RegistrarAuditoria
{
    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos): Auditoria
    {
        $auditoria = Auditoria::query()->create($atributos);

        return $auditoria->refresh();
    }
}
