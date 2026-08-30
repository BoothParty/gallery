<?php

namespace App\Console\Commands\Party;

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

        return self::SUCCESS;
    }
}
