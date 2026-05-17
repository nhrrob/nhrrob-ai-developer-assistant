# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build Commands

```bash
npm run build   # production build → admin/build/
npm run start   # development watch mode
```

PHP has no build step. Composer autoload is pre-generated; run `composer dump-autoload` only when adding new classes to `includes/`.

## Architecture

### Boot Flow

`nhrrob-ai-developer-assistant.php` → `Nhrada_AI_Developer_Assistant::init()` (singleton) → `plugins_loaded` → `init_plugin()` → instantiates `Assets` (always), `Admin` (admin only), `Api` (always), then `require_once`s `wp-content/uploads/nhrada-ai-developer-assistant/snippets-cache.php` if it exists. `Assets` registers admin scripts/styles on `admin_enqueue_scripts` and outputs frontend custom JS via `wp_footer`; actual enqueuing of the admin React app happens in `Admin` via `admin_head-{hook}`.

### Request Flow (the core loop)

```
User message (React UI)
  → POST /wp-json/nhrada/v1/chat
  → Api::handle_chat()
  → AiClient::send_request()       ← picks AI provider
      → PromptBuilder::build()     ← assembles system prompt + custom instructions
      → post_to_api()              ← shared HTTP POST + error handling
  → Executor::apply_change()        ← Safety check, then writes change
  → Changelog::log_change() + create_snapshot()
  → response back to UI

GET /wp-json/nhrada/v1/models
  → Api::get_models()
  → ModelFetcher::fetch()          ← transient cache → provider API → static fallback
  → response back to UI
```

### Class responsibilities in `includes/`

| Class | File | Job |
|---|---|---|
| `AiClient` | `AiClient.php` | Route to WP native or BYOK provider; `post_to_api()` shared HTTP helper; `parse_text_response()` |
| `PromptBuilder` | `PromptBuilder.php` | Assemble the system prompt from site context + custom instructions |
| `ModelFetcher` | `ModelFetcher.php` | Fetch available model list per provider with 24h transient cache and static fallback |
| `Executor` | `Executor.php` | Apply CSS / JS / PHP / option changes |
| `Undo` | `Undo.php` | Revert changes |
| `Safety` | `Safety.php` | Validate code before execution |
| `Context` | `Context.php` | Collect site context (WP version, theme, plugins, error log) |
| `Changelog` | `Database/Changelog.php` | DB read/write for the change log |
| `Activator` | `Activator.php` | Create `nhrada_log` table on activation |
| `Assets` | `Assets.php` | Register admin scripts/styles; output frontend custom JS |

### AI Provider Priority (AiClient.php)

1. **WP 7.0 native** (`wp_supports_ai()` + `is_supported_for_text_generation()`) — no API key needed
2. **BYOK** — user-supplied key for the selected provider (`nhrada_ai_provider`: `claude`, `openai`, `gemini`)
3. **Error** — clear message asking the user to configure a provider

The native WP client uses `using_model_preference()` with the resolved model IDs — preferences only, WP routes to whatever the host has configured.

### Model Selection

Each provider has a hardcoded default (class constants) and a user-overridable WP option:

| Provider | Default constant | Option key |
|---|---|---|
| Claude | `claude-sonnet-4-6` | `nhrada_claude_model` |
| OpenAI | `gpt-4o-mini` | `nhrada_openai_model` |
| Gemini | `gemini-2.0-flash` | `nhrada_gemini_model` |

`get_model($provider)` reads the option; falls back to the constant if blank.

`fetch_models($provider, $bust)` fetches the live model list from the provider's API using the stored key, caches the result in a WP transient (`nhrada_models_{provider}`, 24h TTL), and falls back to a built-in static list if no key is saved or the fetch fails. The transient is deleted automatically when a new API key is saved. The Settings UI shows a `<select>` populated from `GET /nhrada/v1/models?provider=…` with a Refresh button (`?refresh=1`) to bypass the cache.

Static fallbacks (shown when no key is saved): Claude Opus 4.7 / Sonnet 4.7 / Sonnet 4.6 / Haiku 4.5 · GPT-4o / 4o-mini / o1 / o1-mini · Gemini 2.5 Pro / 2.0 Flash / 1.5 Pro / 1.5 Flash.

### System Prompt

The system prompt lives in `AiClient::build_system_prompt()` — not in an external file. It has PHP variable interpolation tied to `Context.php` output (`$context['wp_version']` etc.), so externalising it would require a placeholder/replacement layer with no real benefit.

The prompt structure (and why the order matters):

1. **Role definition** — who the AI is
2. **Site context** — auto-detected (WP version, PHP, theme, plugins, errors, date) + `nhrada_custom_instructions` injected here as "Site admin notes"
3. **Response format** — the JSON contract (immutable)
4. **Coding standards** — immutable
5. **Safety rules** — immutable, always last (last position = strongest influence on model behaviour)

Custom instructions go in position 2 so they inform the AI about the site *before* it decides what to output. Safety rules at position 5 cannot be overridden by user text. Even if a user writes adversarial instructions, `parse_text_response()` expects valid JSON — deviation fails gracefully.

### Custom Instructions

`nhrada_custom_instructions` WP option — site admin can add context the AI wouldn't otherwise know: site purpose, preferred plugins, language, design constraints, etc. Stored via `sanitize_textarea_field()` + 2000-char hard limit (enforced in both `save_settings()` and the textarea `maxLength`). Shown as a textarea in Settings > Customization.

