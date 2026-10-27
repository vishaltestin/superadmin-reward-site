<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Models\LandingPageTemplate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $filters = $request->validate([
            'event_id'          => 'nullable|integer|exists:events,id',
            'custom_event_name' => 'nullable|string|max:255',
            'vertical_id'       => 'nullable|integer|exists:verticals,id',
            'month'             => 'nullable|integer|between:1,12',
            'year'              => 'nullable|integer|between:1900,2200',
            'page'              => 'nullable|integer|min:1',
        ]);

        $query = Campaign::where('company_id', $companyId)
            ->with(['vertical:id,name', 'event:id,title']);

        if (! empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (! empty($filters['custom_event_name'])) {
            $query->whereNull('event_id')
                ->where('custom_event_name', $filters['custom_event_name']);

            if (! empty($filters['vertical_id'])) {
                $query->where('vertical_id', $filters['vertical_id']);
            }
        }

        if (! empty($filters['month'])) {
            $query->whereMonth('created_at', (int) $filters['month']);
        }

        if (! empty($filters['year'])) {
            $query->whereYear('created_at', (int) $filters['year']);
        }

        $campaigns = $query
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->appends($request->query());

        // Supply stable filter metadata from this company's campaigns, not
        // only the current page, so the dropdowns remain useful while paging.
        $campaignsForFilters = Campaign::where('company_id', $companyId)
            ->with(['event:id,title', 'vertical:id,name'])
            ->get(['id', 'vertical_id', 'event_id', 'custom_event_name', 'created_at']);

        $eventOptions = $campaignsForFilters
            ->map(function (Campaign $campaign) {
                if ($campaign->event_id && $campaign->event) {
                    $eventLabel = $campaign->event->title;
                    if ($campaign->vertical?->name) {
                        $eventLabel .= ' — ' . $campaign->vertical->name;
                    }

                    return [
                        'value' => 'event:' . $campaign->event_id,
                        'label' => $eventLabel,
                    ];
                }

                $customName  = trim((string) $campaign->custom_event_name);
                if ($customName === '') {
                    return null;
                }

                $customLabel = $customName . ' (Custom';
                if ($campaign->vertical?->name) {
                    $customLabel .= ' — ' . $campaign->vertical->name;
                }

                return [
                    'value' => 'custom:' . $campaign->vertical_id . ':' . rawurlencode($customName),
                    'label' => $customLabel . ')',
                ];
            })
            ->filter()
            ->unique('value')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $years = $campaignsForFilters
            ->pluck('created_at')
            ->filter()
            ->map(fn($createdAt) => $createdAt->year)
            ->unique()
            ->sortDesc()
            ->values();

        return response()->json(array_merge($campaigns->toArray(), [
            'filters' => [
                'events' => $eventOptions,
                'years'  => $years,
            ],
        ]));
    }

    public function show(Request $request, $id)
    {
        $campaign = Campaign::where('company_id', $request->user()->company_id)
            ->with(['vertical', 'event'])
            ->findOrFail($id);

        // Rich detail payload for the campaign detail page: per-recipient
        // reward status (who claimed, when, with which code) plus wallet
        // impact. Loaded additively — index/list consumers are unaffected.
        $campaign->load([
            'entitlements' => function ($query) {
                $query->select(['id', 'campaign_id', 'issued_to_user_id', 'reward_value', 'claim_code', 'is_claimed', 'claimed_at', 'expires_at', 'created_at'])
                    ->orderByDesc('is_claimed')
                    ->orderBy('created_at')
                    ->with(['user:id,first_name,last_name,email']);
            },
        ]);

        return response()->json(['data' => $campaign]);
    }

    public function store(Request $request)
    {
        $user    = $request->user();
        $company = $user->company;

        $validated = $request->validate([
            'name'                                     => 'required|string|max:255',
            'description'                              => 'nullable|string',
            'vertical_id'                              => 'required|exists:verticals,id',
            'event_id'                                 => 'nullable|required_without:custom_event_name|exists:events,id',
            'custom_event_name'                        => 'nullable|required_without:event_id|string|max:255',
            'distribution_type'                        => 'required|in:online,bulk',

            'reward_type'                              => 'required_if:distribution_type,online|in:points,code,link',
            'recipient_ids'                            => 'required_if:distribution_type,online|array',
            'recipient_ids.*'                          => 'exists:users,id',
            'reward_value_per_user'                    => 'required_if:distribution_type,online|numeric|min:1',
            'starts_at'                                => 'nullable|date',
            'expires_at'                               => 'nullable|date|after:starts_at',

            'event_address'                            => 'required_if:distribution_type,bulk|string',
            'event_date'                               => 'required_if:distribution_type,bulk|date',
            'inquiry_notes'                            => 'nullable|string',
            'bulk_items'                               => 'required_if:distribution_type,bulk|array',
            'bulk_items.*.product_id'                  => 'required|integer',
            'bulk_items.*.product_name'                => 'required|string',
            'bulk_items.*.variant_id'                  => 'nullable|integer',
            'bulk_items.*.variant_name'                => 'nullable|string',
            'bulk_items.*.quantity'                    => 'required|integer|min:1',

            'config_json'                              => 'nullable|array',
            'config_json.catalog_selection'            => 'nullable|array',
            'config_json.catalog_selection.*'          => 'integer|exists:products,id',
            'config_json.email_template_id'            => 'required_if:distribution_type,online|integer|exists:email_templates,id',
            'config_json.landing_page_template_id'     => 'nullable|integer|exists:landing_page_templates,id',
            'config_json.reminder_initial_template_id' => 'nullable|integer|exists:email_templates,id',
            'config_json.reminder_expiry_template_id'  => 'nullable|integer|exists:email_templates,id',
            'config_json.send_initial_reminder'        => 'sometimes|boolean',
            'config_json.reminder_initial_date'        => 'nullable|date_format:Y-m-d',
            'config_json.send_expiry_reminder'         => 'sometimes|boolean',
            'config_json.reminder_expiry_date'         => 'nullable|date_format:Y-m-d',
        ]);

        // Keep the dynamic storefront fields (catalog allow-list, SMS copy,
        // and the validated template/reminder fields) intact in the campaign
        // config JSON.
        $configJson = $request->input('config_json', []);
        if (! is_array($configJson)) {
            $configJson = [];
        }

        $accessibleVerticalIds = $user->user_type === 'sub_admin'
            ? $user->managedVerticals()->pluck('verticals.id')->all()
            : $company->verticals()->pluck('verticals.id')->all();

        if (! in_array((int) $validated['vertical_id'], array_map('intval', $accessibleVerticalIds), true)) {
            return response()->json(['message' => 'Unauthorized vertical access.'], 403);
        }

        if (! empty($validated['event_id'])) {
            $event = \App\Models\Event::findOrFail($validated['event_id']);
            if ((int) $event->vertical_id !== (int) $validated['vertical_id']) {
                throw ValidationException::withMessages([
                    'event_id' => 'The selected event does not belong to the selected vertical.',
                ]);
            }
        }

        if ($validated['distribution_type'] === 'online') {
            $rewardType = $validated['reward_type'];

            $this->assertCampaignTemplateIsCompatible(
                EmailTemplate::class,
                (int) $configJson['email_template_id'],
                $user,
                (int) $validated['vertical_id'],
                $rewardType,
                $validated['event_id'] ?? null,
                'config_json.email_template_id'
            );

            if (! empty($configJson['landing_page_template_id'])) {
                $this->assertCampaignTemplateIsCompatible(
                    LandingPageTemplate::class,
                    (int) $configJson['landing_page_template_id'],
                    $user,
                    (int) $validated['vertical_id'],
                    $rewardType,
                    $validated['event_id'] ?? null,
                    'config_json.landing_page_template_id'
                );
            }

            foreach ([
                'reminder_initial_template_id',
                'reminder_expiry_template_id',
            ] as $templateField) {
                if (! empty($configJson[$templateField])) {
                    $this->assertCampaignTemplateIsCompatible(
                        EmailTemplate::class,
                        (int) $configJson[$templateField],
                        $user,
                        (int) $validated['vertical_id'],
                        $rewardType,
                        $validated['event_id'] ?? null,
                        'config_json.' . $templateField
                    );
                }
            }

            $this->validateReminderSchedule($configJson, $validated);
        }

        try {
            DB::beginTransaction();

            $totalCost         = 0;
            $totalRecipients   = 0;
            $status            = 'processing';
            $walletTransaction = null;

            if ($validated['distribution_type'] === 'online') {
                // ── Tenant guard: recipients must be this company's own active
                //    rewardees. Validation alone (exists:users,id) would let a
                //    company gift points to any user on the platform.
                $recipientIds = \App\Models\User::whereIn('id', $validated['recipient_ids'])
                    ->where('company_id', $company->id)
                    ->where('user_type', 'rewardee')
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                $unknown = array_diff($validated['recipient_ids'], $recipientIds);
                if (! empty($unknown)) {
                    throw new Exception('Some recipients are invalid: they must be active members of your company.');
                }

                $totalRecipients = count($recipientIds);
                $totalCost       = $totalRecipients * $validated['reward_value_per_user'];

                $walletTransaction = $company->wallet->debit(
                    amount: $totalCost,
                    description: "Campaign Escrow Lock: {$validated['name']}"
                );
            } else {
                $status       = 'inquiry_pending';
                $recipientIds = [];
            }

            $campaign = Campaign::create([
                'company_id'         => $company->id,
                'created_by_user_id' => $user->id,
                'vertical_id'        => $validated['vertical_id'],
                'event_id'           => $validated['event_id'] ?? null,
                'custom_event_name'  => $validated['custom_event_name'] ?? null,
                'name'               => $validated['name'],
                'description'        => $validated['description'] ?? null,
                'distribution_type'  => $validated['distribution_type'],
                'reward_type'        => $validated['reward_type'] ?? null,
                'budget_locked'      => $totalCost,
                'total_budget'       => $totalCost,
                'total_recipients'   => $totalRecipients,
                'config_json'        => array_merge($configJson, [
                    'event_address' => $validated['event_address'] ?? null,
                    'event_date'    => $validated['event_date'] ?? null,
                    'inquiry_notes' => $validated['inquiry_notes'] ?? null,
                    'bulk_items'    => $validated['bulk_items'] ?? null,
                ]),
                'status'             => $status,
                'starts_at'          => $validated['starts_at'] ?? now(),
                'expires_at'         => $validated['expires_at'] ?? null,
            ]);

            if ($walletTransaction) {
                $walletTransaction->update([
                    'reference_type' => get_class($campaign),
                    'reference_id'   => $campaign->id,
                ]);
            }

            DB::commit();

            if ($campaign->distribution_type === 'online') {
                ProcessCampaignJob::dispatch(
                    $campaign->id,
                    $recipientIds,
                    $validated['reward_value_per_user']
                );
            }

            return response()->json([
                'message' => $campaign->distribution_type === 'online'
                    ? 'Campaign successfully submitted and is now processing.'
                    : 'Wholesale inquiry submitted successfully. Our team will contact you soon.',
                'data'    => $campaign,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create campaign.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    private function assertCampaignTemplateIsCompatible(
        string $templateClass,
        int $templateId,
        $user,
        int $verticalId,
        string $rewardType,
        $eventId,
        string $errorField
    ): void {
        $template = $templateClass === LandingPageTemplate::class
            ? LandingPageTemplate::with('event')->find($templateId)
            : EmailTemplate::with('event')->find($templateId);

        if (! $template || ! $template->is_active) {
            throw ValidationException::withMessages([
                $errorField => 'The selected template is no longer active.',
            ]);
        }

        if ($template->company_id !== null
            && (int) $template->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                $errorField => 'The selected template is not available to your company.',
            ]);
        }

        if (! $template->event
            || (int) $template->event->vertical_id !== $verticalId) {
            throw ValidationException::withMessages([
                $errorField => 'Choose a template from the campaign vertical.',
            ]);
        }

        if ($eventId !== null && (int) $template->event_id !== (int) $eventId) {
            throw ValidationException::withMessages([
                $errorField => 'Choose a template associated with the selected event.',
            ]);
        }

        if ($template->reward_type !== null && $template->reward_type !== $rewardType) {
            throw ValidationException::withMessages([
                $errorField => 'The selected template is not compatible with this reward type.',
            ]);
        }

        if ($template instanceof LandingPageTemplate
            && $rewardType === 'link'
            && ! $template->hasVisibleRewardSelector()) {
            throw ValidationException::withMessages([
                $errorField => 'Reward-link campaigns require a landing-page template with a visible reward selector.',
            ]);
        }
    }

    private function validateReminderSchedule(array $config, array $campaign): void
    {
        $timezone = config('app.timezone', 'UTC');
        $today    = now($timezone)->startOfDay();
        $startsAt = ! empty($campaign['starts_at'])
            ? Carbon::parse($campaign['starts_at'], $timezone)
            : now($timezone);
        $expiresAt = ! empty($campaign['expires_at'])
            ? Carbon::parse($campaign['expires_at'], $timezone)
            : null;

        if (! empty($campaign['starts_at']) && $startsAt->lt(now($timezone))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Campaign launch must be now or in the future.',
            ]);
        }

        $sendInitial = filter_var($config['send_initial_reminder'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendExpiry  = filter_var($config['send_expiry_reminder'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (($sendInitial || $sendExpiry) && ($campaign['reward_type'] ?? null) === 'points') {
            throw ValidationException::withMessages([
                'config_json.send_initial_reminder' => 'Automated claim reminders are only available for code and link rewards.',
            ]);
        }

        if ($sendInitial) {
            if (empty($config['reminder_initial_date'])) {
                throw ValidationException::withMessages([
                    'config_json.reminder_initial_date' => 'Choose a date for the follow-up reminder.',
                ]);
            }

            $initialDate = Carbon::createFromFormat(
                'Y-m-d',
                $config['reminder_initial_date'],
                $timezone
            )->startOfDay();

            if (! $initialDate->gt($today)) {
                throw ValidationException::withMessages([
                    'config_json.reminder_initial_date' => 'The follow-up reminder date must be in the future.',
                ]);
            }

            if (! $initialDate->gt($startsAt->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'config_json.reminder_initial_date' => 'The follow-up reminder must be on a date after campaign launch.',
                ]);
            }

            if ($expiresAt && ! $initialDate->lt($expiresAt->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'config_json.reminder_initial_date' => 'The follow-up reminder must be before campaign expiration.',
                ]);
            }
        }

        if ($sendExpiry) {
            if (! $expiresAt) {
                throw ValidationException::withMessages([
                    'expires_at' => 'Set a campaign expiration before enabling an expiry reminder.',
                ]);
            }

            if (empty($config['reminder_expiry_date'])) {
                throw ValidationException::withMessages([
                    'config_json.reminder_expiry_date' => 'Choose a date for the expiry reminder.',
                ]);
            }

            $expiryReminderDate = Carbon::createFromFormat(
                'Y-m-d',
                $config['reminder_expiry_date'],
                $timezone
            )->startOfDay();

            if (! $expiryReminderDate->gt($today)) {
                throw ValidationException::withMessages([
                    'config_json.reminder_expiry_date' => 'The expiry reminder date must be in the future.',
                ]);
            }

            if (! $expiryReminderDate->lt($expiresAt->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'config_json.reminder_expiry_date' => 'The expiry reminder must be before campaign expiration.',
                ]);
            }
        }

        if ($sendInitial && $sendExpiry
            && ($config['reminder_initial_date'] ?? null) === ($config['reminder_expiry_date'] ?? null)) {
            throw ValidationException::withMessages([
                'config_json.reminder_expiry_date' => 'Choose different dates for the two reminders.',
            ]);
        }
    }

    public function cancel(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // Lock the campaign row for the whole refund computation. Without
            // this, a claim fulfilling concurrently (which decrements
            // budget_locked in its own transaction) could race the refund and
            // the company would be paid back MORE than the remaining escrow.
            $campaign = Campaign::where('company_id', $request->user()->company_id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($campaign->status, ['completed', 'cancelled'])) {
                DB::rollBack();
                return response()->json(['message' => 'Cannot cancel a completed or already cancelled campaign.'], 400);
            }

            if ($campaign->reward_type === 'points' && $campaign->budget_locked <= 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cannot cancel this campaign because the points have already been distributed to users.',
                ], 400);
            }

            if ($campaign->budget_locked > 0) {
                $campaign->company->wallet->credit(
                    amount: $campaign->budget_locked,
                    description: "Refund for Cancelled Campaign: {$campaign->name}"
                );
            }

            $campaign->entitlements()->where('is_claimed', false)->update([
                'expires_at' => now(),
            ]);

            $campaign->update([
                'status'        => 'cancelled',
                'budget_locked' => 0,
            ]);

            DB::commit();

            return response()->json(['message' => 'Campaign cancelled and remaining funds refunded.']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to cancel campaign.'], 500);
        }
    }
}
