# ODCI Document — Laravel 11 Port

Incremental migration of the legacy Vanilla PHP application at
`C:\laragon\www\ODCI` into this Laravel 11 project.

**The legacy project is read-only reference material. It has not been modified.**

---

## Current status — Stage 1: Authentication (complete and verified)

| Area | State |
|---|---|
| Laravel 11 project | `C:\laragon\www\odci-laravel` |
| Database | The **existing** `odci_db` MySQL schema, connected directly. No migrations have been run against it. |
| Login page | Ported, pixel-for-pixel from `login.php` |
| Login logic | Ported from `login/script/login_function.php` |
| Role gate | Ported from `includes/auth_check.php` (`requireAuth` / `requireRole`) |
| Dashboards | All three render real database counts |
| Tests | 24 passing, 101 assertions |
| Original project | Unmodified (`git status` clean) |

---

## How to run

```bat
cd /d C:\laragon\www\odci-laravel
start-server.bat
```

Then open <http://127.0.0.1:8000/login>.

MySQL must be running. If it is not, start it from the Laragon menu, or:

```
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe --datadir=C:\laragon\data\mysql-8.4
```

### `php artisan serve` needs `--no-reload` on this machine

```bat
php artisan serve --no-reload
```

Plain `php artisan serve` fails on **every** port from 8000 to 8100 with:

```
Failed to listen on 127.0.0.1:8000 (reason: ?)
```

**Root cause** — `ServeCommand.php` line 179:

```php
return in_array($key, static::$passthroughVariables) ? [$key => $value] : [$key => false];
```

Unless `--no-reload` is passed, every `$_ENV` variable that is not in
`$passthroughVariables` is set to `false` in the child process. That list
contains `PATH` and `SYSTEMROOT` in **UPPERCASE**, but on Windows `$_ENV`
actually holds `Path` and `SystemRoot`. `in_array()` is case-sensitive, so both
are wiped — along with `ComSpec`, `TEMP` and `TMP`. Winsock then fails to
initialise and PHP reports the bind failure with the useless `(reason: ?)`.

Line 176 is the escape hatch:

```php
if ($this->option('no-reload') || ! $hasEnvironment) {
    return [$key => $value];   // pass everything through untouched
}
```

**Trade-off:** the server no longer auto-restarts when `.env` changes. Stop it
with `Ctrl+C` and start it again after editing `.env`.

### Other things that were tried and ruled out

For the record, so nobody repeats them:

| Hypothesis | Result |
|---|---|
| Ports 8000-8100 already in use | **False** — `netstat` showed all free, and it still failed |
| Laragon's nginx occupying the port | **False** — nginx serves 80 only |
| `PHP_CLI_SERVER_WORKERS=4` breaking the bind | **False** — `false` and `1` both bind fine; it is left disabled anyway because PHP's worker pre-forking needs POSIX `fork()` |
| Missing `server.php` router script | **False** — Laravel falls back to the framework copy, which works |
| Herd's nginx / PHP-FPM on port 80 | **True but unrelated** — that is a separate `502` on `http://localhost/` |

### `APP_URL` must not contain a sub-directory

```env
APP_URL=http://127.0.0.1:8000
```

Because `public/` is the document root. Laravel's test HTTP client derives the
request path from `APP_URL`, so a value such as `http://localhost/odci-laravel`
makes a request for `/` resolve to `/odci-laravel` and 404.

---

## Environment decisions (and why)

| Setting | Value | Reason |
|---|---|---|
| `DB_*` | existing `odci_db` | Migrate in place; do not create a second database. |
| `SESSION_DRIVER` | `file` | The legacy schema has **no `sessions` table**. File sessions also match the legacy system, which used PHP's default `$_SESSION`. |
| `CACHE_STORE` | `file` | No `cache` table exists. |
| `QUEUE_CONNECTION` | `sync` | No `jobs` table exists. |
| `SESSION_LIFETIME` | `360` | 360 minutes = 21 600 s = `SESSION_TIMEOUT` in `auth_check.php`. |
| `APP_TIMEZONE` | `Asia/Manila` | The legacy system is Philippine-based; the Laravel default is UTC. |

**No schema changes have been made to `odci_db`.**

---

## Legacy → Laravel map

| Legacy file | Route | Controller | Model | View |
|---|---|---|---|---|
| `login.php` | `GET /login` | `Auth\LoginController@showLoginForm` | `User` | `auth/login` |
| `login/script/login_function.php` | `POST /login` | `Auth\LoginController@login` | `User`, `ActivityLog` | — |
| `logout.php` | `POST /logout` | `Auth\LoginController@logout` | `User` | — |
| `includes/auth_check.php` → `requireAuth` | all protected routes | — | `User` | — |
| `includes/auth_check.php` → `requireRole` | dashboard routes | — | `User` | — |
| `roles/user/dashboard.php` | `GET /user/dashboard` | `User\DashboardController` | `Folder`, `File`, `Post`, `Notification`, `Announcement` | `user/dashboard` |
| `roles/admin/dashboard.php` | `GET /admin/dashboard` | `Admin\DashboardController` | `User`, `Department`, `Folder`, `File` | `admin/dashboard` |
| `roles/superadmin/dashboard.php` | `GET /superadmin/dashboard` | `SuperAdmin\DashboardController` | all of the above + `ActivityLog` | `superadmin/dashboard` |
| `login/register.php` | `GET /register` | *placeholder* | — | `auth/pending` |
| `login/forgot_password.php` | `GET /forgot-password` | *placeholder* | — | `auth/pending` |
| `login/script/google_oauth.php` | `GET /login/google` | *placeholder* | — | `auth/pending` |