### AI Response Contract

Every AI call returns a parsed JSON object. The plugin relies on these exact fields:

| Field | Type | Notes |
|---|---|---|
| `can_do` | bool | false = plugin skips execution |
| `change_type` | `css\|js\|php\|option\|none` | routes to the correct executor |
| `file_target` | string | `custom-css`, `custom-js`, `functions-snippet`, or an option name |
| `code` | string | ready-to-execute code |
| `description` | string | stored in changelog |
| `confirmation_message` | string | shown to user |
| `cannot_reason` | string | shown when `can_do` is false |
| `warnings` | string | optional notice |

`parse_text_response()` strips markdown fences and extracts the first JSON object from the raw AI text before decoding.

### How Changes Are Applied and Undone

**Executor** writes the change, **Changelog** records it, **Undo** reverts it. Before writing, `Safety::validate_code()` runs a pattern blacklist on PHP snippets (exec, eval, DROP TABLE, etc.) and enforces a 5000-char limit.

### Managed Snippets File

The DB is the source of truth for PHP snippets. `wp-content/uploads/nhrada-ai-developer-assistant/snippets-cache.php` is a **compiled cache** rebuilt from the DB by `Executor::rebuild_snippets_cache()` after every apply or undo. It is loaded by `load_php_snippets()` on every request. If no snippets are active the file is deleted and `require_once` skips it cleanly.

`NHRADA_SNIPPETS_DIR` and `NHRADA_SNIPPETS_FILE` constants are defined using `wp_upload_dir()['basedir']` so they respect custom upload path configuration.

**Why `wp-content/uploads/`?** WordPress guarantees this directory is writable and already ships a `.htaccess` blocking direct PHP execution via HTTP. Using `eval()` (the only alternative to a file) is banned by the WP.org plugin review team.

**Why not a mu-plugin or separate WP plugin?** A mu-plugin would keep running after the main plugin is deactivated/uninstalled — orphaned code that's hard to debug. A separate plugin doubles the user-facing plugin entries. The current model (owned file loaded by main plugin) means snippets activate/deactivate cleanly with the main plugin, and `uninstall.php` removes the whole directory.

The cache directory is created lazily on first PHP write by `Executor::ensure_cache_dir()`, which also adds:

- `index.php` — `<?php # Silence is golden.` (prevents directory listing)
- `.htaccess` — `Deny from all` for `*.php` (belt-and-suspenders for Apache; nginx admins must add server-level rules)

| `change_type` | Storage mechanism | `snapshot_type` | Undo mechanism |
|---|---|---|---|
| `css` | `wp_update_custom_css_post()` (a WP post, not an option) | `css` | Full CSS restored via `wp_update_custom_css_post()` |
| `js` | `nhrada_custom_js` WP option; output in footer | `option` | Option snapshot |
| `php` | DB (`code` column); cache compiled to `uploads/nhrada-ai-developer-assistant/snippets-cache.php` | `snippets` | Mark row `undone` in DB → rebuild cache (no file parsing needed) |
| `option` | `update_option($file_target, $code)` | `option` | Option snapshot |

Snapshot types are first-class: `option` means "a real WP option named in `target_key`", `css` means "the WP custom CSS post" (no `target_key` needed — it's a singleton), `snippets` means "rebuild cache from DB" (no `original_value`/`new_value` needed — the `code` column is the source).

### Database Table

One table created on activation (`Activator::activate()`): `{prefix}nhrada_log`

Rows are discriminated by `record_type`:

| `record_type` | Populated columns | Notes |
|---|---|---|
| `change` | request, description, change_type, file_target, code, status, snapshot_type, target_key, original_value, new_value, created_at | Snapshot data is stored inline (1:1); `create_snapshot()` does an UPDATE on the same row |
| `message` | role, content, change_id (nullable), created_at | `change_id` links to a `change` row in the same table |

Status values for change rows: `applied`, `undone`.

### Free Plugin

This is a free plugin with no usage limits, no licence keys, and no SaaS backend. Do not add paid-tier gating, upgrade prompts, or external proxy calls — those belong in a separate Pro plugin.

### Frontend

React SPA built with `@wordpress/scripts`. Entry: `admin/src/index.js`, output: `admin/build/`. Enqueued only on the `toplevel_page_nhrada-assistant` admin screen (the hook returned by `add_menu_page()` with slug `nhrada-assistant`). Communicates exclusively via the `nhrada/v1` REST namespace.

## Key Conventions

- Main class: `Nhrada_AI_Developer_Assistant` (singleton in main plugin file)
- Namespace: `Nhrada\AIDeveloperAssistant` (PSR-4 from `includes/`)
- Constant prefix: `NHRADA_`
- Option prefix: `nhrada_`
- DB table prefix: `nhrada_` (after `$wpdb->prefix`)
- All REST routes require `manage_options` capability
- Debug logging gated behind `nhrada_debug_mode` option; use `maybe_debug_log()` in AiClient

## Release Exclusions

`CLAUDE.md`, `.ai/`, and `wp-ai-developer-assistant-prd.md` are excluded from both the WordPress.org distribution (`.distignore`) and `git archive` exports (`.gitattributes` `export-ignore`). Any new dev-only file (AI docs, local scripts, PRDs) must be added to **both** files to keep them in sync.

## Skills

- `/release_plugin` — step-by-step release procedure (branch sync, version bump, PR, tag, publish)
