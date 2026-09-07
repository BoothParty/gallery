<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EnsureBucketExists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:ensure-bucket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the configured S3 bucket when it is missing';

    public function handle(): int
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $client = Storage::disk('s3')->getClient();

        if ($client->doesBucketExistV2($bucket, false)) {
            $this->line("bucket {$bucket} déjà présent");

            return self::SUCCESS;
        }

        $client->createBucket(['Bucket' => $bucket]);
        $this->info("bucket {$bucket} créé");

        return self::SUCCESS;
    }
}
