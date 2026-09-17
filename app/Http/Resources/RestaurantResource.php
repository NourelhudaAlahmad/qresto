<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantResource extends JsonResource
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
            'name' => $this->name,
            'tagline' => $this->tagline,
            'cuisine' => $this->cuisine,
            'description' => $this->description,
            'address' => $this->address,
            'phone' => $this->phone,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
        ];
    }
}
