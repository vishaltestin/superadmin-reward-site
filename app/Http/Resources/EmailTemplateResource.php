<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'subject'             => $this->subject,
            'thumbnail_path'      => $this->getThumbnailUrl($this->thumbnail_path),
            'updated_at'          => $this->updated_at,
            'reward_type'         => $this->reward_type,
            'html_body'           => $this->whenHas('html_body'),
            'design_json'         => $this->whenHas('design_json'),
            'available_variables' => $this->when(isset($this->available_variables), $this->available_variables),
        ];
    }

    private function getThumbnailUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return asset('storage/' . $path);
    }
}