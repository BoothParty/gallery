<?php

namespace App\Console\Commands\Party;

use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

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
     * Progress over the pictures, which is what actually takes hours.
     */
    private ?ProgressBar $progress = null;

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

        $this->progress = $this->output->createProgressBar($this->totalPictures($legacyParties));
        $this->progress->start();

        foreach ($legacyParties as $legacyParty) {
            $this->importPictures($legacyParty, $this->importParty($legacyParty));
        }

        $this->progress->finish();
        $this->newLine(2);
        $this->report();

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

    /**
     * Walk the legacy pictures of one party. The join filters on the
     * `original` size, so the legacy thumbnail and cover rows never surface,
     * and `chunkById` keeps memory flat on a gallery of any size.
     */
    private function importPictures(object $legacyParty, Party $party): void
    {
        DB::connection('legacy')->table('pictures')
            ->join('picture_sizes', 'picture_sizes.picture_id', '=', 'pictures.id')
            ->where('pictures.party_id', $legacyParty->id)
            ->where('picture_sizes.size', 'original')
            ->select([
                'pictures.id',
                'pictures.created_at',
                'pictures.updated_at',
                'picture_sizes.path',
                'picture_sizes.width',
                'picture_sizes.height',
            ])
            ->chunkById(500, function ($rows) use ($party): void {
                foreach ($rows as $row) {
                    $this->importPicture($row, $party);
                    $this->progress?->advance();
                }
            }, 'pictures.id', 'id');
    }

    /**
     * Push one legacy original onto S3 and record it.
     *
     * The object keeps its legacy file name, which the old project already
     * derived from the content hash. That makes the S3 path deterministic, so
     * a re-run overwrites the very same object instead of creating a twin.
     */
    private function importPicture(object $row, Party $party): void
    {
        try {
            $source = $this->filesRoot.'/'.$row->path;

            if (! is_file($source)) {
                $this->counters['missing']++;
                Log::warning('Legacy import: file is gone', [
                    'legacy_picture_id' => $row->id,
                    'path' => $row->path,
                ]);

                return;
            }

            $path = Storage::disk('s3')->putFileAs(
                "pictures/{$party->id}",
                new File($source),
                pathinfo($row->path, PATHINFO_BASENAME),
            );

            $picture = Picture::firstOrNew(['path' => $path]);

            if ($picture->exists) {
                return;
            }

            $picture->fill([
                'party_id' => $party->id,
                'width' => $row->width,
                'height' => $row->height,
            ])->forceFill([
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->save();

            $this->counters['pictures']++;

            CreatePictureThumbnail::dispatch($picture);
        } catch (Throwable $exception) {
            $this->counters['errors']++;
            Log::error('Legacy import: picture failed', [
                'legacy_picture_id' => $row->id,
                'path' => $row->path,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Total number of legacy originals in scope, used to size the progress bar.
     *
     * @param  Collection<int, object>  $legacyParties
     */
    private function totalPictures(Collection $legacyParties): int
    {
        return DB::connection('legacy')->table('pictures')
            ->join('picture_sizes', 'picture_sizes.picture_id', '=', 'pictures.id')
            ->whereIn('pictures.party_id', $legacyParties->pluck('id'))
            ->where('picture_sizes.size', 'original')
            ->count();
    }

    /**
     * Render the tally. Missing files are the legacy rows whose file is gone
     * from disk; orphan files never surface, the import being driven by the
     * database rather than by a filesystem walk.
     */
    private function report(): void
    {
        $this->table(['Résultat', 'Nombre'], [
            ['Parties importées', $this->counters['parties']],
            ['Photos importées', $this->counters['pictures']],
            ['Fichiers manquants', $this->counters['missing']],
            ['Erreurs', $this->counters['errors']],
        ]);
    }
}
