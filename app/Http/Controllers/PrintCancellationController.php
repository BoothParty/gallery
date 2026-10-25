<?php

namespace App\Http\Controllers;

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Http\RedirectResponse;

class PrintCancellationController extends Controller
{
    /**
     * Take a picture off the booth's print queue.
     *
     * Every pending job of the picture goes, since the host sees one badge per
     * picture however many copies they asked for. Prints the booth already
     * made are kept: they happened. A job the booth fetched but has not
     * confirmed yet may still come out of the printer, which is accepted.
     */
    public function __invoke(Party $party, Picture $picture): RedirectResponse
    {
        $cancelled = $picture->printJobs()->pending()->delete();

        if ($cancelled > 0) {
            $party->recordActivity(PartyActivityType::PrintsCancelled, ['count' => 1]);
        }

        return back();
    }
}
