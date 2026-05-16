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

`nhrrob-ai-developer-assistant.php` → `Nhrada_AI_Developer_Assistant::init()` (singleton) → `plugins_loaded` → `init_plugin()` → instantiates `Assets` (always), `Admin` (admin only), `Api` (always), then `require_once`s `wp-content/nhrada-snippets.php` if it exists. `Assets` registers admin scripts/styles on `admin_enqueue_scripts` and outputs frontend custom JS via `wp_footer`; actual enqueuing of the admin React app happens in `Admin` via `admin_head-{hook}`.

### Request Flow (the core loop)

```
User message (React UI)
  → POST /wp-json/nhrada/v1/chat
  → Api::handle_chat()
  → AiClient::send_request()       ← picks AI provider
  → Executor::apply_change()        ← Safety check, then writes change
  → Changelog::log_change() + create_snapshot()
  → response back to UI
```

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

| `change_type` | Storage mechanism | Undo mechanism |
|---|---|---|
| `css` | `wp_update_custom_css_post()` | Snapshot stores full CSS; restored verbatim |
| `js` | `nhrada_custom_js` WP option; output in footer | Option snapshot |
| `php` | Appended to `wp-content/nhrada-snippets.php` with `[NHRAA-SNIPPET-{id}]` block markers | Block removed by regex |
| `option` | `update_option($file_target, $code)` | Option snapshot |

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

React SPA built with `@wordpress/scripts`. Entry: `admin/src/index.js`, output: `admin/build/`. Enqueued only on the `toplevel_page_nhrada-settings` admin screen. Communicates exclusively via the `nhrada/v1` REST namespace.

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
