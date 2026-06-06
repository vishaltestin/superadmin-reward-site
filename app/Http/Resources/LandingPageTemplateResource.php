<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class LandingPageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'title'               => $this->title,
            'thumbnail_path'      => $this->getThumbnailUrl($this->thumbnail_path),
            'status'              => $this->status,
            'updated_at'          => $this->updated_at,

            'global_theme_tokens' => $this->whenHas('global_theme_tokens'),
            'seo_meta'            => $this->whenHas('seo_meta'),
            'page_schema'         => $this->whenHas('page_schema'),
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
