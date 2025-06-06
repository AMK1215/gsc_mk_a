<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'balance' => $this->balanceFloat,
            'status' => $this->status,
        ];

        return [
            'user' => $user,
            'token' => $this->createToken($this->user_name)->plainTextToken,
        ];
    }
}
