<?php

namespace App\Console\Commands\User;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

class GenerateUserToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:generate-token {--user= : The ID or email of the user} {--name= : The name of the token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an API token for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userIdentifier = $this->option('user');

        // If no user provided, search for one interactively
        if (! $userIdentifier) {
            $userIdentifier = search(
                label: 'Search for a user',
                options: fn (string $value) => strlen($value) > 0
                    ? User::query()
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->limit(10)
                        ->get()
                        ->mapWithKeys(fn (User $user) => [$user->id => "{$user->name} ({$user->email})"])
                        ->all()
                    : [],
                placeholder: 'Start typing to search...',
            );
        }

        // Find user by ID or email
        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (! $user) {
            $this->components->error('User not found.');

            return self::FAILURE;
        }

        // Get token name
        $tokenName = $this->option('name') ?? text(
            label: 'Token name',
            placeholder: 'API Token',
            default: 'API Token',
            required: true,
        );

        // Generate token
        $token = $user->createToken($tokenName);

        $this->components->info("Token generated successfully for {$user->name} ({$user->email})");
        $this->newLine();
        $this->components->twoColumnDetail('Token', $token->plainTextToken);
        $this->newLine();
        $this->components->warn('Make sure to copy this token now. You won\'t be able to see it again!');

        return self::SUCCESS;
    }
}
