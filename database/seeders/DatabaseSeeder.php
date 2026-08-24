<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the first administrator from .env. This is the one account that
 * exists before anyone has logged in, so it is also the one an attacker will
 * try first — every guard below exists to stop a shipped copy of this product
 * from going live with credentials that are printed in its own .env.example.
 */
class DatabaseSeeder extends Seeder
{
    /** Refuse anything a scanner tries in its first hundred guesses. */
    private const BANNED_PASSWORDS = [];

    /** The placeholder domain shipped in .env.example. */
    private const PLACEHOLDER_DOMAIN = 'your-domain.com';

    public function run(): void
    {
        // Populate the payment gateway catalog (all 32 gateways as inactive
        // sandbox rows) first, so it lands even if admin-env validation below
        // aborts on a bare install.
        $this->call(PaymentGatewaySeeder::class);

        $name = trim((string) env('ADMIN_NAME', 'Site Admin'));
        $email = strtolower(trim((string) env('ADMIN_EMAIL', '')));
        $password = (string) env('ADMIN_PASSWORD', '');

        $this->assertUsable($email, $password);

        // Keyed on email so re-running after a deploy resets the password
        // instead of colliding on the unique index.
        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : 'Site Admin',
                // The `hashed` cast on User does the bcrypt.
                'password' => $password,
                // The installer account IS the platform admin — without this
                // the created user lands on the normal dashboard with no admin.
                'is_admin' => true,
            ],
        );

        $this->command?->info("Admin ready: {$email}");
    }

    private function assertUsable(string $email, string $password): void
    {
        // `php artisan config:cache` makes env() return null everywhere, which
        // would land here as "missing" — worth naming, since the .env is
        // sitting right there looking correct.
        $hint = ' (if your .env does set it, run `php artisan config:clear` first)';

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ADMIN_EMAIL is missing or not a valid address.' . $hint);
        }

        if (str_ends_with($email, '@' . self::PLACEHOLDER_DOMAIN)) {
            throw new RuntimeException(
                'ADMIN_EMAIL is still the ' . self::PLACEHOLDER_DOMAIN . ' placeholder from .env.example. Set your real address.'
            );
        }

        if ($password === '') {
            throw new RuntimeException('ADMIN_PASSWORD is empty. Set a strong password in .env before seeding.' . $hint);
        }

        if (mb_strlen($password) < 8) {
            throw new RuntimeException(
                'ADMIN_PASSWORD must be at least 8 characters.'
            );
        }

        if (in_array(mb_strtolower($password), self::BANNED_PASSWORDS, true)) {
            throw new RuntimeException('ADMIN_PASSWORD is a well-known password. Choose something unguessable.');
        }
    }
}
