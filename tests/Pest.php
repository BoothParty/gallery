<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\BrowserPage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

/*
| `LazilyRefreshDatabase` rather than `RefreshDatabase`: the transaction is
| only opened by a test that actually touches the database, so the handful of
| pure unit-style tests in `Feature` stay database-free without having to opt
| out by hand.
*/
pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature', 'Web');

/*
|--------------------------------------------------------------------------
| Browser Tests
|--------------------------------------------------------------------------
|
| The browser plugin waits 5 seconds for an element by default, which is not
| enough on the shared CI runners, where three browsers render the same pages
| at once. The generous budget only costs time when a test is about to fail
| anyway.
|
*/

pest()->browser()->timeout(30_000);

/**
 * Wraps a visited page so that its clicks are sent once, see Tests\BrowserPage.
 *
 * The visit is passed as a closure because the browser plugin decides which
 * tests need a browser by looking for a "visit(" call in the test's own source.
 */
function browserPage(Closure $visit): BrowserPage
{
    return new BrowserPage($visit());
}

/**
 * Absolute URL of a page of the given party's gallery, on the host the gallery
 * is published under.
 *
 * The browser plugin rewrites a path to its own 127.0.0.1 origin, which no
 * longer matches the gallery's subdomain route. An absolute URL is left alone,
 * and any `*.localhost` name resolves to the loopback without DNS, so the
 * browser reaches the same test server while the app sees the party's host.
 */
function partyUrl(Party $party, string $path = '/'): string
{
    $port = parse_url((string) config('app.url'), PHP_URL_PORT);

    return 'http://'.$party->publicHost().($port === null ? '' : ':'.$port).$path;
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
