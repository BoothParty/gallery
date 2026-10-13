<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartyActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminPicturesRequest;
use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    /**
     * A photo sent with a `paths` entry is recorded under that path, and sent
     * again it is skipped: the transfer upload retries after timeouts and is
     * resumed by dropping the same folder twice. Without `paths`, as from the
     * dashboard form, every photo is stored, homonyms included.
     */
    public function store(StoreAdminPicturesRequest $request, Party $party): RedirectResponse|JsonResponse
    {
        /** @var UploadedFile[] $photos */
        $photos = $request->file('photos');
        /** @var array<int, string> $paths */
        $paths = $request->validated('paths', []);
        $created = 0;

        foreach ($photos as $index => $photo) {
            $originalName = $paths[$index] ?? null;

            if ($originalName !== null && $party->pictures()->where('original_name', $originalName)->exists()) {
                continue;
            }

            $dimensions = getimagesize($photo->getPathname());

            abort_if($dimensions === false, 422, 'Une des photos est illisible.');

            $path = Storage::disk('s3')->putFile("pictures/{$party->id}", $photo);

            abort_if($path === false, 500, 'Le stockage des photos est indisponible.');

            try {
                $picture = $party->pictures()->create([
                    'path' => $path,
                    'original_name' => $originalName,
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request stored the same path first.
                Storage::disk('s3')->delete($path);

                continue;
            }

            CreatePictureThumbnail::dispatch($picture);
            $created++;
        }

        if ($created > 0) {
            $party->recordActivity(PartyActivityType::PicturesUploaded, ['count' => $created]);
        }

        if ($request->wantsJson()) {
            return response()->json(['created' => $created, 'skipped' => count($photos) - $created]);
        }

        return redirect()->route('party.dashboard.pictures', $party);
    }

    public function bulkDelete(Request $request, Party $party): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $party->pictures()
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each->delete();

        return redirect()->route('party.dashboard.pictures', $party);
    }
}
