<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePictureRequest;
use App\Http\Resources\PictureResource;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PictureController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PictureResource::collection(Picture::query()->latest()->get());
    }

    public function store(StorePictureRequest $request, Party $party): PictureResource
    {
        $path = Storage::disk('s3')->putFile("pictures/{$party->id}", $request->file('photo'));

        $picture = $party->pictures()->create([
            'path' => $path,
        ]);

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
