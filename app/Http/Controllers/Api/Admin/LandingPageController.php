<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LandingPageTemplateResource;
use App\Models\EventVariable;
use App\Models\LandingPageTemplate;
use App\Models\Vertical;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandingPageController extends Controller
{
    private function getAccessibleVerticalIds($user): array
    {
        if ($user->user_type === 'sub_admin') {
            return $user->managedVerticals()->pluck('verticals.id')->toArray();
        }
        return $user->company->verticals()->pluck('verticals.id')->toArray();
    }

    public function getSidebarEvents(Request $request)
    {
        $user        = $request->user();
        $companyId   = $user->company_id;
        $verticalIds = $this->getAccessibleVerticalIds($user);

        $variationCounts = LandingPageTemplate::where('company_id', $companyId)
            ->selectRaw('event_id, count(*) as count')
            ->groupBy('event_id')
            ->pluck('count', 'event_id');

        $verticals = Vertical::whereIn('id', $verticalIds)
            ->where('is_active', true)
            ->with(['events' => function ($query) {
                $query->whereNull('parent_id')
                    ->where('is_active', true)
                    ->with('children');
            }])
            ->get();

        $sidebarData = $verticals->map(function ($vertical) use ($variationCounts) {
            $items = [];

            foreach ($vertical->events as $event) {
                if ($event->children->count() > 0) {
                    $items[] = [
                        'type'   => 'group',
                        'title'  => $event->title,
                        'events' => $event->children->map(function ($child) use ($variationCounts) {
                            return [
                                'id'              => $child->id,
                                'title'           => $child->title,
                                'variation_count' => $variationCounts->get($child->id, 0),
                            ];
                        })->values(),
                    ];
                } else {
                    $items[] = [
                        'type'            => 'event',
                        'id'              => $event->id,
                        'title'           => $event->title,
                        'variation_count' => $variationCounts->get($event->id, 0),
                    ];
                }
            }

            return [
                'id'    => $vertical->id,
                'name'  => $vertical->name,
                'items' => $items,
            ];
        });

        return response()->json(['data' => $sidebarData]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'event_id' => 'required|exists:events,id',
            'tab'      => 'required|in:global,variations',
        ]);

        $event = \App\Models\Event::findOrFail($request->event_id);

        if (! in_array($event->vertical_id, $this->getAccessibleVerticalIds($user))) {
            return response()->json(['message' => 'Unauthorized vertical access.'], 403);
        }

        $query = LandingPageTemplate::where('event_id', $request->event_id)
            ->where('is_active', true);

        if ($request->tab === 'global') {
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $user->company_id);
        }

        $templates = $query->latest()->get(['id', 'name', 'title', 'thumbnail_path', 'status', 'updated_at']);

        return LandingPageTemplateResource::collection($templates);
    }

    public function show(Request $request, $id)
    {
        $user     = $request->user();
        $template = LandingPageTemplate::with('event')->findOrFail($id);

        if ($template->company_id !== null && $template->company_id !== $user->company_id) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        if (! in_array($template->event->vertical_id, $this->getAccessibleVerticalIds($user))) {
            return response()->json(['message' => 'Unauthorized vertical access.'], 403);
        }

        $variables = EventVariable::where('is_active', true)
            ->whereIn('usage_type', ['landing_page', 'both'])
            ->where(function ($query) use ($template) {
                $query->whereNull('event_id')
                    ->orWhere('event_id', $template->event_id);
            })
            ->get(['name', 'value']);

        $template->available_variables = $variables;

        return new LandingPageTemplateResource($template);
    }

    public function duplicateMaster(Request $request, $id)
    {
        $user           = $request->user();
        $masterTemplate = LandingPageTemplate::whereNull('company_id')->findOrFail($id);

        if (! in_array($masterTemplate->event->vertical_id, $this->getAccessibleVerticalIds($user))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name'  => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
        ]);

        $newName  = ! empty($validated['name']) ? $validated['name'] : 'Copy of ' . $masterTemplate->name;
        $newTitle = ! empty($validated['title']) ? $validated['title'] : $masterTemplate->title;

        $variation = DB::transaction(function () use ($masterTemplate, $user, $newName, $newTitle) {
            return LandingPageTemplate::create([
                'event_id'            => $masterTemplate->event_id,
                'company_id'          => $user->company_id,
                'name'                => $newName,
                'title'               => $newTitle,
                'thumbnail_path'      => $masterTemplate->thumbnail_path,
                'status'              => 'draft',
                'global_theme_tokens' => $masterTemplate->global_theme_tokens,
                'seo_meta'            => $masterTemplate->seo_meta,
                'page_schema'         => $masterTemplate->page_schema,
                'is_active'           => true,
            ]);
        });

        return response()->json([
            'message' => 'Template successfully duplicated.',
            'data'    => new LandingPageTemplateResource($variation),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user     = $request->user();
        $template = LandingPageTemplate::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'title'               => 'sometimes|string|max:255',
            'status'              => 'sometimes|in:draft,published,archived',
            'thumbnail_path'      => 'nullable|string',
            'global_theme_tokens' => 'sometimes|array',
            'seo_meta'            => 'sometimes|array',
            'page_schema'         => 'sometimes|array',
            'is_active'           => 'sometimes|boolean',
        ]);

        // Keep database clean by stripping base URLs
        if (isset($validated['thumbnail_path'])) {
            $baseUrl                     = asset('storage') . '/';
            $validated['thumbnail_path'] = str_replace($baseUrl, '', $validated['thumbnail_path']);
        }

        $contentParts = [
            $validated['title'] ?? '',
            isset($validated['seo_meta']) ? json_encode($validated['seo_meta']) : '',
            isset($validated['page_schema']) ? json_encode($validated['page_schema']) : '',
        ];

        $contentToValidate = implode(' ', $contentParts);

        if (! empty(trim($contentToValidate))) {
            preg_match_all('/{{\s*(.*?)\s*}}/', $contentToValidate, $matches);
            $usedTags = array_unique($matches[1]);

            if (count($usedTags) > 0) {
                $allowedTags = EventVariable::where('is_active', true)
                    ->where(function ($query) use ($template) {
                        $query->whereNull('event_id')
                            ->orWhere('event_id', $template->event_id);
                    })
                    ->pluck('value')
                    ->map(function ($val) {
                        return trim(str_replace(['{{', '}}'], '', $val));
                    })
                    ->toArray();

                $invalidTags = array_diff($usedTags, $allowedTags);

                if (count($invalidTags) > 0) {
                    throw ValidationException::withMessages([
                        'page_schema' => 'You used invalid dynamic variables: ' . implode(', ', $invalidTags),
                    ]);
                }
            }
        }

        $template->update($validated);

        return response()->json([
            'message' => 'Landing page saved successfully.',
            'data'    => new LandingPageTemplateResource($template),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user     = $request->user();
        $template = LandingPageTemplate::where('company_id', $user->company_id)->findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'files'   => 'required|array',
            'files.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ]);

        $urls = [];

        foreach ($request->file('files') as $file) {
            $path   = $file->store('landing-page-assets', 'public');
            $urls[] = asset('storage/' . $path);
        }

        return response()->json(['data' => $urls]);
    }
}
