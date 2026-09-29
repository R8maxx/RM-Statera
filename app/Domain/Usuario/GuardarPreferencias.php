<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Usuario\Enums\PaginaInicio;
use App\Domain\Usuario\Enums\Tema;
use App\Models\User;

/**
 * Guarda las preferencias de la cuenta propia.
 *
 * Sin traza, a propósito: el tema o la página de inicio no son un cambio del
 * SGSI, y la traza que registra cada pulsación del interruptor de tema deja de
 * leerse. `saveQuietly()` por lo mismo.
 */
final class GuardarPreferencias
{
    /**
     * @param  array{tema?: string, pagina_inicio?: string, avisos_por_correo?: bool}  $datos
     */
    public function __invoke(User $cuenta, array $datos): void
    {
        if (array_key_exists('tema', $datos)) {
            $cuenta->tema = Tema::from($datos['tema']);
        }

        if (array_key_exists('pagina_inicio', $datos)) {
            $cuenta->pagina_inicio = PaginaInicio::from($datos['pagina_inicio']);
        }

        if (array_key_exists('avisos_por_correo', $datos)) {
            $cuenta->avisos_por_correo = (bool) $datos['avisos_por_correo'];
        }

        $cuenta->saveQuietly();
    }
}
