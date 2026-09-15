# EFMS — Enterprise Financial Management System

A lightweight, dependency-free PHP MVC framework purpose-built for
financial applications: double-entry general ledger, chart of
accounts, and accounts-receivable invoicing, with an architecture
designed so new financial modules (budgets, bills/AP, payroll, fixed
assets, multi-currency, etc.) can be added quickly without touching
the framework core.

No heavy framework dependency (no Laravel/Symfony) — just PHP 8.1+,
PDO, and ~1,500 lines of core code you can read end to end in an
afternoon. That's a deliberate design choice: in a financial system,
"easy to audit" matters as much as "easy to extend."

## 1. Requirements

- PHP 8.1+ with PDO SQLite extension
- Composer
- A webserver (Apache with mod_rewrite, or Nginx / `php -S`)

## 2. Setup

```bash
composer install
cp .env.example .env

# Initialize and seed the SQLite database:
php database/setup.php
# or: composer db:init

# Dev server (webroot MUST be /public):
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`, log in with the seeded admin account
(`admin@example.com` / `password`), and **change that password
immediately** — regenerate the hash with
`password_hash('new-password', PASSWORD_BCRYPT)` and update the
`users` row.

For production, point your Apache/Nginx vhost's document root at
`/public` (an `.htaccess` is included for Apache; for Nginx, proxy
all non-file requests to `public/index.php`).

## 3. Directory structure

```
app/
  Core/          Framework internals (Router, Model, DB, Auth, ...)
  Controllers/   HTTP request handlers
  Models/        Business/domain objects (Active Record)
  Middleware/    Auth/role guards run before a controller
  Views/         Plain-PHP templates, one folder per resource
config/          app.php, database.php — env-driven settings
database/        schema.sql
public/          Web root: index.php (front controller), assets/
routes/          web.php — the single map of every URL in the app
storage/logs/    App + audit logs (file-based)
```

## 4. How a request flows

```
public/index.php
  → App::run()            bootstraps config, loads routes
  → Router::dispatch()     matches method+URI, runs middleware, calls controller
  → Controller             validates input, talks to Model(s)
  → Model                  talks to the database via QueryBuilder
  → Controller             returns a Response (view or JSON or redirect)
  → Response::send()       the ONLY place that actually echoes/sets headers
```

Every layer is a plain class with no magic/reflection-heavy container,
so you can `Cmd+Click` through the whole request lifecycle in an IDE.

## 5. The core financial model

- **`Account`** — chart of accounts (asset/liability/equity/revenue/expense).
  `Account::balance($id)` computes a live balance from posted journal lines.
- **`JournalEntry` + `JournalLine`** — the general ledger. **All writes
  go through `JournalEntry::post($header, $lines)`**, which:
  1. rejects entries where total debits ≠ total credits,
  2. wraps the header + line inserts in one DB transaction,
  3. writes an audit-log entry.
  Never insert into `journal_lines` directly — that's how books go
  out of balance.
- **`Invoice` + `InvoiceItem`** — an example of a document that
  *produces* ledger activity. `Invoice::postToLedger()` shows the
  pattern: build the two lines (Debit A/R, Credit Revenue) and hand
  them to `JournalEntry::post()`. Copy this pattern for bills,
  payroll runs, depreciation entries, etc. — anything that should
  hit the GL should end its write path in `JournalEntry::post()`.

## 6. Adding a new module (the main extensibility path)

Say you want to add **Vendor Bills (Accounts Payable)**. This is the
whole checklist:

**1. Migration** — add a `bills` (+ `bill_items`) table to
`database/schema.sql` (or a new numbered migration file if you adopt
a migration tool later).

**2. Model** — `app/Models/Bill.php`:

```php
class Bill extends Model
{
    protected static string $table = 'bills';
    protected static array $fillable = ['vendor_name', 'bill_date', 'due_date', 'total', 'status'];
    protected static array $casts = ['total' => 'float'];

    public static function postToLedger(int $billId, int $expenseAccountId, int $apAccountId): array
    {
        $bill = static::findOrFail($billId);

        return JournalEntry::post(
            ['entry_date' => date('Y-m-d'), 'memo' => 'Bill posted', 'source_type' => 'bill', 'source_id' => $billId],
            [
                ['account_id' => $expenseAccountId, 'debit' => $bill['total'], 'credit' => 0],
                ['account_id' => $apAccountId, 'debit' => 0, 'credit' => $bill['total']],
            ]
        );
    }
}
```

That's it for data access — `Model` already gives you `find()`,
`all()`, `query()`, `create()`, `update()`, `destroy()`.

**3. Controller** — `app/Controllers/BillController.php`, extending
`Controller` and using `$this->view()`, `$this->validate()`,
`$this->redirect()` — copy `AccountController` as a template for
standard CRUD, or `InvoiceController` if it posts to the ledger.

**4. Routes** — one line in `routes/web.php`:

```php
$router->resource('bills', BillController::class);
```

or explicit routes if it's not standard CRUD (see how `/transactions`
is wired, since journal entries are append-only).

**5. Views** — `app/Views/bills/{index,form,show}.php`, following the
existing view files as templates (they already share `app.css` and
the sidebar layout).

**6. Sidebar link** — one line in `app/Views/layouts/app.php`.

No core framework file needs to change. This is what "easy to
extend" means concretely in this codebase.

## 7. Extending the framework itself

- **New validation rule**: add a `case` to `Validator::applyRule()`.
- **New middleware**: implement `MiddlewareInterface`, attach via
  `$router->group('/prefix', [YourMiddleware::class], fn ($r) => ...)`.
- **New relation type**: `Model::belongsTo()` / `Model::hasMany()`
  are intentionally simple (return arrays, not lazy-loaded objects) —
  extend them in the base `Model` class if you need `hasOne` or
  `belongsToMany`.
- **Swap the logger**: `Logger` exposes 4 static methods
  (`info/warning/error/audit`); reimplement them against Monolog if
  you outgrow file logs.
- **API mode**: `Controller::json()` + `Request::isJson()` already
  support JSON in/out per-endpoint if you want a REST API alongside
  the HTML UI — no separate app needed.

## 8. Security notes

- All queries are parameterized via PDO (`QueryBuilder` builds
  `?`-placeholder SQL; `Model` writes bind values, never interpolate).
- Passwords are hashed with `password_hash()` (bcrypt) and verified
  with `password_verify()`.
- Sessions are regenerated on login/logout to mitigate fixation.
- All ledger-affecting actions write to `storage/logs/audit-*.log`
  via `Logger::audit()` — extend this to a DB-backed audit table if
  you need queryable compliance reporting.
- `Account::destroy()` is intentionally *not* wired to a hard delete
  in the sample `AccountController` — accounts get soft-disabled
  (`is_active = 0`) because financial history must never disappear.
- Set `APP_DEBUG=false` in production — the default error handler in
  `App::errorResponse()` hides exception details unless debug mode
  is on.

## 9. Testing

`composer.json` wires in PHPUnit (`composer test`). Because `Model`
talks to `Database::getInstance()` (a singleton over PDO), the
simplest approach for model tests is a dedicated test database
pointed to by `.env.testing` — wire that up in `phpunit.xml` if/when
you add tests, as none are included in this scaffold.

## 10. What's intentionally NOT included

This is a framework + a working example (chart of accounts, GL,
invoicing) — not a finished ERP. Deliberately left out so you can
shape them to your needs: multi-currency, tax engines, recurring
invoices, bank reconciliation/import, approval workflows, and a
migrations CLI (routes/schema are hand-maintained here for
transparency). The `JournalEntry::post()` pattern is the foundation
all of those would build on.
