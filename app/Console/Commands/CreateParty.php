<?php

namespace App\Console\Commands;

use App\Models\Party;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

class CreateParty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:create {--name= : The name of the party} {--expiration= : The expiration date (Y-m-d H:i:s)} {--owner= : The ID or email of the owner}';

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
            placeholder: 'My Birthday Party',
            required: true,
        );

        // Get expiration date
        $expirationInput = $this->option('expiration') ?? text(
            label: 'Expiration date',
            placeholder: 'Y-m-d H:i:s (e.g., 2026-12-31 23:59:59)',
            required: true,
            validate: fn (string $value) => $this->validateDate($value),
        );

        try {
            $expirationDate = Carbon::parse($expirationInput);
        } catch (\Exception $e) {
            $this->components->error('Invalid date format. Please use Y-m-d H:i:s format.');

            return self::FAILURE;
        }

        // Get owner (optional)
        $ownerIdentifier = $this->option('owner');
        $owner = null;

        if (! $ownerIdentifier && $this->input->isInteractive()) {
            $ownerChoice = $this->components->confirm('Do you want to assign an owner to this party?', false);

            if ($ownerChoice) {
                $ownerIdentifier = search(
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
        }

        // Find owner if provided
        if ($ownerIdentifier) {
            $owner = is_numeric($ownerIdentifier)
                ? User::find($ownerIdentifier)
                : User::where('email', $ownerIdentifier)->first();

            if (! $owner) {
                $this->components->error('Owner not found.');

                return self::FAILURE;
            }
        }

        // Create the party
        $party = Party::create([
            'name' => $name,
            'expiration_date' => $expirationDate,
            'owned_by' => $owner?->id,
        ]);

        $this->components->info("Party \"{$party->name}\" created successfully!");
        $this->newLine();
        $this->components->twoColumnDetail('ID', $party->id);
        $this->components->twoColumnDetail('Name', $party->name);
        $this->components->twoColumnDetail('Expiration Date', $party->expiration_date->format('Y-m-d H:i:s'));

        if ($owner) {
            $this->components->twoColumnDetail('Owner', "{$owner->name} ({$owner->email})");
        }

        return self::SUCCESS;
    }

    /**
     * Validate the date format.
     */
    protected function validateDate(string $date): ?string
    {
        try {
            Carbon::parse($date);

            return null;
        } catch (\Exception $e) {
            return 'Invalid date format. Please use Y-m-d H:i:s format.';
        }
    }
}
