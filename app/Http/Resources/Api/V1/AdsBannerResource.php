<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdsBannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Return empty array if resource is null or empty
        if (!$this->resource) {
            return [];
        }

        return [
           // 'mobile_image' => $this->mobile_image_url,
            'desktop_image' => $this->desktop_image_url,
            'text' => $this->description,
        ];
    }
}
