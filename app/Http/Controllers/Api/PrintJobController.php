<?php

namespace App\Http\Controllers\Api;

use App\Enums\PartyActivityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PrintJobResource;
use App\Models\Party;
use App\Models\PrintJob;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrintJobController extends Controller
{
    public function index(Party $party): AnonymousResourceCollection
    {
        return PrintJobResource::collection(
            $party->printJobs()->pending()->with('picture')->oldest('print_jobs.id')->get()
        );
    }

    public function printed(Party $party, PrintJob $printJob): PrintJobResource
    {
        if ($printJob->printed_at === null) {
            $printJob->update(['printed_at' => now()]);
            $party->recordActivity(PartyActivityType::PicturesPrinted, ['count' => 1]);
        }

        return new PrintJobResource($printJob->load('picture'));
    }
}
