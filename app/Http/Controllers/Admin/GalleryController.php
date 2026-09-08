<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminPicturesRequest;
use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use App\Services\AdminDashboardDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function __construct(
        private readonly AdminDashboardDataService $adminDashboardDataService,
    ) {}

    public function index(Request $request, Party $party): Response
    {
        $allowedPerPage = [24, 48, 72, 96];
        $perPage = in_array($request->integer('per_page', 24), $allowedPerPage)
            ? $request->integer('per_page', 24)
            : 24;

        $pictures = $party->pictures()
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (Picture $picture) => [
                'id' => $picture->id,
                'url' => $picture->url(),
                'thumbnailUrl' => $picture->thumbnailUrl(),
                'width' => $picture->width,
                'height' => $picture->height,
            ]);

        return Inertia::render('admin/gallery/index', [
            ...$this->adminDashboardDataService->sidebarData($party->id),
            'party' => [
                'id' => $party->id,
                'name' => $party->name,
            ],
            'pictures' => $pictures,
            'perPage' => $perPage,
        ]);
    }

    public function store(StoreAdminPicturesRequest $request, Party $party): RedirectResponse
    {
        /** @var UploadedFile[] $photos */
        $photos = $request->file('photos');

        foreach ($photos as $photo) {
            /** @var array{int, int} $dimensions */
            $dimensions = getimagesize($photo->getPathname());

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
