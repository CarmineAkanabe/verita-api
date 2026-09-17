<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'role' => $this->role->value,
            'departmentId' => $this->department_id,
            'staffId' => $this->staff_id,
            'profilePicture' => $this->profile_picture,
            'presenceStatus' => $this->presence_status->value,
        ];
    }
}
