<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Models\CuentaSistema;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $foto_ruta
 * @property ?int $organizacion_id
 * @property ?Carbon $two_factor_confirmed_at
 * @property ?string $two_factor_secret
 * @property ?Carbon $invitada_en
 * @property ?Carbon $activada_en
 * @property ?Carbon $desactivada_en
 * @property ?string $motivo_desactivacion
 * @property ?Carbon $acceso_hasta
 * @property ?Carbon $ultimo_acceso_en
 * @property ?Carbon $created_at
 */
#[Fillable(['name', 'email', 'password', 'organizacion_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Si el segundo factor está activo de verdad.
     *
     * Tener secreto no basta: Fortify lo genera al empezar el alta y sólo queda
     * confirmado cuando la persona introduce un código que cuadra. Entre las dos
     * cosas el segundo factor no protege nada, y decir que sí sería mentirle a
     * quien lo mira en el perfil.
     */
    public function dosFactoresConfirmado(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /** Secreto generado y todavía sin confirmar: el alta a medias. */
    public function dosFactoresPendiente(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at === null;
    }

    /**
     * Por dónde pide el navegador la foto de perfil, o nulo si no hay.
     *
     * Es **siempre la misma ruta y sin identificar a nadie**: la foto sólo se
     * pinta en el chrome y en `/perfil`, y siempre es la de quien mira. Una
     * ruta con `{usuario}` habría que acotarla a mano, porque `users` es el
     * único modelo de datos propios sin scope global y sin RLS, y ningún test
     * de aislamiento avisaría de que se olvidó.
     *
     * **El sufijo de versión no es adorno.** La URL es fija, así que sin él el
     * navegador sirve de su caché la foto vieja y cambiarla no se ve —un fallo
     * que aparece dos días después y en otra pantalla—. El ULID del fichero ya
     * es distinto en cada subida, así que sirve de versión tal cual y no hace
     * falta calcular ningún hash.
     */
    public function urlFoto(): ?string
    {
        if ($this->foto_ruta === null) {
            return null;
        }

        return '/perfil/foto?v='.pathinfo($this->foto_ruta, PATHINFO_FILENAME);
    }

    /** En qué punto de su vida está la cuenta; se deriva, no se guarda. */
    public function estadoCuenta(): EstadoCuenta
    {
        return EstadoCuenta::de($this);
    }

    /**
     * El rol de la cuenta en SU organización, o nulo si no tiene.
     *
     * Se lee de las tablas y no de `roles()` ni de `getRoleNames()`: las dos
     * pasan por el «team» que tenga puesto el registrar en ese momento, y en un
     * listener de login, en un comando o en la lista de cuentas de otro no
     * tiene por qué ser el de esta cuenta.
     */
    public function rol(): ?Rol
    {
        $nombre = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $this->getMorphClass())
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.organizacion_id', $this->organizacion_id)
            ->value('roles.name');

        return is_string($nombre) ? Rol::tryFrom($nombre) : null;
    }

    /**
     * Los sistemas que la cuenta tiene en su alcance. Vacío significa que el
     * alcance no está acotado: ve la organización entera.
     *
     * @return HasMany<CuentaSistema, $this>
     */
    public function alcance(): HasMany
    {
        return $this->hasMany(CuentaSistema::class, 'user_id');
    }

    /**
     * La organización a la que pertenece. Nula significa que el usuario no ve
     * ningún dato propio, que es el comportamiento correcto.
     *
     * @return BelongsTo<Organizacion, $this>
     */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }

    /**
     * Las que tienen la invitación en el correo y no la han aceptado. Misma
     * precedencia que `EstadoCuenta::de()`: una desactivada no está pendiente.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeInvitadas(Builder $consulta): void
    {
        $consulta->whereNull('users.activada_en')->whereNull('users.desactivada_en');
    }

    /** @param  Builder<self>  $consulta */
    public function scopeDesactivadas(Builder $consulta): void
    {
        $consulta->whereNotNull('users.desactivada_en');
    }

    /**
     * Las de acceso con fecha: el auditor externo, que entra mientras dura la
     * auditoría.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeConFechaDeFin(Builder $consulta): void
    {
        $consulta->whereNotNull('users.acceso_hasta');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'invitada_en' => 'datetime',
            'activada_en' => 'datetime',
            'desactivada_en' => 'datetime',
            'acceso_hasta' => 'date',
            'ultimo_acceso_en' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
