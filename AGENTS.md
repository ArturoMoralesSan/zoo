# ZooApp: agent guide

Backend, admin panel and JSON API for a zoo visitor platform: species, interactive
map, tickets, donations, points and rewards, and a collectible card game where
visitors scan a QR code and win a quiz "duel" to capture a card. The mobile app
that consumes `/api` (ViroReact, AR) is **not in this repo**.

This file replaces a codebase scan. Start here, then open only the files your task
names. If something here is wrong, fix this file in the same change.

## Hard rules

1. **Never run tests or destructive commands against a real database.** `.env`
   points to the dev MySQL database `zoo`, and a test run against it once erased
   all data with no recent backup to restore.
   - Run tests only as `php artisan test`, which uses SQLite `:memory:` from
     `phpunit.xml`. Never add `DB_CONNECTION=` or `DB_DATABASE=` overrides.
   - `phpunit.xml` does not force its `<env>` values, so exported `DB_*` shell
     variables win. Cached config also wins, so never run `config:cache` here.
   - Starter-kit tests use bare `RefreshDatabase` with no safety check. Every new
     database test must use `Tests\Concerns\RefreshesInMemoryDatabase`, which
     refuses anything but in-memory SQLite.
   - If the PHP SQLite driver is missing, stop and ask. Do not fall back to MySQL.
   - Never run `migrate:fresh`, `migrate:refresh`, `migrate:reset` or `db:wipe`.
   - Ask before `migrate` or `db:seed` on the dev database. Re-running
     `RolesAndPermissionsSeeder` overwrites role permissions edited in the panel.
2. **Never run repo-wide fixers** (`composer lint`, `npm run lint`,
   `npm run format`). The repo has large existing debt, so they create huge
   unrelated diffs. Fix only the files you touched.
3. **Commit or push only when asked.** Never read or print secrets from `.env`.
4. **Do not change the shape of existing `/api` responses without asking.** The
   mobile app depends on them. Adding fields is fine.

## Stack

PHP 8.3+, Laravel 13, Inertia 3, Vue 3 (`<script setup lang="ts">`), TypeScript,
Tailwind 4, reka-ui/shadcn-style components, Vite 8. Fortify (web auth, 2FA,
passkeys), Sanctum (API tokens), spatie/laravel-permission, Wayfinder (typed routes
for the frontend), Scramble (API docs at `/docs/api`), Leaflet (maps), Babylon.js and
gltf-transform (3D). Dev database is MySQL/MariaDB; tests use SQLite. Package manager
is npm. Timezone is `America/Mexico_City`.

## Commands

```bash
composer dev                                   # server, queue, logs and Vite together
php artisan storage:link                       # once, so uploads are served

# Use these targeted forms. Output is already compact JSON for agents.
php artisan test --filter=DuelTest
npx vitest run tests/js/quizImport.test.ts
vendor/bin/pint app/Path/File.php              # PHP format, touched files only
vendor/bin/phpstan analyse --memory-limit=2G app/Path/File.php
npx eslint --fix resources/js/path/File.vue
npx prettier --write resources/js/path/File.vue
npx vue-tsc --noEmit 2>&1 | grep 'pages/admin/foo'   # no per-file mode, so filter

php artisan wayfinder:generate --with-form     # after route changes, if Vite is not running
php artisan route:list --name=admin.events -v  # routes with their middleware
php artisan route:list --path=api --json
php artisan cards:images:report                # read-only: card images still to convert
php artisan cards:packs:build                  # encrypted offline-pack copies (idempotent; --force re-encrypts)
```

**Baseline on 2026-11-10.** Do not chase these failures unless asked; just add none.

| Check | State |
| --- | --- |
| `php artisan test` | 302 tests, 1 stale failure: `ExampleTest` expects 200 at `/` (checked 2026-11-12) |
| `npx vitest run` | 102 passing |
| pint / eslint / prettier | 16 files / 193 errors / 127 files |
| phpstan (level 7) / vue-tsc | 551 errors / 33 errors |

`composer test` and `composer ci:check` stop at the lint step because of this, so
use the targeted commands. CI runs on pull requests only: its push trigger says
`main`, but the default branch is `master`.

## Where things are

