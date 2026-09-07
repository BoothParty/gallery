<?php

/**
 * En production le kamal-proxy termine le TLS et transmet le schéma et l'hôte
 * d'origine dans X-Forwarded-Proto / X-Forwarded-Host. Sans confiance accordée
 * au proxy, l'application génère ses URL absolues en http:// sur une page
 * servie en https:// : assets bloqués en contenu mixte et redirections qui
 * sortent du HTTPS.
 */
it('trusts the proxy headers when generating absolute URLs', function () {
    $this->get('/up', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'boothparty.test',
    ])->assertOk();

    expect(url()->current())->toBe('https://boothparty.test/up');
});
