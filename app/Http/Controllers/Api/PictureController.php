<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePictureRequest;
use App\Http\Resources\PictureResource;
use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PictureController extends Controller
{
    public function index(Party $party): AnonymousResourceCollection
    {
        return PictureResource::collection($party->pictures()->latest()->get());
    }

    public function store(StorePictureRequest $request, Party $party): PictureResource
    {
        $photo = $request->file('photo');
        $dimensions = getimagesize($photo->getPathname());

        abort_if($dimensions === false, 422, 'La photo est illisible.');

        [$width, $height] = $dimensions;

        $path = Storage::disk('s3')->putFile("pictures/{$party->id}", $photo);

        abort_if($path === false, 500, 'Le stockage des photos est indisponible.');

        $picture = $party->pictures()->create([
            'path' => $path,
            'width' => $width,
            'height' => $height,
        ]);

        CreatePictureThumbnail::dispatch($picture);

        return new PictureResource($picture);
    }

    public function show(Party $party, Picture $picture): PictureResource
    {
        return new PictureResource($picture);
    }

    public function destroy(Party $party, Picture $picture): Response
    {
        $picture->delete();

        return response()->noContent();
    }
}
