<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Abrir la tarea de remediar una vulnerabilidad. Los mismos campos que la de
 * una prueba de continuidad, que es la forma común de «tarea que nace de otro
 * registro».
 */
class DerivarTareaDeVulnerabilidadRequest extends DerivarTareaDePruebaRequest {}
