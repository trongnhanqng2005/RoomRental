<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomCategory;

class UpdateRoomCategoryRequest extends CatalogRequest
{
    public function rules(): array
    {
        return $this->catalogRules('room_categories', $this->route('category') instanceof RoomCategory ? $this->route('category')->id : null);
    }
}
