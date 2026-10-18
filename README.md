# IV Auto — Salvage Management Portal

Laravel 10 · Vue 3 · Inertia.js 2 · MySQL 8 · Redis (production)

Sign in → Dashboard → **Assignment** (Standard / Transport Only) or **Bid Request**.

## Local setup

```bash
composer install
npm install
cp .env.example .env          # then set DB_* and, without Redis, CACHE_DRIVER / QUEUE_CONNECTION / SESSION_DRIVER=database
php artisan key:generate
php artisan migrate --seed    # seeds a local demo user: demo / password
npm run dev                   # or: npm run build
php artisan serve             # http://127.0.0.1:8000
php artisan queue:work        # processes confirmation emails
```

## Quality checks

Run before every push. CI (`.github/workflows/ci.yml`) runs the same checks on each push and pull request once the
repository is on GitHub.

```bash
composer check        # Pint style + Larastan (level 6) + tests
composer lint:fix     # auto-fix PHP style
npm run lint          # ESLint
npm run format        # Prettier (Vue/JS/CSS)
```

Tests use in-memory SQLite and don't need built assets.

## How the code is organised

Each feature is a **module** in `app/Modules/<Module>/`:

```
app/Modules/
  Auth/          login, logout, sign-in audit trail
  Dashboard/     dashboard page + cached per-user totals
  Assignments/   Standard / Transport Only assignments
  BidRequests/   bid requests
  Submissions/   shared by submission modules: Submission contract, SubmissionService,
                 attachments, SubmissionCreated event, receipt email
```

A module keeps its own `Http/Controllers`, `Http/Requests`, `Models`, `Listeners` and `routes.php`. Its Vue pages
live in `resources/js/Pages/<Module>/`.
- `ModuleServiceProvider` loads every `routes.php` automatically.
- Listeners are discovered automatically: any public `handle*()` method in a module's `Listeners/` folder is wired to the
  event it type-hints.
- Modules don't call each other directly; they react to events. For example, Dashboard clears its cached counts when
  it hears `SubmissionCreated`.

### Adding a module

1. Create `app/Modules/<Name>/` with `routes.php`. Give every route a name; Vue code links with
   `route('name')` (Ziggy), never with hard-coded URLs.
2. Add a migration. Store fields you will search or filter on as indexed columns.
3. If users submit records: implement `Submission` and use the `IsSubmission` trait on the model, then save through
   `SubmissionService::create($user->relation(), …)`. That gives you reference numbers, file storage, the
   receipt email and the dashboard refresh.
4. Add `LogsActivity` to the model, logging only its key fields (see `Assignment::getActivitylogOptions`).
5. Register the model's short morph alias in `AppServiceProvider` (`Relation::enforceMorphMap`).
6. Put pages in `resources/js/Pages/<Name>/` and tests in `tests/Feature/`.

### File uploads

Files upload **in the background as soon as the user picks them**, not when they press Submit:

1. **Compress (browser).** Vehicle photos over 1 MB are resized to 2560 px on the longest side at JPEG quality 0.85,
   with EXIF (capture time, GPS) kept. Typically 4–8 MB becomes about 1 MB. This uses `browser-image-compression` in
   a Web Worker, so the page stays responsive. Documents are never altered. If compression fails, the original is sent.
2. **Upload (browser → `POST /uploads`).** Three files at a time. Network errors, 5xx and 429 responses are retried
   with backoff. A small indicator shows progress while the user keeps filling in the form
   (`resources/js/uploads/uploadQueue.js`).
3. **Store (server).** Each file is validated (type per field, 20 MB max) and written straight to its permanent path.
   The response is an id, recorded in `uploads`.
4. **Submit.** The form sends only the ids. `SubmissionService` locks the user's pending uploads, turns them into
   `attachments` rows and deletes the `uploads` rows in the same transaction. No file is copied, and the same upload
   can't be attached twice. If a file is still uploading at Submit, the button shows "Uploading files… 3 of 12" and
   then continues.
