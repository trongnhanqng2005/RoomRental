<?php

namespace App\Http\Requests\Admin;

use App\Models\Amenity;

class UpdateAmenityRequest extends CatalogRequest
{
    public function rules(): array
    {
        return $this->catalogRules('amenities', $this->route('amenity') instanceof Amenity ? $this->route('amenity')->id : null);
    }
}
