<?php

namespace App\Http\Resources;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Restaurant
 */
class RestaurantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $translations = $this->translations ?? [];

        $localized = function (string $field) use ($locale, $translations): ?string {
            return $translations[$locale][$field]
                ?? $translations['en'][$field]
                ?? $this->{$field};
        };

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $localized('name'),
            'tagline' => $localized('tagline'),
            'cuisine' => $localized('cuisine'),
            'description' => $localized('description'),
            'address' => $localized('address'),
            'phone' => $this->phone,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
        ];
    }
}
