# Filament v3 → v4 Upgrade Reference

> Complete code-level migration guide: namespaces, class renames, method signatures, action changes, table changes, URL parameters, CSS/theme, and config.

---

## Table of Contents

1. [Requirements](#requirements)
2. [Automated Upgrade Script](#automated-upgrade-script)
3. [Schema / Form / Infolist Namespace Changes](#schema--form--infolist-namespace-changes)
4. [Layout Component Namespace Changes](#layout-component-namespace-changes)
5. [Action Namespace Changes](#action-namespace-changes)
6. [Table Method Changes](#table-method-changes)
7. [Resource & Page Changes](#resource--page-changes)
8. [Relation Manager Changes](#relation-manager-changes)
9. [URL Parameter Renames](#url-parameter-renames)
10. [CSS / Theme Changes](#css--theme-changes)
11. [Configuration Changes](#configuration-changes)
12. [Removed / Deprecated](#removed--deprecated)
13. [Tenancy Changes](#tenancy-changes)
14. [What the Upgrade Script Does vs. Manual Steps](#what-the-upgrade-script-does-vs-manual-steps)

---

## Requirements

| Dependency | v3 | v4 |
|---|---|---|
| PHP | 8.1+ | **8.2+** |
| Laravel | 10+ | **11.28+** |
| Tailwind CSS (custom theme) | v3 | **v4.1+** |
| `doctrine/dbal` | Required by Filament | No longer required by Filament (add yourself if your app needs it) |
| PHPStan (for upgrade script) | Any | **v2+** (or Larastan v3+) |

---

## Automated Upgrade Script

Run this first. It handles most repetitive changes automatically.

```bash
composer require filament/upgrade:"^4.0" -W --dev

vendor/bin/filament-v4

# Run the commands output by the script (unique to your app), e.g.:
composer require filament/filament:"^4.0" -W --no-update
composer update
```

**Windows PowerShell** (ignores `^`):

```bash
composer require filament/upgrade:"~4.0" -W --dev
vendor/bin/filament-v4
```

**Optional: migrate to new directory structure**

```bash
# Preview changes first:
php artisan filament:upgrade-directory-structure-to-v4 --dry-run

# Apply:
php artisan filament:upgrade-directory-structure-to-v4
```

**Clean up after upgrade:**

```bash
composer remove filament/upgrade --dev
```

> **Note:** The upgrade script does NOT handle all breaking changes. Read the manual sections below in full.

---

## Schema / Form / Infolist Namespace Changes

This is the biggest architectural change in v4. Forms and Infolists are unified under a new `Filament\Schemas` namespace.

### Injected parameter type

| v3 | v4 |
|---|---|
| `Filament\Forms\Form` | `Filament\Schemas\Schema` |
| `Filament\Infolists\Infolist` | `Filament\Schemas\Schema` |

### Method signatures

**v3:**
```php
use Filament\Forms\Form;
use Filament\Infolists\Infolist;

public static function form(Form $form): Form
{
    return $form->schema([...]);
}

public function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([...]);
}
```

**v4:**
```php
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema->components([...]);
}

public function infolist(Schema $schema): Schema
{
    return $schema->components([...]);
}
```

### Schema object method rename

| v3 | v4 |
|---|---|
| `$form->schema([...])` | `$schema->components([...])` |

### Trait and interface renames (Livewire components)

| v3 | v4 |
|---|---|
| `Filament\Forms\Concerns\InteractsWithForms` | `Filament\Schemas\Concerns\InteractsWithSchemas` |
| `Filament\Forms\Contracts\HasForms` | `Filament\Schemas\Contracts\HasSchemas` |
| `Filament\Infolists\Concerns\InteractsWithInfolists` | *(merged into `InteractsWithSchemas`)* |
| `Filament\Infolists\Contracts\HasInfolists` | *(merged into `HasSchemas`)* |

### Utility injection classes

| v3 | v4 |
|---|---|
| `Filament\Forms\Set` | `Filament\Schemas\Components\Utilities\Set` |
| `Filament\Forms\Get` | `Filament\Schemas\Components\Utilities\Get` |
| `callable $set` / `callable $get` in closures | Must use typed `Set $set` / `Get $get` injection |

**v3:**
```php
->afterStateUpdated(function (callable $set, $state) {
    $set('other_field', $state);
})
```

**v4:**
```php
use Filament\Schemas\Components\Utilities\Set;

->afterStateUpdated(function (Set $set, $state) {
    $set('other_field', $state);
})
```

### Action wrapper inside a schema

| v3 | v4 |
|---|---|
| `Filament\Forms\Components\Actions` (wrapper) | `Filament\Schemas\Components\Actions` |

**v4 usage:**
```php
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;

->components([
    Actions::make([
        Action::make('myAction')->action(fn() => ...),
    ]),
])
```

---

## Layout Component Namespace Changes

Layout / structural components moved from `Filament\Forms\Components` and `Filament\Infolists\Components` into the shared `Filament\Schemas\Components` namespace. **Form fields** (`TextInput`, `Select`, etc.) and **infolist entries** (`TextEntry`, etc.) remain in their original namespaces.

| v3 | v4 |
|---|---|
| `Filament\Forms\Components\Section` | `Filament\Schemas\Components\Section` |
| `Filament\Forms\Components\Grid` | `Filament\Schemas\Components\Grid` |
| `Filament\Forms\Components\Tabs` | `Filament\Schemas\Components\Tabs` |
| `Filament\Forms\Components\Tabs\Tab` | `Filament\Schemas\Components\Tabs\Tab` |
| `Filament\Forms\Components\Wizard` | `Filament\Schemas\Components\Wizard` |
| `Filament\Forms\Components\Wizard\Step` | `Filament\Schemas\Components\Wizard\Step` |
| `Filament\Forms\Components\Fieldset` | `Filament\Schemas\Components\Fieldset` |
| `Filament\Forms\Components\Group` | `Filament\Schemas\Components\Group` |
| `Filament\Forms\Components\Placeholder` | `Filament\Schemas\Components\Placeholder` |
| `Filament\Infolists\Components\Section` | `Filament\Schemas\Components\Section` *(merged)* |
| `Filament\Infolists\Components\Grid` | `Filament\Schemas\Components\Grid` *(merged)* |
| `Filament\Infolists\Components\Tabs` | `Filament\Schemas\Components\Tabs` *(merged)* |

> **These do NOT move** (still in original namespaces):
> - `Filament\Forms\Components\TextInput`, `Select`, `Checkbox`, `FileUpload`, `Repeater`, `Builder`, etc.
> - `Filament\Infolists\Components\TextEntry`, `ImageEntry`, `IconEntry`, etc.

---

## Action Namespace Changes

All Action classes are now unified under a single `Filament\Actions` namespace.

### Action class imports

| v3 | v4 |
|---|---|
| `Filament\Tables\Actions\Action` | `Filament\Actions\Action` |
| `Filament\Forms\Components\Actions\Action` | `Filament\Actions\Action` |
| `Filament\Infolists\Components\Actions\Action` | `Filament\Actions\Action` |
| `Filament\Tables\Actions\BulkAction` | `Filament\Actions\BulkAction` |
| `Filament\Tables\Actions\DeleteAction` | `Filament\Actions\DeleteAction` |
| `Filament\Tables\Actions\EditAction` | `Filament\Actions\EditAction` |
| `Filament\Tables\Actions\ViewAction` | `Filament\Actions\ViewAction` |
| `Filament\Tables\Actions\CreateAction` | `Filament\Actions\CreateAction` |
| `Filament\Tables\Actions\ReplicateAction` | `Filament\Actions\ReplicateAction` |
| `Filament\Tables\Actions\ForceDeleteAction` | `Filament\Actions\ForceDeleteAction` |
| `Filament\Tables\Actions\RestoreAction` | `Filament\Actions\RestoreAction` |
| `Filament\Tables\Actions\ImportAction` | `Filament\Actions\ImportAction` |
| `Filament\Tables\Actions\ExportAction` | `Filament\Actions\ExportAction` |
| `Filament\Tables\Actions\DeleteBulkAction` | `Filament\Actions\DeleteBulkAction` |
| `Filament\Tables\Actions\ForceDeleteBulkAction` | `Filament\Actions\ForceDeleteBulkAction` |
| `Filament\Tables\Actions\RestoreBulkAction` | `Filament\Actions\RestoreBulkAction` |

### Action modal form → schema

| v3 | v4 |
|---|---|
| `->form([...])` on an Action | `->schema([...])` on an Action |

**v3:**
```php
Action::make('approve')
    ->form([
        Textarea::make('reason')->required(),
    ])
```

**v4:**
```php
Action::make('approve')
    ->schema([
        Textarea::make('reason')->required(),
    ])
```

---

## Table Method Changes

| v3 | v4 | Notes |
|---|---|---|
| `->actions([...])` | `->recordActions([...])` | Per-row actions on the `Table` object |
| `->headerActions([...])` | `->toolbarActions([...])` | Toolbar-level table actions |
| `->deferFilters()` | *(removed — now the default)* | Deferred filter behavior is on by default in v4 |

**v3:**
```php
public static function table(Table $table): Table
{
    return $table
        ->actions([
            EditAction::make(),
            DeleteAction::make(),
        ])
        ->headerActions([
            CreateAction::make(),
        ])
        ->deferFilters();
}
```

**v4:**
```php
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;

public static function table(Table $table): Table
{
    return $table
        ->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ])
        ->toolbarActions([
            CreateAction::make(),
        ]);
        // deferFilters() no longer needed — it's the default
}
```

### New table methods in v4

| Method | Description |
|---|---|
| `->toolbarActions([...])` | Place actions and bulk actions in the toolbar |
| `->records(fn() => [...])` | Provide custom (non-Eloquent) data to a table |
| `BulkAction::make()->chunkSelectedRecords()` | Process selected records in batches |
| `BulkAction::make()->authorizeIndividualRecords()` | Policy check per selected record |

---

## Resource & Page Changes

### `$navigationIcon` property type

The upgrade script widens the type automatically.

| v3 | v4 |
|---|---|
| `protected static ?string $navigationIcon` | `protected static string\|\BackedEnum\|null $navigationIcon` |

### Directory structure (optional migration)

| v3 | v4 (new default) |
|---|---|
| `app/Filament/Resources/UserResource.php` | `app/Filament/Resources/Users/UserResource.php` |
| `app/Filament/Resources/UserResource/Pages/` | `app/Filament/Resources/Users/Pages/` |
| `app/Filament/Resources/UserResource/RelationManagers/` | `app/Filament/Resources/Users/RelationManagers/` |

Run `php artisan filament:upgrade-directory-structure-to-v4` to migrate. The old flat structure still works if you skip this.

### New recommended code structure (optional)

In v4, Filament encourages extracting forms and tables into dedicated classes:

**Schema class** (`app/Filament/Resources/Users/Schemas/UserForm.php`):
```php
namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
        ]);
    }
}
```

**Table class** (`app/Filament/Resources/Users/Tables/UserTable.php`):
```php
namespace App\Filament\Resources\Users\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class UserTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
        ]);
    }
}
```

The old inline style (defining form/table directly inside the resource) still works fine.

### Default redirect after create

New in v4 — configure globally in panel:

```php
->redirectAfterCreate('index') // or 'view', 'edit'
```

### Global search term splitting

| v3 | v4 |
|---|---|
| Always splits search term on spaces | Controllable via `$shouldSplitGlobalSearchTerms` property |

```php
// v4 — disable splitting for performance on large datasets:
protected bool $shouldSplitGlobalSearchTerms = false;
```

---

## Relation Manager Changes

Relation managers follow the same Schema signature change:

| v3 | v4 |
|---|---|
| `public function form(Form $form): Form` | `public function form(Schema $schema): Schema` |
| `public function infolist(Infolist $infolist): Infolist` | `public function infolist(Schema $schema): Schema` |
| `->actions([...])` on Table | `->recordActions([...])` on Table |
| `->headerActions([...])` on Table | `->toolbarActions([...])` on Table |

---

## URL Parameter Renames

These are query string / route parameters used in `::getUrl()` calls and URL generation. Update any hardcoded references.

| v3 parameter | v4 parameter | Page type |
|---|---|---|
| `activeRelationManager` | `relation` | Edit / View resource pages |
| `activeTab` | `tab` | List / Manage Relation pages |
| `isTableReordering` | `reordering` | List / Manage Relation pages |
| `tableFilters` | `filters` | List / Manage Relation pages |
| `tableGrouping` | `grouping` | List / Manage Relation pages |
| `tableGroupingDirection` | `groupingDirection` | List / Manage Relation pages |
| `tableSearch` | `search` | List / Manage Relation pages |
| `tableSort` | `sort` | List / Manage Relation pages |

**v3:**
```php
UserResource::getUrl('edit', [
    'record' => $user,
    'activeRelationManager' => 'posts',
    'tableSearch' => 'foo',
]);
```

**v4:**
```php
UserResource::getUrl('edit', [
    'record' => $user,
    'relation' => 'posts',
    'search' => 'foo',
]);
```

---

## CSS / Theme Changes

Only applies if you use a **custom Filament theme** or use Filament components outside a panel.

### Custom theme CSS file

**v3** (`resources/css/filament/admin/theme.css`):
```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@config 'tailwind.config.js';
```

**v4:**
```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../app/Filament';
@source '../../../../resources/views/filament';

/* Add @source entries for any plugins you use, e.g.: */
@source '../../../../vendor/some-plugin/resources/**/*.blade.php';
```

### PostCSS config

**v3** (`postcss.config.js`):
```js
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
```

**v4:**
```bash
npm install @tailwindcss/postcss
```

```js
export default {
    plugins: {
        '@tailwindcss/postcss': {},
    },
};
```

### Tailwind config

The `tailwind.config.js` file for your theme is **no longer used** in Tailwind v4. Move any customizations into your CSS file.

Run the Tailwind upgrade tool to automate most of this:

```bash
npx @tailwindcss/upgrade
```

---

## Configuration Changes

### Filesystem disk

| v3 | v4 |
|---|---|
| Env var: `FILAMENT_FILESYSTEM_DISK` | Env var: `FILESYSTEM_DISK` |
| Default visibility: public | Default visibility: **private** |

To preserve v3 behavior in `config/filament.php`:

```php
'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),
```

This affects: `FileUpload`, `ImageColumn` (incl. Spatie variants), `ImageEntry`.

### File generation flags

New `file_generation` section in `config/filament.php`. Add these flags to preserve v3 code generation style:

```php
use Filament\Support\Commands\FileGenerators\FileGenerationFlag;

return [
    'file_generation' => [
        'flags' => [
            // Define forms/infolists inline in resource (v3 style):
            FileGenerationFlag::EMBEDDED_PANEL_RESOURCE_SCHEMAS,
            // Define tables inline in resource (v3 style):
            FileGenerationFlag::EMBEDDED_PANEL_RESOURCE_TABLES,
            // Resource classes outside their directories (v3 style):
            FileGenerationFlag::PANEL_RESOURCE_CLASSES_OUTSIDE_DIRECTORIES,
            // Cluster classes outside their directories (v3 style):
            FileGenerationFlag::PANEL_CLUSTER_CLASSES_OUTSIDE_DIRECTORIES,
            // Use partial imports (v3 style):
            FileGenerationFlag::PARTIAL_IMPORTS,
        ],
    ],
];
```

---

## Removed / Deprecated

| v3 | v4 replacement / status |
|---|---|
| `filament/spatie-laravel-translatable-plugin` | **Deprecated.** Use `lara-zeus/translatable` instead |
| `->deferFilters()` on Table | Removed — deferred filters are now the default |
| `callable $set` / `callable $get` closure injection | Removed — use typed `Set $set` / `Get $get` |
| `Filament\Infolists\Concerns\InteractsWithInfolists` | Merged into `InteractsWithSchemas` |
| `Filament\Infolists\Contracts\HasInfolists` | Merged into `HasSchemas` |
| `doctrine/dbal` (Filament dependency) | No longer a Filament dependency; add to your own `composer.json` if needed |
| `tailwind.config.js` for custom themes | Configuration now lives in the CSS file (Tailwind v4) |
| `@config 'tailwind.config.js'` in CSS | Replaced by `@source` directives |

---

## Tenancy Changes

In v3, Filament only scoped **resource table queries**, URL parameter resolution, and global search results to the current tenant. Many other queries were not scoped by default.

In v4, **all queries in the panel are scoped to the current tenant by default.** This is a high-impact change — review your tenancy logic carefully after upgrading to ensure no unintended data leakage or query errors.

---

## What the Upgrade Script Does vs. Manual Steps

### Handled automatically by `vendor/bin/filament-v4`

- `Form $form` / `Infolist $infolist` → `Schema $schema` in all method signatures
- `$form->schema([...])` / `$infolist->schema([...])` → `$schema->components([...])`
- `->actions([...])` → `->recordActions([...])` on Table objects
- `->form([...])` → `->schema([...])` on Action objects
- Import rewrites for most moved classes
- `$navigationIcon` type widening to `string|\BackedEnum|null`
- Detects incompatible plugins and outputs the correct `composer require` commands
- Replaces Spatie Translatable plugin references with Lara Zeus plugin

### Must be done manually

- URL parameter keys in `::getUrl([...])` calls (8 renamed params)
- Tailwind CSS v4 migration (custom theme CSS file, PostCSS config, `npx @tailwindcss/upgrade`)
- Filesystem disk env var (`FILAMENT_FILESYSTEM_DISK` → `FILESYSTEM_DISK`) + visibility review
- Plugin syntax changes beyond version bumps (check each plugin's own upgrade guide)
- Tenancy scoping review
- `callable $set` / `callable $get` → typed injection (if not caught by script)
- `config/filament.php` `file_generation` section (if preserving v3 code style)

---

## Quick Command Reference

```bash
# 1. Run upgrade script
composer require filament/upgrade:"^4.0" -W --dev
vendor/bin/filament-v4

# 2. Install new Filament version (use commands output by script)
composer require filament/filament:"^4.0" -W --no-update
composer update

# 3. (Optional) Migrate directory structure
php artisan filament:upgrade-directory-structure-to-v4 --dry-run
php artisan filament:upgrade-directory-structure-to-v4

# 4. Publish/update config
php artisan vendor:publish --tag=filament-config

# 5. (If using custom theme) Upgrade Tailwind
npm install @tailwindcss/postcss
npx @tailwindcss/upgrade

# 6. Run static analysis to catch leftover broken imports
vendor/bin/phpstan analyse

# 7. Clean up
composer remove filament/upgrade --dev
```

---

*Sources: [filamentphp.com/docs/4.x/upgrade-guide](https://filamentphp.com/docs/4.x/upgrade-guide) · Official Filament v4 announcements · Filament GitHub repository (4.x branch)*