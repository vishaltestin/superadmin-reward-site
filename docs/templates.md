Here is the official technical documentation for your new Template Engines. This is structured so you can hand it directly to your Laravel and React developers to ensure everyone understands the architecture, the database rules, and the expected workflows.

---

# Technical Architecture: Platform Template Engines
**Version:** 1.0
**Core Philosophy:** Headless flexibility, strict mobile responsiveness, and zero "broken layouts." 

## 1. Overview
The platform utilizes a **Dual-Engine Template Architecture** to handle communication (Emails) and conversion (Landing Pages). Both engines rely on a **Master/Override Fallback System**.
* **Super Admins (Filament):** Create "Global Master" templates.
* **Company Admins (React Portal):** Can duplicate these masters and create unlimited, company-specific variations.

The core rule for both systems: `company_id = null` signifies a Global Master Template owned by the platform.

---

## 2. Module A: The Email Template Engine
A hybrid code/visual system designed to allow Super Admins to paste complex ThemeForest HTML, while giving Company Admins a safe, visual way to edit text without breaking table structures.

### 2.1 Database Architecture (`email_templates`)
* **`event_id`**: Foreign key linking the template to a specific trigger (e.g., "New Joinee").
* **`company_id`**: Determines ownership. If `null`, the platform owns it. If an integer, the specific company owns it.
* **`html_body`**: `LongText` column storing the final, responsive HTML code. 

### 2.2 Workflows
#### **Super Admin (Filament Interface)**
* Uses a **Code Editor** with an adjacent **Live HTML Preview**.
* Pastes raw, responsive HTML from external sources (e.g., ThemeForest).
* Injects dynamic merge tags (e.g., `{{ first_name }}`).

#### **Company Admin (React Interface)**
* Views Global Masters in a read-only list.
* Clicks "Duplicate & Edit" to create a local variation.
* The React frontend loads the HTML into a **WYSIWYG Editor** (e.g., React-Quill or SunEditor).
* The Admin edits text and colors visually, preserving the underlying responsive `<table>` tags.
* Saves the variation, generating a new row in the database tied to their `company_id`.

---

## 3. Module B: The Headless Landing Page Engine
A JSON-driven block builder. To protect the React frontend from broken raw HTML, landing pages are stored as "Design Tokens" and ordered component schemas.

### 3.1 Database Architecture (`landing_page_templates`)
* **`name`**: Internal tracking name (e.g., "Master Diwali V1").
* **`title`**: Public-facing `<title>` tag editable by the client.
* **`status`**: String enum (`draft`, `published`, `archived`) to protect live traffic.
* **`global_theme_tokens` (JSON)**: High-level CSS variables (e.g., Primary Color, Font Family) that cascade down to all blocks on the page.
* **`seo_meta` (JSON)**: OpenGraph data for social sharing (Title, Description, Image URL).
* **`page_schema` (JSON)**: The core payload. An ordered array of objects dictating which React components to load and what data to pass them.

### 3.2 The JSON Component Schema
The platform uses **Semantic Variants** rather than raw inline CSS. The JSON payload sent to React follows this strict structure:

```json
[
  {
    "id": "sec_hero_01",
    "type": "hero_banner",
    "isVisible": true,
    "properties": [
      { "key": "titleText", "type": "text", "value": "Claim Your Bonus!" },
      { "key": "designVariant", "type": "select", "value": "confetti_style" }
    ]
  }
]
```

### 3.3 Workflows
#### **Super Admin (Filament Interface)**
* Uses Filament's native **Builder** and **Repeater** fields to construct complex JSON arrays without writing code manually.
* Defines the available blocks (Hero, Claim UI, Video) and the configurable properties for each.

#### **Company Admin (React Interface)**
* Interacts with a split-screen **Schema-Driven UI**.
* **Left Sidebar:** React reads the JSON `properties` array and dynamically renders form inputs (Color Pickers, Text Boxes, Dropdowns).
* **Right Canvas:** React reads the `page_schema` array and renders the hardcoded UI components in real-time as the state changes.
* Admins can hide, show, and reorder sections, but cannot break the mobile layout.

### 3.4 React Frontend Implementation (Component Mapping)
The React application must act as a "Traffic Cop". It does not parse HTML. It loops through the `page_schema` array and mounts the corresponding local component:

```jsx
const SectionMap = {
  hero_banner: HeroComponent,
  claim_ui: ClaimComponent,
};

// Inside the Page Renderer:
const SpecificSection = SectionMap[schemaBlock.type];
return <SpecificSection data={schemaBlock.properties} />
```

---

This documentation should give your entire team perfect clarity on how the data moves from the database, through Filament, and out to the React frontend. 

Now that the foundation for the templates is locked in, do you want to map out the API endpoints that your React developers will use to fetch and save this data, or should we move on to the actual **Campaign / Sending Logic**?