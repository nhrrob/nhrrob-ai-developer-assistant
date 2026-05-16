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

`nhrrob-ai-developer-assistant.php` → `Nhrada_AI_Developer_Assistant::init()` (singleton) → `plugins_loaded` → `init_plugin()` → registers `Admin` (admin only) and `Api` (always), outputs custom JS in footer, and `require_once`s `wp-content/nhrada-snippets.php` if it exists.

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

The native WP client uses `using_model_preference(CLAUDE_MODEL, OPENAI_MODEL, GEMINI_MODEL)` — preferences only, never hardcode a required provider.

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

### Database Tables

Three tables created on activation (`Activator::activate()`):

- `{prefix}nhrada_changes` — change log (request, type, code, status `applied|undone`)
- `{prefix}nhrada_snapshots` — before/after values per change, keyed by `change_id`
- `{prefix}nhrada_messages` — chat history (role `user|assistant`, linked to `change_id`)

### Free Plugin

This is a free plugin with no usage limits, no licence keys, and no SaaS backend. `Licence.php` exists as an empty stub only. Do not add paid-tier gating, upgrade prompts, or external proxy calls — those belong in a separate Pro plugin.

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