`register`, `forgot-password` and `login/google` are deliberate placeholders so
the login page has no broken links. They are not migrated yet.

---

## Preserved authentication behaviour

Verified by `tests/Feature/AuthenticationTest.php`:

- Sign in with **username *or* email**.
- Only accounts with `is_approved = 1` may sign in.
- Passwords verified against the existing bcrypt hashes — **existing hashes are
  never re-hashed** on save (guarded by an explicit test).
- 5 failed attempts → account locked for 15 minutes, with the original
  messages:
  - `Invalid username/email or password. N attempts remaining.`
  - `Too many failed login attempts. Your account has been locked for 15 minutes.`
- A correct password is still refused while the lock is active.
- Role-based redirect after login: `super_admin` / `admin` / `user`.
- Logging out invalidates the session.
- Role mismatch redirects to the user's **own** dashboard (not to `/login`),
  matching `preventCrossRoleAccess()`.
- De-approving an account revokes access on the very next request
  (`EnsureAccountIsActive`, mirroring `requireAuth`).
- `login` / `failed_login` / `logout` rows are written to `activity_logs`.

---

## Middleware

| Alias | Class | Legacy equivalent |
|---|---|---|
| `role` | `App\Http\Middleware\RoleMiddleware` | `requireRole()` / `preventCrossRoleAccess()` |
| `active.account` | `App\Http\Middleware\EnsureAccountIsActive` | the body of `requireAuth()` |

> `RoleMiddleware` splits comma-separated roles itself. Laravel passes the
> parameter of `role:admin,super_admin` as a **single** `'admin,super_admin'`
> string; it does not split on commas. Without the split the gate silently
> denies everyone.

---

## Models

All 16 models point at the existing tables and declare `$table` explicitly.
They set **no** `fillable` entries for `role`, `is_approved`, `is_restricted`,
`failed_login_attempts` or `account_locked_until`, so those columns cannot be
tampered with via mass assignment.

```php
$user = new User();
$user->forceFill(['role' => 'admin', 'is_approved' => 1])->save();
```

Tables without `updated_at` (`activity_logs`, `notifications`,
`folder_permissions`, `file_shares`, `file_downloads`, `post_likes`,
`post_media`, `faculty_document_submissions`) set `public $timestamps = false`.

Soft deletes in the legacy system are `is_deleted` columns, **not** Eloquent's
`SoftDeletes`, so models expose `scopeNotDeleted()` instead.

---

## Static assets

Copied (not moved) out of the legacy project into `public/`:

| Legacy path | Laravel path |
|---|---|
| `login/script/css/login.css` | `public/css/login.css` |
| `assets/css/cvsu-auth.css` | `public/css/cvsu-auth.css` |
| `login/img/cvsu-logo.png` | `public/img/cvsu-logo.png` |
| `img/cvsu-naic-bg.jpg` | `public/img/cvsu-naic-bg.jpg` |
| `login/script/js/script.js` | `public/js/auth-script.js` |

One edit was required: `login.css` referenced its background as
`url('../../img/cvsu-naic-bg.jpg')`, which resolved correctly only in the legacy
directory depth. It was re-pointed to the absolute `/img/cvsu-naic-bg.jpg`.
**Only the Laravel copy was changed; the legacy file is untouched.**

---

## Known open items

1. **`remember_token` column does not exist** in `users`. Laravel's
   "Remember me" writes to `users.remember_token`; the legacy system stored its
   remember token in `password_reset_token`. The checkbox is present and the
   request is accepted, but persistence needs either an additive nullable
   column or a custom token store. **Not done — awaiting approval**, since it
   is a schema change.
2. **Google OAuth** is not migrated. The button only renders if
   `GOOGLE_CLIENT_ID` is set, which it currently is not.
3. **Registration and password reset** are placeholders.
4. **Vite / npm asset pipeline** is not wired up. The layout deliberately avoids
   `@vite` because `public/build/manifest.json` does not exist yet and `@vite`
   throws without it.
5. `includes/schema.php` performs self-healing schema repair on every legacy
   bootstrap. That must be understood before any schema work.

---

## Running the tests

```bat
cd /d C:\laragon\www\odci-laravel
php artisan test
```

The tests run against the **real** `odci_db` using `DatabaseTransactions`
(every change is rolled back). They deliberately do **not** use
`RefreshDatabase`, which would migrate and drop tables.

```
Tests:    24 passed (101 assertions)
```