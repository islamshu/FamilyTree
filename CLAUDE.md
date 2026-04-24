# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development
composer run dev          # Start Laravel + Queue + Vite concurrently
composer run dev:ssr      # Same with SSR enabled

# Testing
php artisan test --compact                          # Run all tests
php artisan test --compact --filter=TestName        # Run a single test by name
php artisan test tests/Feature/FamilyMemberApiTest.php  # Run a specific file
composer run test         # Lint check + full test suite

# Linting & Formatting
composer run lint         # Fix PHP style with Pint
composer run lint:check   # Check PHP style without fixing
npm run lint              # Fix JS/TS with ESLint
npm run format            # Format with Prettier
npm run types:check       # TypeScript type-check

# CI
composer run ci:check     # Full check: npm lint/format/types + PHP tests

# Initial setup (first time only)
composer run setup
```

After modifying any PHP files, run `vendor/bin/pint --dirty --format agent` to fix formatting.

## Architecture

This is a **Laravel 12 + Vue 3 + Inertia.js** family tree management app. It is a single-page app with one route (`/`) rendered by `FamilyTree.vue`, with backend API endpoints for data operations.

### Data Model

`FamilyMember` is a self-referential Eloquent model:
- `parent_id` → `belongsTo(FamilyMember)` / `hasMany(FamilyMember, 'parent_id')` children
- Fields: `name`, `gender` (enum: male/female), `birth_date`, `death_date`, `wife_name`, `description`
- Uses soft deletes; dates cast via `casts()` method
- Custom accessors: `getArabicGenderAttribute()`, `getAgeAttribute()`

### Backend

- **Routes:** `routes/api.php` exposes `/api/family-members` (CRUD) and `/api/family-tree` (tree structure). `routes/web.php` has a single Inertia route for `/`.
- **Controller:** `FamilyMemberController` — handles all CRUD and recursive tree-building logic.
- **Middleware/Bootstrap:** Registered in `bootstrap/app.php` (Laravel 12 — no `Kernel.php`).
- **Config:** Use `config('key')` not `env()` directly in application code.

### Frontend

- **Entry:** `resources/js/app.ts` (client), `resources/js/ssr.ts` (SSR)
- **Pages:** `resources/js/pages/` — Inertia page components
- **Main page:** `resources/js/pages/FamilyTree.vue` — renders the tree using ECharts, handles all CRUD UI
- **Path alias:** `@/*` → `resources/js/*`
- **Routing from TS:** Use Wayfinder — import from `@/actions/` (controllers) or `@/routes/` (named routes), never hardcode URLs

### Key Conventions

- **PHP:** PHP 8 constructor property promotion; explicit return types on all methods; PHPDoc blocks over inline comments; curly braces always; Enum keys TitleCase.
- **Validation:** Always create Form Request classes — never inline validation in controllers.
- **DB queries:** Prefer `Model::query()` over `DB::`; use eager loading to avoid N+1.
- **New models:** Create a factory and seeder alongside the model.
- **Artisan:** Use `php artisan make:` commands to generate files; always pass `--no-interaction`.
- **Tests:** Pest v4, mostly feature tests; use model factories; `RefreshDatabase` trait; SQLite in-memory for tests (configured in `phpunit.xml`).

### MCP Tools (Laravel Boost)

When available, use these instead of guessing:
- `search-docs` — version-aware Laravel/Inertia/Pest docs (use before making changes)
- `tinker` — run PHP/Eloquent directly for debugging
- `database-query` / `database-schema` — inspect DB without writing code
- `browser-logs` — read frontend errors
- `list-artisan-commands` — verify Artisan command options
- `get-absolute-url` — get the correct local URL before sharing with user

### SSR

SSR is supported and runs on port 13714 (configured in `config/inertia.php`). If the frontend doesn't reflect changes, the user may need to run `npm run build` or `composer run dev`.
