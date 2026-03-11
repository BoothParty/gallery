<?php

namespace App\Console\Commands\Party;

use App\Models\Party;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

class CreateParty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:create
                            {--name= : The name of the party}
                            {--user= : The ID or email of the owner (optional)}
                            {--expires-at= : Expiration date (Y-m-d H:i:s, optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new party';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get party name
        $name = $this->option('name') ?? text(
            label: 'Party name',
            placeholder: 'My Party',
            required: true,
        );

        // Resolve owner
        $user = $this->resolveUser();

        if ($user === false) {
            return self::FAILURE;
        }

        // Get expiration date
        $expiresAt = $this->resolveExpiresAt();

        if ($expiresAt === false) {
            return self::FAILURE;
        }

        // Create party
        $party = Party::create([
            'name' => $name,
            'user_id' => $user?->id,
            'expire_at' => $expiresAt,
        ]);

        $this->components->info("Party \"{$party->name}\" created successfully (ID: {$party->id})");
        $this->newLine();
        $this->components->twoColumnDetail('Name', $party->name);
        $this->components->twoColumnDetail('Owner', $user ? "{$user->name} ({$user->email})" : '—');
        $this->components->twoColumnDetail('Expires at', $party->expire_at?->toDateTimeString() ?? '—');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Resolve the user from the --user option or interactively.
     * Returns a User, null (no owner), or false on error.
     */
    private function resolveUser(): User|false|null
    {
        $userIdentifier = $this->option('user');

        // If explicitly passed, resolve and validate
        if ($userIdentifier !== null) {
            $user = is_numeric($userIdentifier)
                ? User::find($userIdentifier)
                : User::where('email', $userIdentifier)->first();

            if (! $user) {
                $this->components->error('User not found.');

                return false;
            }

            return $user;
        }

        // Interactive search (returns user ID, 0 for no owner)
        $selectedId = search(
            label: 'Search for an owner',
            options: fn (string $value) => collect([0 => '— No owner —'])
                ->when(
                    strlen($value) > 0,
                    fn ($items) => $items->merge(
                        User::query()
                            ->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%")
                            ->limit(10)
                            ->get()
                            ->mapWithKeys(fn (User $user) => [$user->id => "{$user->name} ({$user->email})"])
                    )
                )
                ->all(),
            placeholder: 'Start typing to search...',
        );

        return $selectedId ? User::find($selectedId) : null;
    }

    /**
     * Resolve the expiration date from the --expires-at option or interactively.
     * Returns a valid date string, null (no expiration), or false on error.
     */
    private function resolveExpiresAt(): string|false|null
    {
        $value = $this->option('expires-at');

        // Option passed: validate immediately and fail hard
        if ($value !== null) {
            $error = $this->validateExpiresAt($value);

            if ($error) {
                $this->components->error($error);

                return false;
            }

            return $value;
        }

        // Interactive prompt with inline validation
        $value = text(
            label: 'Expiration date',
            placeholder: 'Y-m-d H:i:s (leave empty for no expiration)',
            required: false,
            validate: fn (string $v) => $v !== '' ? $this->validateExpiresAt($v) : null,
        ) ?: null;

        return $value;
    }

    /**
     * Validate an expiration date string.
     * Returns an error message, or null if valid.
     */
    private function validateExpiresAt(string $value): ?string
    {
        $validator = Validator::make(
            ['expires_at' => $value],
            ['expires_at' => ['required', 'date', 'after:now']],
            [
                'expires_at.date' => 'The expiration date is not a valid date.',
                'expires_at.after' => 'The expiration date must be in the future.',
            ],
        );

        return $validator->fails()
            ? $validator->errors()->first('expires_at')
            : null;
    }
}
