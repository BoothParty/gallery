<?php

declare(strict_types=1);

namespace Tests;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Playwright;

/**
 * A browser page whose clicks are sent to Playwright exactly once.
 *
 * The browser plugin wraps every call in a retry loop that gives Playwright one
 * second per attempt. That is fine for assertions, but a click that lands just
 * after its second is up is reported as a timeout even though the browser
 * handled it: the retries then hit a page where the click already happened —
 * a modal covering the row, a submitted form whose button is gone — so they can
 * never succeed and the test fails on a click that actually worked. Clicking
 * once with the full timeout lets Playwright do the waiting it already knows
 * how to do.
 */
final class BrowserPage
{
    public function __construct(private readonly PendingAwaitablePage $page)
    {
        //
    }

    /**
     * Click the element matching the given selector, without retrying.
     */
    public function click(string $selector): self
    {
        $timeout = Playwright::timeout();

        /*
         * The plugin skips its retry loop when the timeout is one second or
         * less, so the click itself still gets the whole budget.
         */
        Playwright::setTimeout(1_000);

        try {
            Playwright::usingTimeout($timeout, fn () => $this->page->click($selector));
        } finally {
            Playwright::setTimeout($timeout);
        }

        return $this;
    }

    /**
     * Forward every other call to the underlying page.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->page->{$name}(...$arguments);

        return $result instanceof PendingAwaitablePage || $result instanceof AwaitableWebpage
            ? $this
            : $result;
    }
}
