<?php

namespace App\Console\Commands\Party;

use App\Models\Party;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:import-legacy
                            {--files= : Path to the copied public/ directory of the legacy project}
                            {--party= : Restrict the import to a single legacy party, by name}
                            {--dry-run : Walk the source and report without writing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import parties and pictures from the legacy BoothParty database';

    /**
     * Value written to `parties.source_key` for everything this import creates.
     */
    public const SOURCE_KEY = 'legacy';

    /**
     * Absolute path to the copied `public/` directory of the legacy project.
     */
    private string $filesRoot = '';

    /**
     * Tally of what the run did, rendered by the closing report.
     *
     * @var array{parties: int, pictures: int, missing: int, errors: int}
     */
    private array $counters = [
        'parties' => 0,
        'pictures' => 0,
        'missing' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filesRoot = $this->option('files');

        if (! is_string($filesRoot) || ! is_dir($filesRoot)) {
            $this->components->error('The --files option must point to an existing directory.');

            return self::FAILURE;
        }

        $this->filesRoot = rtrim($filesRoot, '/');

        $legacyParties = DB::connection('legacy')->table('parties')
            ->when(
                $this->option('party'),
                fn ($query, string $name) => $query->where('name', $name),
            )
            ->orderBy('id')
            ->get();

        foreach ($legacyParties as $legacyParty) {
            $this->importParty($legacyParty);
        }

        return self::SUCCESS;
    }

    /**
     * Create the target party for a legacy one, or return the party a previous
     * run already created for it. The source couple is the whole idempotency
     * key: no run state is persisted anywhere.
     */
    private function importParty(object $legacyParty): Party
    {
        $party = Party::firstOrNew([
            'source_key' => self::SOURCE_KEY,
            'source_event_id' => (string) $legacyParty->id,
        ]);

        if ($party->exists) {
            return $party;
        }

        $party->fill([
            'name' => $legacyParty->name,
            'display_name' => $legacyParty->title,
            'description' => $legacyParty->description,
            'expire_at' => $legacyParty->hide_pictures_date,
            'user_id' => null,
        ]);

        /**
         * Timestamps are guarded, and letting Eloquent stamp them would date
         * every imported gallery from the night of the migration.
         */
        $party->forceFill([
            'created_at' => $legacyParty->created_at,
            'updated_at' => $legacyParty->updated_at,
        ])->save();

        $this->counters['parties']++;

        return $party;
    }
}
