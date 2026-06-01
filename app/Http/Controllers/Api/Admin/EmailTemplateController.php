<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\EventVariable;
use App\Models\Vertical;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailTemplateController extends Controller
{
    private function getAccessibleVerticalIds($user): array
    {
        if ($user->user_type === 'sub_admin') {
            return $user->managedVerticals()->pluck('verticals.id')->toArray();
        }

        return $user->company->verticals()->pluck('verticals.id')->toArray();
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $template = EmailTemplate::with('event')->findOrFail($id);

        if ($template->company_id !== null && $template->company_id !== $user->company_id) {
            return response()->json(['message' => 'Unauthorized access to this company template.'], 403);
        }

        if (! in_array($template->event->vertical_id, $this->getAccessibleVerticalIds($user))) {
            return response()->json(['message' => 'Unauthorized vertical access.'], 403);
        }

        $variables = \App\Models\EventVariable::where('is_active', true)
            ->whereIn('usage_type', ['email', 'both'])
            ->where(function ($query) use ($template) {
                $query->whereNull('event_id')
                    ->orWhere('event_id', $template->event_id);
            })
            ->get(['name', 'value']);

        $template->available_variables = $variables;

        return response()->json(['data' => $template]);
    }

    public function getSidebarEvents(Request $request)
    {
        $user        = $request->user();
        $companyId   = $user->company_id;
        $verticalIds = $this->getAccessibleVerticalIds($user);

        $variationCounts = \App\Models\EmailTemplate::where('company_id', $companyId)
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
        $user      = $request->user();
        $companyId = $user->company_id;

        $request->validate([
            'event_id'    => 'required|exists:events,id',
            'tab'         => 'required|in:global,variations',
            'reward_type' => 'nullable|in:points,code,link',
        ]);

        $eventId = $request->event_id;
        $tab     = $request->tab;

        $event = \App\Models\Event::findOrFail($eventId);
        if (! in_array($event->vertical_id, $this->getAccessibleVerticalIds($user))) {
            return response()->json(['message' => 'Unauthorized vertical access.'], 403);
        }

        $query = EmailTemplate::where('event_id', $eventId)
            ->where('is_active', true);

        if ($tab === 'global') {
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $companyId);
        }

        if ($request->has('reward_type')) {
            $query->where('reward_type', $request->reward_type);
        }

        $templates = $query->latest()->get(['id', 'name', 'subject', 'thumbnail_path', 'updated_at']);
        $templates = $query->latest()->get(['id', 'name', 'subject', 'thumbnail_path', 'updated_at', 'reward_type']);

        return response()->json(['data' => $templates]);
    }

    public function duplicateMaster(Request $request, $id)
    {
        $user = $request->user();

        $masterTemplate = EmailTemplate::whereNull('company_id')->findOrFail($id);

        if (! in_array($masterTemplate->event->vertical_id, $this->getAccessibleVerticalIds($user))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $variation = DB::transaction(function () use ($masterTemplate, $user) {
            return EmailTemplate::create([
                'event_id'    => $masterTemplate->event_id,
                'company_id'  => $user->company_id,
                'reward_type' => $masterTemplate->reward_type,
                'name'        => 'Copy of ' . $masterTemplate->name,
                'subject'     => $masterTemplate->subject,
                'html_body'   => $masterTemplate->html_body,
                'design_json' => $masterTemplate->design_json,
                'is_active'   => true,
            ]);
        });

        return response()->json([
            'message' => 'Template successfully duplicated to your variations.',
            'data'    => $variation,
        ], 201);
    }

    // public function update(Request $request, $id)
    // {
    //     $user = $request->user();
    //     $template = EmailTemplate::where('company_id', $user->company_id)->findOrFail($id);

    //     $validated = $request->validate([
    //         'name' => 'sometimes|string|max:255',
    //         'subject' => 'sometimes|string|max:255',
    //         'html_body' => 'sometimes|string',
    //         'design_json' => 'sometimes|array',
    //         'is_active' => 'sometimes|boolean',
    //         'thumbnail_path' => 'nullable|string',
    //     ]);

    //     $template->update($validated);

    //     return response()->json([
    //         'message' => 'Template updated successfully.',
    //         'data' => $template
    //     ]);
    // }

    public function update(Request $request, $id)
    {
        $user     = $request->user();
        $template = EmailTemplate::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'subject'        => 'sometimes|string|max:255',
            'html_body'      => 'sometimes|string',
            'design_json'    => 'sometimes|array',
            'is_active'      => 'sometimes|boolean',
            'thumbnail_path' => 'nullable|string',
            'reward_type'    => 'nullable|in:points,code,link',
        ]);

        $contentToValidate = ($validated['subject'] ?? '') . ' ' . ($validated['html_body'] ?? '');

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
                        'html_body' => 'You used invalid dynamic variables: ' . implode(', ', $invalidTags),
                    ]);
                }
            }
        }

        $template->update($validated);

        return response()->json([
            'message' => 'Template updated successfully.',
            'data'    => $template,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $template = EmailTemplate::where('company_id', $user->company_id)->findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'files'   => 'required|array',
            'files.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $urls = [];

        foreach ($request->file('files') as $file) {
            $path   = $file->store('email-assets', 'public');
            $urls[] = asset('storage/' . $path);
        }

        return response()->json(['data' => $urls]);
    }
}
