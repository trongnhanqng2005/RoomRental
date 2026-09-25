<?php

namespace App\Http\Requests\Admin;

class StoreRoomCategoryRequest extends CatalogRequest
{
    public function rules(): array
    {
        return $this->catalogRules('room_categories');
    }
}