```
routes/web.php      Admin panel, all under auth + verified, prefix admin, names admin.*
routes/api.php      Mobile API, nearly all under auth:sanctum
routes/settings.php User settings (starter kit)
app/Http/Controllers/Admin/      Inertia controllers, one per resource
app/Http/Controllers/Api/        JSON controllers for the mobile app
app/Http/Requests/{Admin,Api}/   Form requests (Admin/<Resource>/{Store,Update}…Request)
app/Http/Resources/              API resources (CardResource, Duel/*, Credits/*)
app/Services/                    CardDuelService, PointService, GlbInspector, CardImageOptimizer, ImageEncoding/*, Licensing/*, CardPack/*
app/Concerns/                    Validation rule traits shared by form requests
app/Enums/ app/Rules/ app/Observers/ app/Policies/ app/Exceptions/
config/glb.php config/licensing.php config/images.php config/cards.php   Project-specific settings
database/seeders/                Roles and permissions, menu, catalogs
resources/js/pages/admin/<resource>/{Index,Create,Edit,Show}.vue
resources/js/components/admin/   Shared admin UI; admin/cards/ for the card editor
resources/js/components/ui/      shadcn-style primitives
resources/js/lib/                Card, GLB, credits and quiz logic (pure TS, tested)
resources/js/composables/  resources/js/types/  resources/css/
tests/Feature/{Admin,Api,Auth,Licensing,Settings}  tests/Unit  tests/js  tests/Fixtures
docs/api-credits.md  docs/glb-resizing.md  docs/image-optimization.md   Read only when working on those features
docs/offline-packs-{backend,app}.md   Offline card packs: server done, mobile app pending
```

Page folders match the route resource, except `quizquestions`, `card-qr-codes` and
`card-credits` (nested under `admin/cards/{card}/…`).

### Data model

- **Species:** `Species` → `SpeciesCategory`, `SpeciesImage`, `SpeciesModel`,
  `SpeciesLocation` (→ `ZooZone`), `SpeciesTag` (many-to-many).
- **Cards:** `Card` → `Species`, `CardRaritySetting`, `QuizQuestion` → `QuizAnswer`,
  `CardQrCode`, `QuizAttempt`, `Credit` (polymorphic, alias `card`). Per user:
  `CardCapture` (owned), `CardQuizState` (retries and lockout).
  Singleton: `DuelMessageSetting::current()`.
- **Credits:** `Credit` → `License`.
- **Map:** `ZooZone` → `MapMarker`, `SpeciesLocation`, `Event`; `MapPath`.
- **Tickets:** `TicketOrder` → `TicketOrderItem` (→ `TicketType`), `TicketOrderPayment`
  (→ `PaymentMethod`), `Ticket`.
- **Users:** `User` → `Profile`, `Level`, `PointMovement`; `PointRule`; `Reward` →
  `RewardRedemption`; `Donation`. Sidebar: `Menu` → `Link` → `Permission`.

A model's `$fillable` and `$casts` show its columns; there is no need to read its migrations.

## Recipes

### New admin CRUD, in this order

1. Migration. It must work on both MySQL and SQLite.
2. Model with `$fillable`, `$casts` and typed relations.
3. `Admin/<Resource>/{Store,Update}<Resource>Request`, with Spanish `attributes()`.
4. `Admin/<Resource>Controller`: `Inertia::render('admin/<resource>/Index', […])`,
   `->paginate(10)->withQueryString()`, and a `filters` prop.
5. Route inside the admin group of `routes/web.php`, using **`middlewareFor`** (see
   Gotchas). Literal paths must come before a resource with the same prefix.
6. Add `<resource>.view|create|edit|delete` to `RolesAndPermissionsSeeder`, and to
   the `staff` list if staff should have it. `admin` gets everything automatically.
7. Add the sidebar link with `MenuSeeder::link()`. Icons are Lucide names that must
   also be registered in the `icons` map in `components/AppSidebar.vue`, otherwise
   they fall back to `LayoutGrid`.
8. Generate Wayfinder routes, then build the pages.
9. Add a feature test: `actingAs` a user with the permission, on the in-memory trait.

**Copy from** `SpeciesTagController` with `pages/admin/species-tags/*` (smallest full
CRUD), or `CardQrCodeController` with `pages/admin/card-qr-codes/*` (newest patterns:
form request, toast, `middlewareFor`). Do not start from cards, species or map-paths.

### New API endpoint

1. Add to `routes/api.php` inside `auth:sanctum`, named `api.<area>.<action>`, with
   `throttle` on writes.
2. Controller in `Controllers/Api`, form request in `Requests/Api`, business logic in
   a service, output through a `JsonResource`.
3. Respond with `{ success: true, data }`. Errors are `{ success: false, message,
   data? }`. For domain errors, follow `DuelException`, which renders itself.
