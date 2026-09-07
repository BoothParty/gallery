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
    protected $description = 'Create the configured S3 bucket and open it for anonymous reads';

    public function handle(): int
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $client = Storage::disk('s3')->getClient();

        if (! $client->doesBucketExistV2($bucket, false)) {
            $client->createBucket(['Bucket' => $bucket]);
            $this->info("bucket {$bucket} créé");
        }

        $client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => json_encode($this->publicReadPolicy($bucket)),
        ]);

        return self::SUCCESS;
    }

    /**
     * Les navigateurs téléchargent photos et vignettes directement depuis le
     * stockage, sans signature : les objets doivent être lisibles anonymement.
     *
     * @return array<string, mixed>
     */
    private function publicReadPolicy(string $bucket): array
    {
        return [
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => ["arn:aws:s3:::{$bucket}/*"],
            ]],
        ];
    }
}