5. **Clean up.** Uploads never attached to a submission are deleted after 24 hours by the hourly
   `model:prune` schedule. Each user can hold at most 200 pending uploads.

**At larger scale:** move files to S3 (`FILESYSTEM_DISK=s3`, `composer require league/flysystem-aws-s3-v3`). After
that, the next step is letting the browser upload straight to S3 with pre-signed URLs, so file bytes never pass through
PHP at all. Only `UploadController` and `uploadQueue.js` would change.

### Audit log and error tracking

- **Audit log** (`spatie/laravel-activitylog`, table `activity_log`) records sign-ins, sign-outs, failed attempts
  (username only, never the password), lockouts, account changes, and the creation of assignments and bid requests,
  each with who did it. Entries older than `ACTIVITY_LOG_RETENTION_DAYS` are pruned nightly.
- **Sentry** reports server and browser errors once `SENTRY_LARAVEL_DSN` / `VITE_SENTRY_DSN` are set. Browser errors
  are tagged with the account id only. With no DSN, nothing is sent and the SDK isn't even bundled.

**Data model.** Fields that are searched or filtered (claim #, VIN, status, user, dates, money) are real indexed
columns. The full form snapshot is stored in a `payload` JSON column, so the long forms can change without a migration
each time. Stock and bid numbers come from the auto-increment id, so they are unique without locking.

### The design forms (`data/` → `resources/js/legacy/`)

The Assignment and Bid Request screens are the designer's prototypes from `data/`, used as they are:

```bash
npm run legacy:sync   # re-import after the designer updates data/*.html / *.css / *.js
```

`scripts/sync-legacy-forms.mjs` generates `resources/js/legacy/<form>/{markup.html,style.css,script.js}`. It also
patches the two submit hooks; if a design change moves a hook, the sync fails loudly instead of breaking submission.
`Components/LegacyForm.vue` mounts the markup and script, sends the submit to Laravel as multipart, and removes the
script's global listeners when the user leaves the page. At build time, Vite scopes each stylesheet to its page
wrapper (`.legacy-assignment`, `.legacy-bid`). Don't edit the generated files by hand; change `data/` and re-sync.

## Production

**Environment.** Start from `.env.example`. It sets `APP_DEBUG=false` and puts cache, session and queue on Redis.
Use `FILESYSTEM_DISK=s3` (or shared storage) once you run more than one web server.

**Deploy:**

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
```

**PHP-FPM / php.ini.** Enable OPcache (`opcache.enable=1`, `opcache.validate_timestamps=0`, then reload FPM on each
deploy). Files arrive one per request (see *File uploads*), so `upload_max_filesize=20M`, `post_max_size=25M` and
Nginx `client_max_body_size 25M` are enough however many files a form has. Size `pm.max_children` to the server's RAM.

**Nginx (minimal):**

```nginx
server {
    listen 443 ssl http2;
    server_name portal.example.com;
    root /var/www/ivauto/public;
    index index.php;
    client_max_body_size 25M;

    location /build/ { expires 1y; add_header Cache-Control "public, immutable"; try_files $uri =404; }
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

**Queue worker (Supervisor):**

```ini
[program:ivauto-worker]
command=php /var/www/ivauto/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data
```

**Scheduler (cron, one server only).** This runs the nightly audit-log and failed-job pruning:

```cron
* * * * * cd /var/www/ivauto && php artisan schedule:run >> /dev/null 2>&1
```

`event:cache` in the deploy step matters: listeners are discovered from `app/Modules/*/Listeners`, and caching skips
that scan on every request.

**Monitoring.** Errors go to Sentry; set `SENTRY_RELEASE` to the deployed commit so errors link to the release. Watch response times, MySQL slow query log (`long_query_time=1`), Redis memory, the
`failed_jobs` table (`php artisan queue:failed`) and `storage/logs`. Before adding a new list or search screen, check
its query with `EXPLAIN` against the indexes above, and paginate it (`cursorPaginate` for large tables).