4. Write a Spanish docblock with `@tags`, because Scramble publishes it. `DuelController`
   is the reference. Build file URLs with `CardResource::publicUrl()`.
5. Test with `Sanctum::actingAs($user)`.

## Conventions

- **Language:** UI text, validation messages, comments and commit messages are in
  **Spanish**; identifiers are in English. Commits are short and imperative, for
  example `Añade…`, `Arregla…`, `Mejora…`. Work on a branch and merge to `master` by PR.
- **Comments** explain why, not what. Match the surrounding style.
- **Web routes** are named `admin.<resource>.<action>`. Always guard admin routes with
  `permission:` middleware; `SuperAdmin` bypasses every check through `Gate::before`.
- **Frontend URLs** always come from Wayfinder (`import admin from '@/routes/admin'`,
  then `admin.events.index().url`). Never hardcode them. `resources/js/{actions,routes,wayfinder}`
  are generated and gitignored.
- **Pages** set breadcrumbs with `defineOptions({ breadcrumbs: […] })`. Layouts are
  applied in `app.ts`. Forms use `useForm`, with `forceFormData: true` for uploads.
  Deletes confirm with `Swal` (sweetalert2).
- **Admin UI** reuses `PageHeader`, `DataTable`, `TableActions`, `AdminCard`,
  `AdminButton`, `CancelButton`, `ActiveSwitch` and the `form-*` classes in
  `resources/css/forms.css`. Use `cn()` from `lib/utils`.
- **Theme** is light only. Palette: primary `#187744`, text `#244334`, muted `#718477`,
  page `#F2F7F4`, border `#c8dccf`.
- **Icons:** use `lucide-vue-next`, the most common library here.
- **Toasts:** use `Inertia::flash('toast', ['type' => 'success', 'message' => '…'])`.
- **Uploads:** public files go to `storage/app/public/<dir>` and the database stores the
  relative path. Remove them with `Storage::disk('public')->delete()`. Private
  files use the `local` disk.
- **Concurrency:** wrap multi-step writes in `DB::transaction` and use `lockForUpdate`
  for counters and limits. See `CardDuelService::lockState`.

## Domain rules

- **Duels** (`CardDuelService`) are fully server-authoritative. A duel starts only from
  a QR token, never from a card id sent by the app. The correct answer is never sent
  to the client. A card is captured only if every answer is right. Duels have no time
  limit. Using up the retries locks the card for a cooldown (HTTP 423).
- **ZooDeck dev tools:** the "Tarjetas obtenidas" and "Intentos y bloqueos" tabs on
  the cards index, and their revoke and reset endpoints, exist only when
  `APP_ZOODECK_DEV=1` (`config('app.zoodeck_dev')`). Otherwise the endpoints return 404.
- **Duel settings** come from the card or its rarity (`duel_settings_source`). Always
  read `effective_questions_per_duel`, `effective_retries` and
  `effective_cooldown_minutes` on `Card`.
- **AI question generation** uses Gemini (`QuizGeneratorService`, `GEMINI_API_KEY` in
  `.env`, settings in `config/services.php`). It never saves: an admin reviews the
  questions and they are stored through the JSON import endpoint. The manual route
  remains as the advanced option: `lib/quizImport.ts` builds a prompt for an external
  LLM, then the JSON is imported.
- **3D models:** the browser resizes the GLB and the server re-verifies it, and 1 unit
  equals 1 metre. Never rescale an already scaled file. On every resize the browser also
  shrinks textures to 1024 px JPEG q85 (`lib/glb/glbTextures.ts`, `config('glb.textures')`),
  because Viro reads only core glTF JPEG/PNG: no KTX2, WebP or mesh compression. See
  `docs/glb-resizing.md`.
- **Offline packs** (`CardPack/*`, `GET /api/cards/pack`): `CardPackObserver` encrypts
  each card file (AES-256-GCM, key derived from its path) into `storage/app/public/packs/`
  right after commit. Keys go out in `pack` only where the plain URL already does
  (owned cards, duel start). `phpunit.xml` sets `CARD_PACKS_ENABLED=false`, so tests
  never write to real `storage/`; enable it only together with `Storage::fake('public')`.
  See `docs/offline-packs-backend.md`.
- **Credits:** licences and attribution are legally relevant, and restricted licences
  are off by default. `CreditsCacheObserver` invalidates the public `/api/v1/credits`
  cache. See `docs/api-credits.md`.
