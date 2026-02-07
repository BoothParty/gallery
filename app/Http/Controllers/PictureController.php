<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadPictureRequest;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PictureController extends Controller
{
    /**
     * Upload a picture to a specific party.
     */
    public function store(UploadPictureRequest $request, Party $party): JsonResponse
    {
        $file = $request->file('image');

        // Store the file in S3/Minio in the parties directory with a generated filename
        $path = $file->store("parties/{$party->id}", 's3');

        // Create picture record
        $picture = $party->pictures()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => 'Picture uploaded successfully.',
            'data' => [
                'id' => $picture->id,
                'url' => Storage::disk('s3')->url($path),
                'original_name' => $picture->original_name,
                'size' => $picture->size,
                'uploaded_at' => $picture->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * List all pictures for a specific party.
     */
    public function index(Party $party): JsonResponse
    {
        $pictures = $party->pictures()
            ->latest()
            ->get()
            ->map(fn (Picture $picture) => [
                'id' => $picture->id,
                'url' => Storage::disk('s3')->url($picture->path),
                'original_name' => $picture->original_name,
                'mime_type' => $picture->mime_type,
                'size' => $picture->size,
                'uploaded_at' => $picture->created_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => $pictures,
        ]);
    }

    /**
     * Delete a specific picture.
     */
    public function destroy(Party $party, Picture $picture): JsonResponse
    {
        // Ensure the picture belongs to the party
        if ($picture->party_id !== $party->id) {
            return response()->json([
                'message' => 'Picture does not belong to this party.',
            ], 404);
        }

        // Delete the file from S3/Minio storage
        Storage::disk('s3')->delete($picture->path);

        // Delete the database record
        $picture->delete();

        return response()->json([
            'message' => 'Picture deleted successfully.',
        ]);
    }
}
