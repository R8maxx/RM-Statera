<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Organizacion\Models\Organizacion;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
