<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Aviso\DestinatariosDelResumen;
use App\Domain\Documento\Models\Documento;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Usuario\Enums\PaginaInicio;
use App\Domain\Usuario\Models\CuentaSistema;
use App\Models\User;

/**
 * Lo que el menú de la cuenta enseña al abrirse: qué hay a tu nombre, cuándo
 * entraste la última vez y, al auditor, hasta cuándo y sobre qué.
 *
 * **Se pide al abrir el menú, no en cada página.** Son cinco consultas, y
 * como prop compartido correrían en cada navegación y en cada recarga parcial
 * de una tabla para un menú que casi nunca se abre.
 *
 * **Cada cifra sale del mismo scope que filtra la tabla a la que lleva**:
 * `Tarea::abiertas()` y `vencidas()`, `Documento::pendientesDeMiAcuse()` y
 * `enRevision()`. Con la condición escrita dos veces, el menú diría 3 y la tabla
 * enseñaría 2, y a partir de ahí nadie se fía de ninguno — lo mismo que ya
 * dicen el panel y el aviso diario.
 *
 * Todo corre dentro del contexto de la petición, así que las tres capas y el
 * alcance del auditor ya acotan: un auditor no cuenta documentos de un sistema
 * que no audita.
 */
final class LoPendienteDeLaCuenta
{
    public function __construct(private readonly AccesosRecientes $accesos) {}

    /**
     * @return array{
     *     rol: ?string,
     *     tareas: array{abiertas: int, vencidas: int, url: string}|null,
     *     porLeer: array{total: int, url: string},
     *     porFirmar: array{total: int, url: string}|null,
     *     avisos: array{activos: bool}|null,
     *     alcance: array{hasta: ?string, sistemas: list<string>}|null,
     *     entradaAnterior: array{fecha: string, ip: ?string, fallidosDesde: int}|null,
     * }
     */
    public function de(User $cuenta): array
    {
        return [
            'rol' => $cuenta->rol()?->etiqueta(),
            'tareas' => $cuenta->can(Permiso::TareasVer->value) ? $this->tareas($cuenta) : null,
            'porLeer' => [
                'total' => Documento::query()->pendientesDeMiAcuse()->count(),
                'url' => '/documentos?'.http_build_query(['filter' => ['por_leer' => '1']]),
            ],
            'porFirmar' => $cuenta->can(Permiso::DocumentosAprobar->value) ? [
                'total' => Documento::query()->enRevision()->count(),
                'url' => '/documentos?'.http_build_query(['filter' => ['en_revision' => '1']]),
            ] : null,
            'avisos' => DestinatariosDelResumen::leCorresponde($cuenta)
                ? ['activos' => $cuenta->avisos_por_correo]
                : null,
            'alcance' => $this->alcance($cuenta),
            'entradaAnterior' => $this->accesos->entradaAnterior($cuenta),
        ];
    }

    /** @return array{abiertas: int, vencidas: int, url: string} */
    private function tareas(User $cuenta): array
    {
        $mias = static fn () => Tarea::query()->where('responsable_id', $cuenta->id);

        return [
            'abiertas' => $mias()->abiertas()->count(),
            'vencidas' => $mias()->vencidas()->count(),
            'url' => PaginaInicio::Tareas->url($cuenta),
        ];
    }

    /**
     * La cuarta capa, contada a quien la sufre: sin esto el auditor externo
     * descubre que su acceso caduca el día que deja de poder entrar.
     *
     * Sólo para quien tiene alcance o fecha de fin, que hoy es el auditor.
     *
     * @return array{hasta: ?string, sistemas: list<string>}|null
     */
    private function alcance(User $cuenta): ?array
    {
        $sistemas = $cuenta->alcance()
            ->with('sistema:id,nombre')
            ->get()
            ->map(static fn (CuentaSistema $fila): string => (string) $fila->sistema?->nombre)
            ->filter()
            ->sort()
            ->values()
            ->all();

        if ($sistemas === [] && $cuenta->acceso_hasta === null) {
            return null;
        }

        return [
            'hasta' => $cuenta->acceso_hasta?->toDateString(),
            'sistemas' => $sistemas,
        ];
    }
}
