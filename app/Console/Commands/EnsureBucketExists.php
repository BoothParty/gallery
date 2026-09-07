<?php

namespace App\Console\Commands;

use Aws\Exception\AwsException;
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
    protected $description = 'Create the configured S3 bucket and keep it closed to anonymous reads';

    public function handle(): int
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $client = Storage::disk('s3')->getClient();

        if (! $client->doesBucketExistV2($bucket, false)) {
            $client->createBucket(['Bucket' => $bucket]);
            $this->info("bucket {$bucket} créé");
        }

        /*
         * Les photos ne sont lisibles que par URL signée : toute policy
         * anonyme laissée sur le bucket court-circuiterait l'expiration des
         * liens, donc on la retire à chaque boot plutôt qu'à la création.
         */
        try {
            $client->deleteBucketPolicy(['Bucket' => $bucket]);
        } catch (AwsException) {
            // Pas de policy à retirer.
        }

        return self::SUCCESS;
    }
}
