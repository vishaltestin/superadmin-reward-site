<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'alias'               => $this->alias,
            'logo_url'            => $this->logo ? asset('storage/' . $this->logo) : null,
            'industry'            => $this->industry,
            'number_of_employee'  => $this->number_of_employee,
            'gst_no'              => $this->gst_no,
            'pan_no'              => $this->pan_no,
            'address'             => $this->address,
            'points_name'         => $this->points_name,
            'wallet_balance'      => (float) ($this->wallet?->balance ?? 0.00),
            'is_approved'         => $this->is_approved,

            'social_links'        => empty($this->social_links) ? (object) [] : $this->social_links,
            'terms_text'          => $this->terms_text,
            'privacy_text'        => $this->privacy_text,

            'hidden_category_ids' => $this->hidden_category_ids ?? [],
            'hidden_product_ids'  => $this->hidden_product_ids ?? [],
        ];
    }
}
