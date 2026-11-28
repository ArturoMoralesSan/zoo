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

PHP 8.4+, Laravel 13, Inertia 3, Vue 3 (`<script setup lang="ts">`), TypeScript,
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
php artisan species:images:report              # same for species images (optimize deletes originals)
php artisan cards:packs:build                  # encrypted offline-pack copies (idempotent; --force re-encrypts)
php artisan cards:export | cards:import x.zip --dry-run   # card+species .zip packages without the panel
```

**Baseline on 2026-11-26.** Do not chase the existing debt unless asked; just add none.

| Check | State |
| --- | --- |
| `php artisan test` | 529 tests, all passing (28 skipped) |
| `npx vitest run` | 156 passing |
| pint / eslint / prettier | 16 files / 193 errors / 127 files |
| phpstan (level 7) / vue-tsc | 483 errors, all in `phpstan-baseline.neon` / 37 errors |

**CI** (`.github/workflows/tests.yml`, PHP 8.4, on pull requests and pushes to `master`) runs the
frontend build, PHPStan with the baseline (only new errors fail), `php artisan test` and vitest.
On pull requests, `.github/scripts/check-changed-files.sh` runs pint, eslint, prettier and vue-tsc
on the changed files only, so **a file you touch must end up clean**, including its old debt. Run
`bash .github/scripts/check-changed-files.sh` before pushing (commit first: it diffs against
`origin/master`). When you fix PHPStan errors, regenerate the baseline with
`vendor/bin/phpstan analyse --memory-limit=2G --generate-baseline=phpstan-baseline.neon`; never add
new errors to it. `composer test` and `composer ci:check` still stop at the repo-wide lint step.

## Where things are

```
routes/web.php      Admin panel, all under auth + verified, prefix admin, names admin.*
routes/api.php      Mobile API, nearly all under auth:sanctum
routes/settings.php User settings (starter kit)
app/Http/Controllers/Admin/      Inertia controllers, one per resource
app/Http/Controllers/Api/        JSON controllers for the mobile app
app/Http/Requests/{Admin,Api}/   Form requests (Admin/<Resource>/{Store,Update}…Request)
app/Http/Resources/              API resources (CardResource, Duel/*, Credits/*)
app/Services/                    CardDuelService, PointService, Ai/GeminiClient, Points/*, GlbInspector, CardImageOptimizer, ImageEncoding/*, Licensing/*, CardPack/*, CardTransfer/*
app/Concerns/                    Validation rule traits shared by form requests
app/Enums/ app/Rules/ app/Observers/ app/Policies/ app/Exceptions/
config/glb.php config/licensing.php config/images.php config/cards.php config/points.php   Project-specific settings
database/seeders/                Roles and permissions, menu, catalogs
resources/js/pages/admin/<resource>/{Index,Create,Edit,Show}.vue
resources/js/components/admin/   Shared admin UI; admin/cards/ for the card editor
resources/js/components/ui/      shadcn-style primitives
resources/js/lib/                Card, GLB, credits and quiz logic (pure TS, tested)
resources/js/composables/  resources/js/types/  resources/css/
tests/Feature/{Admin,Api,Auth,Licensing,Settings}  tests/Unit  tests/js  tests/Fixtures
docs/api-credits.md  docs/glb-resizing.md  docs/image-optimization.md   Read only when working on those features
docs/offline-packs-{backend,app}.md   Offline card packs: server done, mobile app pending
docs/points-app.md        Points for the app: rules, levels, seasons (GET /api/points/season), icon/color catalog
docs/card-points-app.md   Card capture points (data.points on duel wins, incl. season); points done in the app
docs/purchase-points-app.md  Same data.points on ticket purchases and donations; app side pending
docs/card-transfer.md     Export/import all cards and species as a .zip (Recursos → Descargas)
docs/focal-points-app.md   Focal points for the app (species image_focal, focal_x/y; card thumbnail_focal); app side pending
```

Page folders match the route resource, except `quizquestions`, `card-qr-codes` and
`card-credits` (nested under `admin/cards/{card}/…`).

### Data model

- **Species:** `Species` → `SpeciesCategory`, `SpeciesImage`, `SpeciesModel`,
  `SpeciesLocation` (→ `ZooZone`), `SpeciesTag` (many-to-many).
- **Cards:** `Card` → `Species`, `CardRaritySetting`, `QuizQuestion` → `QuizAnswer`,
  `CardQrCode`, `QuizAttempt`, `Credit` (polymorphic, aliases `card` and `species_image`). Per user:
  `CardCapture` (owned), `CardQuizState` (retries and lockout).
  Singleton: `DuelMessageSetting::current()`. `Card.uuid` is the stable id across servers;
  `CardTransfer` tracks queued .zip exports/imports.
- **Credits:** `Credit` → `License`.
- **Map:** `ZooZone` → `MapMarker`, `SpeciesLocation`, `Event`; `MapPath`.
- **Tickets:** `TicketOrder` → `TicketOrderItem` (→ `TicketType`), `TicketOrderPayment`
  (→ `PaymentMethod`), `Ticket`.
- **Users:** `User` → `Profile`, `Level`, `PointMovement` (→ `PointMode`); `PointRule`;
  `PointMode` ↔ `PointRule` (pivot `point_mode_rules.points`); `Reward` →
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
  Name stored files with `Support/UploadExtension::for($file)` (extension guessed from the
  content), never `getClientOriginalExtension()`: a valid image named x.html or x.php would be
  served as a page or script. Every upload field needs a rule; a field with none is not checked.
  `storage/app/public/.htaccess` refuses PHP and HTML as a backstop (nginx needs its own rule).
- **Leaflet HTML:** `divIcon`/popup `html` is inserted raw, so escape every value with
  `escapeHtml()` and only allow `#hex` colors.
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
- **Visit validations:** with `APP_ZOODECK_ENFORCE_VALIDATIONS=1`, `POST /api/duels` and
  `/submit` require `latitude`/`longitude` inside an active zone and an active ticket from
  an order paid today (there is no visit date and no gate check-in, `used_at` is never set).
  Refusals are `DuelException` with `data.reason` (`location_required` 422, `outside_zoo`
  and `no_ticket` 403). `Services/ZooDeck/VisitValidator` is the only place these rules live;
  `validate-capture` and the ZooDeck map (`GET /api/map/species`, same zone shape as
  `GET /api/map` plus per-card `status`, `attempts`, `can_duel`, `reason`; it lists only
  cards with a usable QR and at least one active question) use it too.
  Keep the map's `reason` order equal to the order `POST /duels` refuses in. All of them
  take an optional `accuracy` (GPS metres): a point that close to a zone's edge counts as
  inside, capped by `APP_ZOODECK_LOCATION_TOLERANCE` (30 m).
- **Duel settings** come from the card or its rarity (`duel_settings_source`). Always
  read `effective_questions_per_duel`, `effective_retries` and
  `effective_cooldown_minutes` on `Card`.
- **AI question generation** uses Gemini (`QuizGeneratorService`, `GEMINI_API_KEY` in
  `.env`, settings in `config/services.php`). It never saves: an admin reviews the
  questions and they are stored through the JSON import endpoint. The manual route
  remains as the advanced option: `lib/quizImport.ts` builds a prompt for an external
  LLM, then the JSON is imported. Both this and species "Completar con IA"
  (`SpeciesInfoGeneratorService`, `POST admin/species/ai-fill`, `SpeciesAiFillDialog.vue`) call
  Gemini through `Services/Ai/GeminiClient`, with errors from `AiGenerationException` subclasses.
  Species fill only suggests into the form; category and tags are opt-in and picked only from
  active ones, never created.
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
- **Card and species packages** (`CardTransfer/*`, Recursos → Descargas, routes
  `admin.assets.transfers.*`, permissions `assets.export`/`assets.import`): queued jobs build or
  import a `.zip` (format v3, v1/v2 still read) with every card, its questions, QRs, credits, global
  duel settings and files, plus every species with category, tags and images. Needs PHP `zip`;
  without it every action returns 503. Species are imported first (matched by slug, then a unique
  scientific name) and created if missing; the preview lets the admin create or map missing
  categories/tags and map or drop unknown zones. Species locations travel only if the exporter
  ticks the box. Import never deletes questions and copies files as-is. QR tokens travel only if
  the exporter ticks the box. Adding a card or species column means adding it to
  `CardPackage::CARD_FIELDS`/`SPECIES_FIELDS` and the reader's rules. Since v3 each species image
  carries its `credits`. See `docs/card-transfer.md`.
- **Species catalogs:** `conservation_status` (IUCN code, plus `DOM` "Domesticado", which is not IUCN), `diet` and `habitats` (JSON, several)
  store enum codes, never free text. The API keeps the old Spanish strings in `habitat`, `diet`
  and `conservation_status` and adds `habitats`, `diet_code`, `conservation_status_code`. The
  species gallery is saved with the form (`SpeciesController::syncGallery`: order, alt text, focal
  points, deletes and re-cropped saved photos in one request; an item with both `id` and
  `file_index` replaces that photo's file and keeps its credits); `SpeciesGalleryField.vue` builds that payload.
- **Credits:** licences and attribution are legally relevant, and restricted licences
  are off by default. `CreditsCacheObserver` invalidates the public `/api/v1/credits`
  cache. Owners implement `Contracts/Creditable` through `Models/Concerns/HasCredits`: `Card`
  (assets `CreditAsset::forCards()`) and each `SpeciesImage` of type main/thumbnail/gallery (asset
  `image`, never `based_on_asset`; panel `admin/species/{species}/credits`). Never loop
  `CreditAsset::cases()` for card code, since `image` has no card column. The API sends card
  sections then species sections, told apart by `type`. Deleting a `SpeciesImage` deletes its
  credits (model hook, so use Eloquent deletes); replacing a file keeps them with a review toast.
  Missing credits never block activating a card or species: the panel warns instead
  (`MissingCreditsAlert` under the switch, a warning toast on save, red "Activa sin licencia" in
  the lists), with texts from `Card`/`Species::missingCreditsText()` and `lib/credits/missing.ts`.
  See `docs/api-credits.md`.
- **Species images** (main, thumbnail, gallery) follow the same WebP flow with their own profiles
  (`resources/data/species-image-profiles.json`, `SpeciesImageOptimizer`, `species:images:*`).
  See the "Especies" section of `docs/image-optimization.md`.
- **Crop and focal point:** every image picked in the panel goes through `ImageCropDialog`
  before the WebP step. Ratios come from `aspect` in the image profiles: card artwork 572:1016,
  both thumbnails 1:1 (locked, re-checked by `Concerns/ImageAspectRules` only on new uploads,
  not on .zip imports); species main and gallery are free and carry `focal_x`/`focal_y`
  (0–1, null = centre), applied like CSS `object-position`. The card thumbnail also has one
  (`thumbnail_focal_x/y`, API `thumbnail_focal`), because the app crops it from 0.7:1 to 2.3:1. Build the output size with
  `cropOutputSize()`: Laravel's ratio check fails at one pixel off on large images. See
  "Recorte y punto focal" in `docs/image-optimization.md`. Card artwork and species main also
  keep a ≤2400 px copy of the full photo plus the last crop box (`ImageCropSource`,
  `artwork_source`/`artwork_crop`, `species_images.source_path`/`crop`) so "Recortar imagen
  actual" starts from it; copies never travel in .zip packages.
- **Recursos page** (`admin/assets`, `AssetController`, `AssetInventory`): tab "Optimización"
  shows sizes, savings and pending conversions for card images, species images and card 3D
  models, plus both conversion panels (`assets.view`, `assets.optimize`); tab "Descargas" is the
  package export/import (`assets.export`, `assets.import`). All admin only. The conversion endpoints
  `cards.images.*` and `species.images.*` require `assets.optimize`.
- **Card images** are stored as WebP. The browser encodes them (`lib/imageOptimize.ts`,
  libwebp in WASM) and the server re-encodes non-WebP uploads only if it has Imagick or
  GD (`IMAGE_ENCODER`). `generated_image` is 2× (1144×2032), not the 3× download. Older
  images are converted from Recursos → Optimización or with `cards:images:optimize`, which
  **deletes each original**. See `docs/image-optimization.md`.
- **Security** (report of 2026-11-26): app users (only role `visitor`) never enter the panel:
  `Fortify::authenticateUsing` rejects them and the `panel` middleware (`EnsurePanelAccess`) logs
  them out of `admin/*` and settings. There is no web sign-up; the app registers through
  `/api/register` (`Password::defaults()`). Only a SuperAdmin can grant that role, edit its
  accounts or edit the role. `/api/login`, `/register`, ticket orders and donations are throttled,
  so set `TRUSTED_PROXIES` (`config/trustedproxy.php`) behind a proxy. API tokens expire after
  30 days (`SANCTUM_EXPIRATION`, pruned daily). There is no payment gateway yet: app ticket
  orders and donations are approved without charging only with `APP_PAYMENTS_SIMULATED`
  (default: on only with `APP_ENV=local`), otherwise they return 503. Rewards have
  `max_per_user` (null = unlimited), checked inside the redemption transaction.
- **Points:** `PointService::add()` applies a `PointRule` by type and throws if it is missing or
  inactive; `addIfRuleActive()` skips it instead. Levels never go down, and redeeming a reward
  does not deduct points. Points are awarded inside the action's own request and transaction and
  come back in its response as `data.points` (`PointsAwardResource`: `awarded`, `total`, `level`,
  `level_up`, `season`); there is no endpoint to add points from the app. Winning a duel awards
  the rule picked per rarity (`card_rarity_settings.point_rule_id`, null = no points; it starts as
  `card_capture_<rarity>`, see `CardRarity::pointRuleType()`) once per card. Paid ticket orders
  (app and box office, once per order) and completed donations (app and panel, once, only from
  `config('points.donation_min_amount')`, 50 MXN) go through `Points/PaymentPoints`, which is
  idempotent per reference (`PointService::awardOnce()`) so a payment webhook can call it again.
  See `docs/card-points-app.md` and `docs/purchase-points-app.md`.
  **A new action that awards points** gets a case in `Enums/PointAction` (its `type` and panel
  label) and is awarded with `addIfRuleActive()` (or `awardOnce()`) inside its transaction, never
  `add()`, so a disabled rule never blocks the action. `PointService::ruleUsage()` uses that enum
  plus the rarity settings to mark rules "Sin uso" in the panel, and `visibleSeason()` leaves
  unused rules out of `other`. With `APP_POINTS_RULES_LEGACY=1` (`config('points.rules_legacy')`,
  checked only through `PointService::tracksRuleUsage()`) the panel hides that usage info and
  `other` lists every changed rule again, as before; awarding does not change.
  **Point modes** (seasons/events, admin "Modos de puntos"): at most one `PointMode` is
  active and overrides some rules' points; rules it doesn't override keep their base points.
  `PointService::pointsFor()` resolves them, and every award goes through it. A mode can set a
  rule to 0, which awards nothing and never throws (it must not block a purchase).
  Movements store `point_mode_id`. A mode with `is_public` shows its `public_title`/`public_message`
  (never its internal name) through `GET /api/points/season` and the additive
  `data.points.season` on duel wins; hidden modes still change points but report `null`.
  `PointService::visibleSeason()` is the only place that decides this. `PointModeSeeder` holds six
  example seasons (all inactive, created only if missing by name). Seasons also carry
  `icon`/`color` keys from the shared catalog (`PointSeasonStyles`); null or unknown = app default.

**Logic duplicated across PHP and TS. Change both sides:**

| PHP | TS | Shared check |
| --- | --- | --- |
| `Licensing/AttributionBuilder` | `lib/credits/attribution.ts` | `tests/Fixtures/credits/attribution-cases.json` |
| `QuizQuestion::normalizeText` | `quizImport.ts` `normalizeQuestionText` | none |
| `QuizGeneratorService` prompt rules | `quizImport.ts` `buildQuizPrompt` | none |
| `Enums/CardRarity` | `lib/cardRarity.ts` | none |
| `Enums/{ConservationStatus,Diet,Habitat}` | `lib/speciesCatalogs.ts` | `SpeciesAdminTest` (catalog parity) |
| `GlbInspector`, `config/glb.php` | `lib/glb/resizeGlb.ts` | `tests/Fixtures/glb` |
| `ImageAspectRules` (`dimensions:ratio`) | `lib/imageCrop.ts` `matchesAspect`/`cropOutputSize` | none |

Card fonts are shared through `resources/data/card-fonts.json`, card and species image profiles
(size and quality) through `resources/data/{card,species}-image-profiles.json`, and season icons and
colors through `resources/data/point-season-styles.json` (keys never change; the app maps
them, see `docs/points-app.md`).

## Gotchas

- **Do not copy `Route::resource(...)->middleware(['index' => '…'])`.** The keys are
  ignored, so every action requires all the listed permissions. `SuperAdmin` and
  `admin` get through, but a role with only some of them gets a 403. Most older
  resources have this bug. Use `middlewareFor`.
- **Do not copy `->with('success', '…')`.** Older controllers use it, but no frontend
  code reads it, so the message never appears. Use the toast above.
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
