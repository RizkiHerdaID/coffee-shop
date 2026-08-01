---
name: filament-v5
description: Use when working with the Filament v5 admin panel in this project — panel provider config, auth guard wiring, creating/customizing resources (make:filament-resource), the MenuItems resource layout, and writing Filament/Livewire feature tests (login page, form errors, rate limiting).
---

# Filament v5 development (coffee-shop)

The admin panel is Filament v5.7 at `/admin`, installed in commit `05d1826` (replaced the hand-rolled admin auth). Livewire v4 comes as a dependency.

## Panel wiring

- `app/Providers/Filament/AdminPanelProvider.php` — id `admin`, path `admin`, `->authGuard('admin')` (v5 defaults to `web` — must override), brand "Coffee Shop", `->login()`.
- `app/Models/Admin.php` implements `Filament\Models\Contracts\FilamentUser`; `canAccessPanel()` returns `true`.
- `config/auth.php` — `admins` provider + `admin` session guard.
- Filament handles login/logout; there are no custom admin controllers or `resources/views/admin/*` views.

## Resources (v5 layout)

Resources live at `app/Filament/Resources/<Plural>/`:

```
app/Filament/Resources/MenuItems/
├── MenuItemResource.php          # resource + table/config wiring
├── Schemas/MenuItemForm.php      # form components (TextInput, TextArea...)
└── Tables/MenuItemsTable.php     # table columns
```

Scaffold a new resource (the generator reads the DB schema, so point it at a temp sqlite DB when pgsql is unreachable):

```bash
touch /tmp/opencode/filament-setup.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/opencode/filament-setup.sqlite php artisan migrate --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/opencode/filament-setup.sqlite php artisan make:filament-resource MenuItem --generate
```

Existing pattern (MenuItemResource): `price` required numeric (IDR, unsigned int), `note` nullable, `sort_order` default 0, table `money('IDR')` price column, sortable `sort_order`.

## Installation notes (for reference)

`composer require filament/filament:"^5.0" -W` → `php artisan filament:install --panels` (generates panel provider, publishes assets to `public/filament`, adds `@php artisan filament:upgrade` to composer `post-autoload-dump`). No npm/Vite build needed for Filament itself. `php artisan` on the host needs `.env` + `APP_KEY` first.

## Testing Filament (Livewire)

```php
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

Livewire::test(Login::class)
    ->fillForm(['email' => $email, 'password' => 'password'])
    ->call('authenticate')
    ->assertRedirect(route('filament.admin.pages.dashboard'));
```

Gotchas (all learned the hard way):

- **Form error keys are NOT prefixed**: `assertHasFormErrors(['email'])`, never `['data.email']` — Filament prepends the `data` state path itself.
- **`withServerVariables` does NOT affect Livewire requests** — the Livewire `RequestBroker` is a separate object, so you cannot vary the request IP in tests. Test IP-keyed rate limiting deterministically with per-email keys instead.
- **Throttle tests**: `Cache::flush()` in `setUp`; use unique emails per test so limiters don't poison each other. Filament's login rate limit is 5/min.
- Route names: `filament.admin.auth.login` (GET login, not POSTable via HTTP test — use Livewire), `filament.admin.auth.logout`, `filament.admin.pages.dashboard`.
- Auth middleware tests: `actingAs($admin, 'admin')`, `assertAuthenticatedAs($admin, 'admin')`, `assertGuest('admin')`.

## Rate limiting (project-specific)

`app/Providers/AppServiceProvider.php::boot()` registers `RateLimiter::for('admin.login', ...)` — `Limit::perMinute(5)->by(strtolower(email).'|'.ip)` — applied via `throttle:admin.login` middleware on POST `/admin/login` only. Keep this intact; never re-add `trustProxies(at: '*')` (it makes the IP key spoofable).
