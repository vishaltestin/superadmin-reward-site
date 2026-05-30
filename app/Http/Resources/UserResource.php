<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $homeAddress = $this->whenLoaded('addresses') instanceof \Illuminate\Support\Collection
            ? $this->addresses->where('type', 'home')->first()
            : null;

        $shippingAddress = $this->whenLoaded('addresses') instanceof \Illuminate\Support\Collection
            ? $this->addresses->where('type', 'shipping')->first()
            : null;

        return [
            'id'                   => $this->id,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'email'                => $this->email,
            'mobile'               => $this->mobile,
            'user_type'            => $this->user_type,
            'is_active'            => $this->is_active,
            'wallet_balance'       => (float) ($this->wallet?->balance ?? 0.00),

            'gender'               => $this->gender,
            'dob'                  => $this->dob,

            'address'              => $homeAddress->address_line_1 ?? null,
            'landmark'             => $homeAddress->address_line_2 ?? null,
            'city'                 => $homeAddress->city ?? null,
            'state'                => $homeAddress->state ?? null,
            'pincode'              => $homeAddress->pincode ?? null,
            'country'              => $homeAddress->country ?? null,

            'shipping_address'     => $shippingAddress->address_line_1 ?? null,
            'shipping_landmark'    => $shippingAddress->address_line_2 ?? null,
            'shipping_city'        => $shippingAddress->city ?? null,
            'shipping_state'       => $shippingAddress->state ?? null,
            'shipping_pincode'     => $shippingAddress->pincode ?? null,
            'shipping_country'     => $shippingAddress->country ?? null,

            'company'              => new CompanyResource($this->whenLoaded('company')),

            'managed_vertical_ids' => $this->whenLoaded('managedVerticals', function () {
                return $this->managedVerticals->pluck('id')->toArray();
            }, []),

            'custom_data'          => $this->whenLoaded('rewardeeProfile', function () {
                return $this->rewardeeProfile->vertical_data ?? (object) [];
            }, (object) []),
        ];
    }
}