- **Card images** are stored as WebP. The browser encodes them (`lib/imageOptimize.ts`,
  libwebp in WASM) and the server re-encodes non-WebP uploads only if it has Imagick or
  GD (`IMAGE_ENCODER`). `generated_image` is 2× (1144×2032), not the 3× download. Older
  images are converted from the "Configuración" tab or with `cards:images:optimize`, which
  **deletes each original**. See `docs/image-optimization.md`.
- **Points:** `PointService::add()` applies a `PointRule` by type. Levels never go down.
  Only the ticket flows call it today.

**Logic duplicated across PHP and TS. Change both sides:**

| PHP | TS | Shared check |
| --- | --- | --- |
| `Licensing/AttributionBuilder` | `lib/credits/attribution.ts` | `tests/Fixtures/credits/attribution-cases.json` |
| `QuizQuestion::normalizeText` | `quizImport.ts` `normalizeQuestionText` | none |
| `QuizGeneratorService` prompt rules | `quizImport.ts` `buildQuizPrompt` | none |
| `Enums/CardRarity` | `lib/cardRarity.ts` | none |
| `GlbInspector`, `config/glb.php` | `lib/glb/resizeGlb.ts` | `tests/Fixtures/glb` |

Card fonts are shared through `resources/data/card-fonts.json`, and card image profiles
(size and quality) through `resources/data/card-image-profiles.json`.

## Gotchas

- **Do not copy `Route::resource(...)->middleware(['index' => '…'])`.** The keys are
  ignored, so every action requires all the listed permissions. `SuperAdmin` and
  `admin` get through, but a role with only some of them gets a 403. Most older
  resources have this bug. Use `middlewareFor`.
- **Do not copy `->with('success', '…')`.** Older controllers use it, but no frontend
  code reads it, so the message never appears. Use the toast above.
- `DELETE admin/species/{species}/images/{image}` has no permission middleware.
- `Welcome.vue` and `routes/settings.php` come from the starter kit; login lands on
  `/admin/dashboard` (`admin.dashboard`). There is no `dashboard` route.
- `card.html` and `Card.glb` in the root are design references, not app code.
- `composer.json` still names the project `laravel/vue-starter-kit`; ignore that.

## Saving tokens

**Finding code**

- Trust this file before exploring. Go straight to the file for the feature's name.
- Search with `grep -n` first, then read only the matching range with offset and
  limit. Never open a whole file over 300 lines just to find something.
- Use `route:list --name=…` instead of reading `routes/web.php` (457 lines).
- To learn a page's props, grep the controller for `Inertia::render` and read that
  call, or read only the `defineProps` block of the `.vue` file.
- In a controller, list its methods with `grep -n 'function '` first.

**Never open:** `composer.lock`, `package-lock.json`, `vendor/`, `node_modules/`,
`public/build/`, `storage/`, `resources/js/{actions,routes,wayfinder}/` (generated),
`tests/Fixtures/glb/`, `*.glb`, `card.html`, `.phpunit.result.cache`, or
`database/migrations/` as a whole (63 files; open one by name).

**Large files, always read in ranges:** `Admin/SpeciesController` (1023),
`Admin/MapPathController` (1002), `pages/admin/cards/Edit.vue` (985),
`Admin/CardController` (973), `Api/MapController` (854), `pages/admin/cards/{Index,Show,Create}.vue`
(about 800 each), `Admin/TicketOrderController` (679), `Services/GlbInspector` (544),
`Services/CardDuelService` (511), and `pages/admin/map-paths/{Create,Edit,Show}.vue`
(26–33 KB each).

**Writing code**

- Edit in place with small targeted replacements. Never rewrite a whole file.
- Copy the smallest template from Recipes rather than reading several examples.
- Reuse existing components, traits, services and `lib/` helpers. Search before adding one.
- Do only what was asked: no drive-by refactors, reformatting, extra docblocks or
  unrequested abstractions.
- Do not re-read a file after editing it. Plan related edits together.

**Running checks**

- Run the narrowest check first: one test filter, one file. Run the full test suite
  once, at the end, only for changes that cut across features.
- Cap long output with `| tail -n 40` or `grep`. Fix the first real error and re-run,
  rather than reading every cascading one.
- Compare failures with the baseline above before investigating them.

**Working style**

- Give broad searches to a subagent only when a few `grep`s will not do. Do not
  delegate what one search can answer.
- Keep messages short: no restating the task or narrating each step. Do not paste
  back large code blocks already in a file.
- If the task is ambiguous, ask one specific question instead of exploring every option.

## Keeping this file useful

Update it in the same change when you add a domain, command or convention, or when you
find an entry is wrong. Remove a Gotcha once it is fixed. Keep it short, because every
agent pays to load it on every task.
