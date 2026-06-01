Here is the complete, master architectural documentation for your Template Builder and Campaign Dispatch system. Save this in your project repository (e.g., `docs/template-engine-architecture.md`) so you and your team have a permanent reference.

---

# 📘 Master Architecture: Template Engine & Campaign Dispatcher

## 1. Core Philosophy & The "Restaurant" Model

This module provides an enterprise-grade, dynamic communication engine. It bridges a headless drag-and-drop editor (React/GrapesJS) with an asynchronous, high-volume background queue (Laravel Jobs).

To ensure performance and data integrity, the system strictly separates concerns using the **Restaurant Model**:

1. **The Menu (`event_variables` table):** Defines exactly what dynamic tags (e.g., `{{ first_name }}`) are allowed for a specific event.
2. **The Waiter (Controller Validation):** When a user saves a template, the backend strictly checks the HTML against the "Menu." If they typed an invalid tag, the save is rejected.
3. **The Chef (Background Queue):** When sending 10,000 emails, the system does *not* query the database for variable rules. It trusts the saved template, loads the user data into a dictionary array, parses the HTML in memory, and sends it instantly.

---

## 2. Database Schema (The Data Layer)

Four core tables manage the lifecycle of templates and their variables.

* **`events`**: The trigger categories (e.g., "Festival Bonus", "New Joinee").
* **`event_variables`**: The dictionary of allowed tags.
* *Global Variables* have `event_id = null`.
* *Specific Variables* belong to one `event_id`.


* **`email_templates`**: Stores the GrapesJS `design_json` and the final, inline-styled `html_body`.
* **`landing_page_templates`**: Stores the headless CMS layout inside a nested `page_schema` JSON column.

---

## 3. The Complete Template Lifecycle

### Phase 1: Variable Definition (Super Admin)

Before a template can be built, the system must know what data is available.

* A Super Admin creates an Event (e.g., "Diwali Promo").
* The Super Admin adds allowed tags to `event_variables` (e.g., `{{ reward_value }}`, `{{ claim_link }}`).

### Phase 2: UI Hydration (Frontend Editor)

When a Business Head opens GrapesJS to design an email:

1. React fetches the `event_variables` for the selected Event.
2. The UI renders a dropdown menu containing only these explicitly allowed tags.
3. The user drags and drops these variables into their email design.

### Phase 3: Strict Save Validation (The Gatekeeper)

When the user clicks "Save", the Laravel Controller acts as a strict gatekeeper to prevent corrupted or fake tags from entering the database.

**Email Validation Logic:**
The backend extracts all text between `{{` and `}}` using Regex (`preg_match_all`), compares it against the `event_variables` table, and throws a `422 Unprocessable Entity` error if unauthorized tags exist.

```php
// EmailTemplateController.php
$contentToValidate = ($validated['subject'] ?? '') . ' ' . ($validated['html_body'] ?? '');

preg_match_all('/{{\s*(.*?)\s*}}/', $contentToValidate, $matches);
$usedTags = array_unique($matches[1]);

// ... compares $usedTags against allowed values in event_variables
// Throws ValidationException if invalid tags are found.

```

**Landing Page Validation Logic:**
Because landing pages use complex JSON schemas rather than flat HTML, the backend flattens the JSON into a searchable string before running the exact same Regex check.

```php
// LandingPageController.php
$contentParts = [
    $validated['title'] ?? '',
    isset($validated['seo_meta']) ? json_encode($validated['seo_meta']) : '',
    isset($validated['page_schema']) ? json_encode($validated['page_schema']) : ''
];
$contentToValidate = implode(' ', $contentParts);

```

### Phase 4: Campaign Execution & Dictionary Mapping

When a campaign launches, `DispatchCampaignCommsJob` loops through thousands of recipients. It relies on the **Global Parser Service** to swap tags instantly without hitting the database.

**The EmailParserService:**
A highly efficient utility that swaps tags with real data, gracefully replacing missing data with an empty space.

```php
namespace App\Services;

class EmailParserService
{
    public static function parse(string $text, array $payload): string
    {
        return preg_replace_callback('/{{\s*(.*?)\s*}}/', function ($matches) use ($payload) {
            $key = trim($matches[1]); 
            return $payload[$key] ?? ''; 
        }, $text);
    }
}

```

**The Payload Dictionary (Inside the Background Job):**
The developer creates an array where the **keys perfectly match the database variables**.

```php
// DispatchCampaignCommsJob.php
foreach ($entitlements as $entitlement) {
    // 1. Build the dictionary for this specific user
    $payload = [
        'first_name'   => $user->first_name,
        'last_name'    => $user->last_name,
        'company_name' => $user->company->name ?? '',
        'reward_value' => $entitlement->reward_value,
        'claim_link'   => $claimUrl ?? '#',
        'claim_code'   => $entitlement->claim_code ?? 'N/A',
    ];

    // 2. Parse the HTML and Subject
    $htmlBody = EmailParserService::parse($template->html_body, $payload);
    $finalSubject = EmailParserService::parse($template->subject, $payload);

    // 3. Send the email
    Mail::html($htmlBody, function ($message) use ($user, $finalSubject) {
         $message->to($user->email)->subject($finalSubject);
    });
}

```

---

## 4. Developer Playbook: How to Add a New Variable

If the Marketing team requests a new dynamic variable (e.g., the employee's `{{ department_name }}`), follow these exact 3 steps. Do not deviate.

### Step 1: Update the Database

Add a new row to the `event_variables` table.

* `name`: "Department Name"
* `value`: `{{ department_name }}`
* `event_id`: Null (if global) or specific to an event.
*(Result: The GrapesJS UI will automatically show this in the dropdown, and the Controller will now allow it to be saved).*

### Step 2: Update the Job Payload

Open `DispatchCampaignCommsJob.php` and add the new key to the `$payload` array so the parser has the actual data.

```php
$payload = [
    // ... existing variables ...
    'department_name' => $user->department->name ?? 'Your Department', // <-- ADDED
];

```

### Step 3: Restart the Queue

Because you modified a PHP Job file that runs in the background, you must restart your queue workers.

```bash
php artisan queue:restart

```

By following this architecture, your template system remains infinitely scalable, strictly secure against bad data, and heavily decoupled for high-speed background processing.