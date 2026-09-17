<?php

use Symfony\Component\Yaml\Yaml;

it('exposes the studio mailbox through the party configuration', function () {
    config(['party.studio_email' => 'studio@boothparty.test']);

    expect(config('party.studio_email'))->toBe('studio@boothparty.test');
});

it('leaves the studio mailbox null when the environment does not set it', function () {
    expect(config('party.studio_email'))->toBeNull();
});

it('installs ffmpeg in the production image', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('ffmpeg');
});

/**
 * The scheduler is a deployment concern rather than an application one, but
 * nothing else would notice its absence: without that role the reminder
 * command never runs and the emails are silently never sent.
 */
it('runs a scheduler role alongside the queue worker', function () {
    $deploy = Yaml::parseFile(base_path('config/deploy.yml'));

    expect($deploy['servers'])->toHaveKey('scheduler')
        ->and($deploy['servers']['scheduler']['cmd'])->toBe('php artisan schedule:work')
        ->and($deploy['servers']['job']['cmd'])->toContain('queue:work');
});

it('carries the mail credentials as deployment secrets', function () {
    $deploy = Yaml::parseFile(base_path('config/deploy.yml'));

    expect($deploy['env']['secret'])->toContain('MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'PARTY_STUDIO_EMAIL')
        ->and($deploy['env']['clear']['MAIL_MAILER'])->toBe('smtp');
});
