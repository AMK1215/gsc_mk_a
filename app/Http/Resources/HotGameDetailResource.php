<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotGameDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $imgUrl = $this->gameTypes[0]->pivot->image;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'game_type_id' => $this->game_type_id,
            'provider_id' => $this->product_id,
            'provider_code' => $this->product->code,
            // 'image' => $this->image_url,
            'image' => url('/api/proxy-image?url=' . urlencode($this->image_url)),
        ];
    }
}
