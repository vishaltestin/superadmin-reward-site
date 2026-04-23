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
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'wallet_balance' => (float) $this->balance, 
            
            'Gender' => $this->gender,
            'dob'    => $this->dob,
            
            'Address'  => $homeAddress->address_line_1 ?? null,
            'Landmark' => $homeAddress->address_line_2 ?? null,
            'City'     => $homeAddress->city ?? null,
            'State'    => $homeAddress->state ?? null,
            'PinCode'  => $homeAddress->pincode ?? null,
            'Country'  => $homeAddress->country ?? null,

            'Shipping_Address'  => $shippingAddress->address_line_1 ?? null,
            'Shipping_Landmark' => $shippingAddress->address_line_2 ?? null,
            'Shipping_City'     => $shippingAddress->city ?? null,
            'Shipping_State'    => $shippingAddress->state ?? null,
            'Shipping_PinCode'  => $shippingAddress->pincode ?? null,
            'Shipping_Country'  => $shippingAddress->country ?? null,


            'company' => new CompanyResource($this->whenLoaded('company')),
            
            'managed_vertical_ids' => $this->whenLoaded('managedVerticals', function () {
                return $this->managedVerticals->pluck('id')->toArray();
            }, []),

            'custom_data' => $this->whenLoaded('rewardeeProfile', function () {
                return $this->rewardeeProfile->vertical_data ?? (object)[]; 
            }, (object)[]),
        ];
    }
}