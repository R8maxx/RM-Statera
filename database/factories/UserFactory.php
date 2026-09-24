<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Activa por defecto: una cuenta sin `activada_en` es una invitación
            // pendiente y `CuentaVigente` la saca en la primera petición.
            'activada_en' => now(),
        ];
    }

    /** Invitada y sin aceptar: nadie conoce todavía su contraseña. */
    public function invitada(): static
    {
        return $this->state(fn (array $attributes) => [
            'invitada_en' => now(),
            'activada_en' => null,
        ]);
    }

    public function desactivada(?string $motivo = null): static
    {
        return $this->state(fn (array $attributes) => [
            'desactivada_en' => now(),
            'motivo_desactivacion' => $motivo,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
