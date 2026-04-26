Here is the complete, master architectural documentation. Save this in your project repository (e.g., `docs/email-campaign-architecture.md`). This covers everything we built today, exactly how the GrapesJS hack works, and the blueprint for how your future "Campaigns" module will connect to it.

---

# 🏗️ Architecture Docs: Email Template Builder & Campaign Integration

## 📑 1. Core Philosophy
This module provides an enterprise-grade email building experience. The system is built on a **Backend-Driven, Dictionary-Mapped Architecture**. 
* **The Frontend is "Dumb":** React never hardcodes variable names. It only renders what the database allows.
* **No If/Else Hell:** The backend sender uses Regex mapping, meaning the system doesn't care if an email is for a "Birthday" or a "Purchase"—it just blindly maps arrays to tags.
* **Fail-Safe:** If a user inserts a variable `{{ tracking_url }}` but the campaign doesn't have that data, the system gracefully replaces the tag with an empty string rather than crashing or showing brackets to the recipient.

---

## 🗄️ 2. Database Schema (The Three Pillars)

To make variables dynamic and UI-manageable, the system relies on three interconnected tables:

1. **`events` (or `campaign_types`)**: The trigger. (e.g., "New Joinee", "Birthday", "Purchase").
2. **`email_templates`**: The actual design. 
   * `event_id`: Links the design to its trigger.
   * `html_body`: The final, inline-styled HTML.
   * `design_json`: The raw GrapesJS state.
3. **`event_variables`**: The dictionary of allowed dynamic tags.
   * `event_id`: (Nullable). If NULL, the variable is Global (e.g., `{{ first_name }}`). If set, it only applies to that specific event.
   * `name`: UI Label (e.g., "First Name").
   * `value`: System tag (e.g., `{{ first_name }}`).

---

## 🖥️ 3. Frontend Implementation (GrapesJS)

### The Editor Initialization (`TemplateEditor.tsx`)
* **Sanctum Uploads:** We pass `credentials: "include"` and the `X-XSRF-TOKEN` cookie directly into the GrapesJS `assetManager` so image uploads hit the Laravel API safely.
* **Saving (Inlined CSS):** Emails *require* inline CSS to bypass Outlook/Gmail stripping. We explicitly run `editor.runCommand("gjs-get-inlined-html")` on save to bake the CSS directly into the HTML tags.
* **State Management:** We prevent cascading React renders by avoiding `useEffect` for state population. The settings modal state is only hydrated precisely when the "Settings" button is clicked.

### The Custom Variables Plugin Hack (`grapesjs-dynamic-vars.ts`)
The `newsletterPreset` aggressively blocks custom toolbar buttons. We bypassed it using a native DOM hack:
1. We construct the `<select>` HTML string using the variables from Laravel.
2. We inject it into the `editor.RichTextEditor.add()` payload.
3. We use `editor.on("load")` combined with `requestAnimationFrame` to wait for GrapesJS to render the UI, and then we attach native `addEventListener("change")` listeners directly to the DOM node.
4. We apply `flex-shrink: 0` to prevent the GrapesJS flexbox toolbar from squishing the dropdown text.

---

## ⚙️ 4. Backend Preparation (Laravel API)

When the frontend requests a template via `GET /email-templates/{id}`, the `EmailTemplateController` acts as a gatekeeper:
```php
$variables = EventVariable::where('is_active', true)
    ->where(function ($query) use ($template) {
        $query->whereNull('event_id') // Get Globals
              ->orWhere('event_id', $template->event_id); // Get Event Specific
    })->get(['name', 'value']);

$template->available_variables = $variables;
```
This guarantees the user only sees variables in their dropdown that the backend actually supports for that specific campaign.

---

## 🚀 5. FUTURE ROADMAP: How Campaigns Will Execute This

When you build the Campaign/Trigger module, here is the exact architectural blueprint of how you will fetch these templates and send them.

### Step 1: The Global Parser Service
You will create a single, centralized parser that converts the GrapesJS HTML into a personalized email.

```php
namespace App\Services;

class EmailParserService
{
    public static function parse(string $htmlBody, array $payload): string
    {
        // Finds all {{ tags }} and replaces them with matching array keys.
        // If the key is missing from the payload, it fails safely to '' (blank space).
        return preg_replace_callback('/{{\s*(.*?)\s*}}/', function ($matches) use ($payload) {
            $key = $matches[1];
            return $payload[$key] ?? ''; 
        }, $htmlBody);
    }
}
```

### Step 2: The Campaign Dispatcher (Job / Listener)
When a campaign fires, the developer simply builds a payload array that matches the "Promise" made in the `event_variables` database.

**Example: A "New Joinee" Campaign Job:**
```php
public function handle(User $newEmployee)
{
    // 1. Fetch the active template for this specific event/campaign
    $template = EmailTemplate::where('event_id', $this->newJoineeEventId)
                             ->where('is_active', true)
                             ->first();

    // 2. Build the exact payload promised in the database
    $payload = [
        'first_name' => $newEmployee->first_name,
        'last_name' => $newEmployee->last_name,
        'company_name' => $newEmployee->company->name,
        'login_url' => 'https://app.yourplatform.com/login',
        'password' => 'Welcome123!' // default assigned pass
    ];

    // 3. Parse the dynamic HTML
    $finalHtml = \App\Services\EmailParserService::parse($template->html_body, $payload);
    
    // 4. (Optional) Parse the subject line too!
    $finalSubject = \App\Services\EmailParserService::parse($template->subject, $payload);

    // 5. Send it via Laravel Mail / Postmark / SES
    Mail::html($finalHtml, function ($message) use ($newEmployee, $finalSubject) {
        $message->to($newEmployee->email)
                ->subject($finalSubject);
    });
}
```

---

## 📝 6. Developer "Rules of the Road"

1. **The Payload Contract:** If you add `{{ order_id }}` to the database, you **MUST** ensure the Campaign Dispatcher array includes `'order_id' => $value`. If you forget, it will silently send an empty space.
2. **Never Free-Type:** If users ask for a new variable, do not tell them to just "type it in brackets." Tell the Super Admin to add it to the `event_variables` table so it shows up in the dropdown.
3. **Template Versioning:** Because Campaigns depend on `html_body`, always ensure GrapesJS successfully saves the *inlined* HTML. If you ever update GrapesJS versions, verify the `gjs-get-inlined-html` command still functions correctly.