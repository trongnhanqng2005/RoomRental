<?php

namespace App\Http\Requests\Admin;

class StoreAmenityRequest extends CatalogRequest
{
    public function rules(): array
    {
        return $this->catalogRules('amenities');
    }
}
