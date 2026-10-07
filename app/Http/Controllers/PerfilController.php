<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\PermisosDeLaCuenta;
use App\Domain\Aviso\DestinatariosDelResumen;
use App\Domain\Usuario\AccesosRecientes;
use App\Domain\Usuario\CopiaDeMisDatos;
use App\Domain\Usuario\Enums\PaginaInicio;
use App\Domain\Usuario\FichaPropia;
use App\Domain\Usuario\GuardarPreferencias;
use App\Domain\Usuario\LoPendienteDeLaCuenta;
use App\Domain\Usuario\SesionesAbiertas;
use App\Http\Requests\GuardarPreferenciasRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as RespuestaHttp;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Passkey;

/**
 * La cuenta de quien ha entrado: datos, contraseña, segundo factor, passkeys,
 * sesiones, preferencias y la copia de sus datos. Y el menú de la cuenta, que
 * es la misma pregunta —«¿qué hay de lo mío?»— hecha desde cualquier pantalla.
 *
 * Todo el backend es de Fortify y del paquete de passkeys, que ya registran sus
 * rutas (`user/two-factor-*`, `user/passkeys*`). Aquí sólo se pinta el estado y
 * se decide qué se enseña.
 *
 * **El secreto, el QR y los códigos de recuperación no viajan con la pantalla.**
 * Viven en `dosFactores()`, que va detrás de `password.confirm`: son los datos
 * con los que alguien que se siente delante de una sesión abierta se lleva el
 * segundo factor puesto. Fortify protege así sus propios endpoints y esta
 * pantalla no puede ser la puerta de atrás.
 */
class PerfilController extends Controller
{
    public function __construct(
        private readonly FichaPropia $ficha,
        private readonly SesionesAbiertas $sesiones,
        private readonly AccesosRecientes $accesos,
    ) {}

    public function show(Request $request): Response
    {
        return Inertia::render('perfil/Index', $this->estado($request));
    }

    /**
     * Lo que el menú de la cuenta pinta al abrirse. JSON y no Inertia: el menú
     * vive en el layout de todas las pantallas y no navega.
     */
    public function menu(Request $request, LoPendienteDeLaCuenta $pendiente): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return response()->json($pendiente->de($usuario));
    }

    public function preferencias(GuardarPreferenciasRequest $request, GuardarPreferencias $guardar): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        /** @var array{tema?: string, pagina_inicio?: string, avisos_por_correo?: bool} $datos */
        $datos = $request->validated();
        $guardar($usuario, $datos);

        Inertia::flash('exito', 'Preferencias guardadas.');

        return to_route('perfil');
    }

    /**
     * El tema, desde el menú: una petición de fondo que no navega, porque
     * cambiar de tema no debería recargar la tabla que se estaba mirando. El
     * cliente ya lo ha aplicado; esto sólo lo recuerda en la cuenta.
     */
    public function tema(GuardarPreferenciasRequest $request, GuardarPreferencias $guardar): RespuestaHttp
    {
        /** @var User $usuario */
        $usuario = $request->user();

        /** @var array{tema?: string} $datos */
        $datos = $request->safe()->only('tema');
        $guardar($usuario, $datos);

        return response()->noContent();
    }

    /**
     * Adónde lleva la entrada. Es el `home` de Fortify, así que es aquí donde
     * aterriza quien acaba de entrar, y de aquí sale a la página que eligió.
     */
    public function inicio(Request $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        // Quien administra la plataforma no tiene panel de organización: su
        // casa es la lista de clientes (punto 41).
        if ($usuario->esPlataforma()) {
            return redirect()->route('plataforma.organizaciones.index');
        }

        return redirect()->to($usuario->pagina_inicio->url($usuario));
    }

    public function misDatos(Request $request, CopiaDeMisDatos $copia): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return response()
            ->json($copia->de($usuario), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="mis-datos-statera-'.now()->format('Y-m-d').'.json"');
    }

    /**
     * Lo mismo, con el secreto del segundo factor a la vista.
     *
     * Es una navegación y no una petición de fondo a propósito: si hay que
     * reconfirmar la contraseña, el middleware manda a la pantalla de
     * confirmación y vuelve aquí después, que es el recorrido que la persona
     * entiende.
     */
    public function dosFactores(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return Inertia::render('perfil/Index', [
            ...$this->estado($request),
            'secreto' => $usuario->two_factor_secret === null ? null : [
                // El SVG lo genera Fortify; se pinta con `v-html` porque es eso,
                // un SVG, y no texto.
                'qr' => $usuario->twoFactorQrCodeSvg(),
                // La clave en texto, para quien no puede escanear.
                'clave' => decrypt($usuario->two_factor_secret),
                'codigos' => $usuario->recoveryCodes(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function estado(Request $request): array
    {
        /** @var ?User $usuario */
        $usuario = $request->user();

        return [
            /*
             * El nombre y el correo viajan para que el formulario los PRECARGUE.
             * Antes el cliente arrancaba con dos cadenas vacías y el valor real
             * puesto de `placeholder`: quien pulsaba «Guardar» sin reescribir
             * los dos campos recibía un error de validación, y quien cambiaba
             * sólo el nombre mandaba el correo vacío.
             */
            'usuario' => [
                'nombre' => $usuario?->name,
                'email' => $usuario?->email,
                'foto' => $usuario?->urlFoto(),
            ],

            // Sólo lectura: qué puede hacer esta cuenta y qué no. Gestionar los
            // permisos de otros es el § 4.19 y no cuelga de aquí.
            'permisos' => $usuario === null
                ? ['roles' => [], 'modulos' => [], 'sinAcceso' => []]
                : PermisosDeLaCuenta::de($usuario),
            'dosFactores' => [
                'confirmado' => (bool) $usuario?->dosFactoresConfirmado(),
                'pendiente' => (bool) $usuario?->dosFactoresPendiente(),
            ],
            'passkeys' => $usuario === null ? [] : $usuario->passkeys()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Passkey $passkey): array => [
                    'id' => $passkey->id,
                    'nombre' => $passkey->name,
                    // Qué autenticador es (Touch ID, una llave física…), si el
                    // catálogo de AAGUID lo reconoce.
                    'autenticador' => $passkey->authenticator,
                    'ultimoUso' => $passkey->last_used_at?->toIso8601String(),
                    'alta' => $passkey->created_at?->toIso8601String(),
                ])
                ->all(),
            // Sin secreto: esta clave sólo la rellena `dosFactores()`.
            'secreto' => null,

            'ficha' => $usuario === null ? null : $this->ficha->de($usuario),
            'sesiones' => $usuario === null ? [] : $this->sesiones->de($usuario, $request->session()->getId()),
            'accesos' => $usuario === null ? [] : $this->accesos->de($usuario),
            'preferencias' => [
                'tema' => $usuario?->tema->value,
                'paginaInicio' => $usuario?->pagina_inicio->value,
                'paginasInicio' => array_map(
                    static fn (PaginaInicio $pagina): array => ['valor' => $pagina->value, 'etiqueta' => $pagina->etiqueta()],
                    PaginaInicio::cases(),
                ),
            ],
            // Nulo cuando el rol no recibe el resumen: la pantalla lo explica
            // en vez de pintar un interruptor que no apaga nada.
            'avisos' => $usuario !== null && DestinatariosDelResumen::leCorresponde($usuario)
                ? ['activos' => $usuario->avisos_por_correo]
                : null,
        ];
    }
}
