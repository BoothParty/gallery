<?php

namespace App\Http\Controllers;

use App\Enums\PartyActivityType;
use App\Http\Requests\ReprintPicturesRequest;
use App\Models\Party;
use App\Models\PrintJob;
use Illuminate\Http\RedirectResponse;

class PictureReprintController extends Controller
{
    /**
     * Queue the selected pictures for the booth to print again.
     *
     * Every request adds jobs, even for a picture already waiting: asking
     * twice is how a host gets two copies. Rows are inserted in one query
     * since a selection can hold as many pictures as a download can.
     */
    public function __invoke(ReprintPicturesRequest $request, Party $party): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');
        $now = now();

        PrintJob::insert(array_map(fn (int $id): array => [
            'picture_id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $ids));

        $party->recordActivity(PartyActivityType::PrintsRequested, ['count' => count($ids)]);

        return back();
    }
}
