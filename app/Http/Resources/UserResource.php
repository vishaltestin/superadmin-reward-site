<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'wallet_balance' => (float) $this->balance,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'custom_data' => $this->whenLoaded('rewardeeProfile', function () {
                return $this->rewardeeProfile->vertical_data ?? [];
            }),
        ];
    }
}