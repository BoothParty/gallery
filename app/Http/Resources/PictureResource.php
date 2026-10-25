<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PictureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'party_id' => $this->party_id,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'hidden' => $this->isHidden(),
            'print_pending' => $this->whenHas('print_pending', fn () => (bool) $this->print_pending),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
