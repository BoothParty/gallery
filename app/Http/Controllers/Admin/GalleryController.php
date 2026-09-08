<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminPicturesRequest;
use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function store(StoreAdminPicturesRequest $request, Party $party): RedirectResponse
    {
        /** @var UploadedFile[] $photos */
        $photos = $request->file('photos');

        foreach ($photos as $photo) {
            $dimensions = getimagesize($photo->getPathname());

            abort_if($dimensions === false, 422, 'Une des photos est illisible.');

            $path = Storage::disk('s3')->putFile("pictures/{$party->id}", $photo);

            abort_if($path === false, 500, 'Le stockage des photos est indisponible.');

            $picture = $party->pictures()->create([
                'path' => $path,
                'width' => $dimensions[0],
                'height' => $dimensions[1],
            ]);

            CreatePictureThumbnail::dispatch($picture);
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
