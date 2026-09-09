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
