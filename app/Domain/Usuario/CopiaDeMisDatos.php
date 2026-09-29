<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Documento\Models\DocumentoLectura;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Models\User;

/**
 * Lo que la herramienta guarda de la cuenta, para llevárselo (RGPD, arts. 15 y 20).
 *
 * **Lo que es de la cuenta, y nada de lo que es de la organización.** Tu ficha,
 * tus preferencias, cuándo has entrado y lo que has firmado o acusado. No van
 * las tareas ni las evidencias que llevas: son registros del SGSI de la
 * organización en los que apareces, no datos tuyos, y sacarlos de la
 * herramienta es otra conversación con quien responde de ellos.
 *
 * **Sin secretos**: ni la contraseña, ni el segundo factor, ni las passkeys más
 * allá de su nombre. Un fichero que se descarga acaba en la carpeta de
 * descargas de un portátil.
 *
 * Todo corre en el contexto de la petición, así que la traza, las lecturas y
 * las versiones salen de la organización de la cuenta y de ninguna otra.
 */
final class CopiaDeMisDatos
{
    public function __construct(private readonly FichaPropia $ficha) {}

    /** @return array<string, mixed> */
    public function de(User $cuenta): array
    {
        return [
            'generada_en' => now()->toIso8601String(),
            'cuenta' => [
                'nombre' => $cuenta->name,
                'correo' => $cuenta->email,
                ...$this->ficha->de($cuenta),
                'ultimo_acceso_en' => $cuenta->ultimo_acceso_en?->toIso8601String(),
                'acceso_hasta' => $cuenta->acceso_hasta?->toDateString(),
                'segundo_factor' => $cuenta->dosFactoresConfirmado(),
                'passkeys' => $cuenta->passkeys()->pluck('name')->all(),
            ],
            'preferencias' => [
                'tema' => $cuenta->tema->value,
                'pagina_inicio' => $cuenta->pagina_inicio->value,
                'avisos_por_correo' => $cuenta->avisos_por_correo,
            ],
            'accesos' => EventoAuditoria::query()
                ->where('entidad', class_basename(User::class))
                ->where('entidad_id', $cuenta->id)
                ->whereIn('accion', [
                    AccionAuditada::InicioSesion->value,
                    AccionAuditada::CierreSesion->value,
                    AccionAuditada::IntentoFallido->value,
                ])
                ->orderBy('created_at')
                ->get()
                ->map(static fn (EventoAuditoria $evento): array => [
                    'fecha' => $evento->created_at->toIso8601String(),
                    'accion' => $evento->accion->value,
                    'ip' => $evento->ip,
                ])
                ->all(),
            'acuses_de_lectura' => DocumentoLectura::query()
                ->where('user_id', $cuenta->id)
                ->with('version.documento:id,codigo,titulo')
                ->orderBy('acusada_en')
                ->get()
                ->map(static fn (DocumentoLectura $lectura): array => [
                    'documento' => $lectura->version->documento->codigo.' — '.$lectura->version->documento->titulo,
                    'version' => $lectura->version->etiqueta(),
                    'fecha' => $lectura->acusada_en->toIso8601String(),
                ])
                ->all(),
            'firmas' => DocumentoVersion::query()
                ->where('aprobada_por_id', $cuenta->id)
                ->with('documento:id,codigo,titulo')
                ->orderBy('aprobada_en')
                ->get()
                ->map(static fn (DocumentoVersion $version): array => [
                    'documento' => $version->documento->codigo.' — '.$version->documento->titulo,
                    'version' => $version->etiqueta(),
                    'fecha' => $version->aprobada_en?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
