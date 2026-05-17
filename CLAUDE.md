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

`nhrrob-ai-developer-assistant.php` → `Nhrada_AI_Developer_Assistant::init()` (singleton) → `plugins_loaded` → `init_plugin()`:

1. `new Assets()` — always; registers scripts/styles on `admin_enqueue_scripts`, hooks `wp_footer` for custom JS output
2. `new Admin(); $admin->init()` — admin only; adds menu page, registers nginx notice, adds plugin action links
3. `new Api(); $api->init()` — always; registers all REST routes
4. `load_php_snippets()` — `require_once` the snippets cache if it exists

Asset enqueuing: `Admin::maybe_enqueue_assets($hook)` fires on `admin_enqueue_scripts` and calls `wp_enqueue_script/style` only when `$hook === $this->hook` (the value returned by `add_menu_page()`). The menu slug is `nhrada-assistant`, so the hook is `toplevel_page_nhrada-assistant`.

### Request Flow (the core loop)

```
User message (React UI)
  → POST /wp-json/nhrada/v1/chat
  → Api::handle_chat()
      → log user message to DB
      → fetch last 10 messages from DB (conversation history)
      → Context::get_context()          ← site info for the prompt
      → AiClient::send_request()        ← picks AI provider
          → PromptBuilder::build()      ← system prompt + context + custom instructions
          → post_to_api()               ← shared HTTP POST helper
      → Executor::apply_change()        ← Safety check, then writes change
          → Changelog::log_change()     ← INSERT change row
          → Changelog::create_snapshot() ← UPDATE same row with snapshot data
      → log assistant reply to DB
  → response back to UI

GET /wp-json/nhrada/v1/models?provider=claude|openai|gemini[&refresh=1]
  → Api::get_models()
  → ModelFetcher::fetch()   ← transient cache → provider API → static fallback
  → response back to UI
```

### Class responsibilities in `includes/`

| Class | File | Job |
|---|---|---|
| `AiClient` | `AiClient.php` | Route to WP native or BYOK provider; `post_to_api()` shared HTTP helper; `parse_text_response()` |
| `PromptBuilder` | `PromptBuilder.php` | Assemble the system prompt from site context + custom instructions |
| `ModelFetcher` | `ModelFetcher.php` | Fetch available model list per provider with 24h transient cache and static fallback |
| `Executor` | `Executor.php` | Apply CSS / JS / PHP / option changes; rebuild snippets cache |
| `Undo` | `Undo.php` | Revert changes by snapshot type |
| `Safety` | `Safety.php` | Validate PHP code before execution (blacklist + length limit) |
| `Context` | `Context.php` | Collect site context sent to AI (WP/PHP version, theme, plugins, errors, customizer) |
| `Changelog` | `Database/Changelog.php` | DB read/write for the change log |
| `Activator` | `Activator.php` | Create `nhrada_log` table; migrate flat options → `nhrada_settings` array |
| `Assets` | `Assets.php` | Register admin scripts/styles; output frontend custom JS via `wp_footer` |

### AI Provider Priority (AiClient.php)

1. **WP 7.0 native** (`wp_supports_ai()` + `is_supported_for_text_generation()`) — no API key needed
2. **BYOK** — user-supplied key for the selected provider (`nhrada_settings['ai_provider']`: `claude`, `openai`, `gemini`)
3. **Error** — clear message asking the user to configure a provider

The native WP client uses `using_model_preference()` with the resolved model IDs — preferences only, WP routes to whatever the host has configured.

### Model Selection

Each provider has a hardcoded default (class constant in `AiClient`) and a user-overridable setting:

| Provider | Default | Setting key |
|---|---|---|
| Claude | `claude-sonnet-4-6` | `nhrada_settings['claude_model']` |
| OpenAI | `gpt-4o-mini` | `nhrada_settings['openai_model']` |
| Gemini | `gemini-2.0-flash` | `nhrada_settings['gemini_model']` |

`ModelFetcher::fetch($provider, $bust)` fetches the live model list from the provider's API using the stored key, caches in a WP transient (`nhrada_models_{provider}`, 24h TTL), and falls back to a built-in static list if no key is saved or the fetch fails. The transient is deleted automatically when a new API key is saved.

### System Prompt

The system prompt lives in `PromptBuilder::build($context)`. It takes the site context array from `Context::get_context()` and the `nhrada_settings['custom_instructions']` option, and returns a single string passed as the system message to the AI.

Prompt structure (order matters for model behaviour):

1. **Role definition** — who the AI is
2. **Site context** — WP version, PHP, theme, plugins, errors, date + `custom_instructions` as "Site admin notes"
3. **Response format** — the JSON contract (immutable)
4. **Coding standards** — immutable
5. **Safety rules** — immutable, always last (last position = strongest influence on model behaviour)

Custom instructions go in position 2 so they inform the AI about the site before it decides what to output. Safety rules at position 5 cannot be overridden by user text. Even if a user writes adversarial instructions, `parse_text_response()` expects valid JSON — deviation fails gracefully.

### Conversation History

Every chat turn saves both the user message and assistant reply to `nhrada_log` (record_type = `message`). When building the next request, `Api::handle_chat()` fetches the last 11 rows (DESC), reverses to chronological, then pops the user message just inserted (sent separately), yielding up to 10 prior turns as conversation history. This history is passed through to all three BYOK providers and to the WP native client via `with_history()`.

### Custom Instructions

`nhrada_settings['custom_instructions']` — site admin can add context the AI wouldn't otherwise know: site purpose, preferred plugins, language, design constraints, etc. Sanitized via `sanitize_textarea_field()` + 2000-char hard limit enforced in both `Api::save_settings()` and the textarea `maxLength`. Shown as a textarea in Settings > Customization.

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

**Executor** writes the change, **Changelog** records it, **Undo** reverts it. Before writing, `Safety::validate_code()` runs a pattern blacklist on PHP snippets (exec, eval, shell_exec, DROP TABLE, etc.) and enforces a 5000-char limit.

| `change_type` | Storage | `snapshot_type` | Undo mechanism |
|---|---|---|---|
| `css` | `wp_update_custom_css_post()` | `css` | Full CSS restored via `wp_update_custom_css_post()` |
| `js` | `nhrada_custom_js` WP option; output in footer | `option` | Option snapshot |
| `php` | DB (`code` column); cache compiled to snippets file | `snippets` | Mark row `undone` in DB → rebuild cache |
| `option` | `update_option($file_target, $code)` | `option` | Option snapshot |

Snapshot types are first-class: `option` stores `original_value`/`new_value` in the same log row; `css` is a singleton (no `target_key`); `snippets` rebuilds from DB (no value columns needed).

### Managed Snippets File

The DB is the source of truth for PHP snippets. `wp-content/uploads/nhrada-ai-developer-assistant/snippets-cache.php` is a **compiled cache** rebuilt from the DB by `Executor::rebuild_snippets_cache()` after every apply or undo. It is loaded by `load_php_snippets()` on every request. If no snippets are active the file is deleted and `require_once` skips it cleanly.

`NHRADA_SNIPPETS_DIR` and `NHRADA_SNIPPETS_FILE` constants are defined using `wp_upload_dir()['basedir']` so they respect custom upload path configuration.

**Why `wp-content/uploads/`?** WordPress guarantees this directory is writable and already ships a `.htaccess` blocking direct PHP execution via HTTP. Using `eval()` (the only alternative to a file) is banned by the WP.org plugin review team.

**Why not a mu-plugin?** A mu-plugin keeps running after the main plugin is deactivated — orphaned code. The current model means snippets activate/deactivate/uninstall cleanly with the main plugin.

The cache directory is created lazily on first PHP write by `Executor::ensure_cache_dir()`, which also creates `index.php` (silence) and `.htaccess` (`Deny from all` for `*.php`). On nginx, `Admin::maybe_show_nginx_notice()` detects the server and shows a dismissible admin notice with the required `location` block — dismissed state stored in `nhrada_nginx_notice_dismissed` option.

### Database Table

One table created on activation: `{prefix}nhrada_log`

Rows are discriminated by `record_type`:

| `record_type` | Populated columns | Notes |
|---|---|---|
| `change` | request, description, change_type, file_target, code, status, snapshot_type, target_key, original_value, new_value, created_at | `create_snapshot()` UPDATEs the same row — no separate snapshot table |
| `message` | role, content, change_id (nullable FK), created_at | `change_id` links to a `change` row in the same table |

Status values for `change` rows: `applied`, `undone`.

### Settings Storage

All settings are stored in a single `nhrada_settings` WP option (array). Keys inside the array: `ai_provider`, `claude_api_key`, `openai_api_key`, `gemini_api_key`, `claude_model`, `openai_model`, `gemini_model`, `custom_instructions`, `debug_mode`.

`Activator::maybe_migrate_settings()` runs on activation and migrates any flat v1.0.x options (`nhrada_ai_provider`, `nhrada_claude_api_key`, etc.) into the array, then deletes the old keys.

### Uninstall Cleanup

`uninstall.php` runs on plugin deletion (not deactivation):
- Drops `{prefix}nhrada_log`
- Deletes the entire snippets cache directory from uploads
- Deletes `nhrada_settings`, `nhrada_custom_js`, `nhrada_nginx_notice_dismissed` options

### Free Plugin

This is a free plugin with no usage limits, no licence keys, and no SaaS backend. Do not add paid-tier gating, upgrade prompts, or external proxy calls.

### Frontend

React SPA built with `@wordpress/scripts`. Entry: `admin/src/index.js`, output: `admin/build/`. Three tabs: Chat (default), History, Settings. Communicates exclusively via the `nhrada/v1` REST namespace using `@wordpress/api-fetch`. CSS class prefix: `.nhrada-`.

Enqueued only on `toplevel_page_nhrada-assistant` (menu slug `nhrada-assistant`).

## Key Conventions

- Main class: `Nhrada_AI_Developer_Assistant` (singleton in main plugin file)
- Namespace: `Nhrada\AIDeveloperAssistant\` (PSR-4 from `includes/`)
- Constant prefix: `NHRADA_`
- Option prefix: `nhrada_` (all settings in single `nhrada_settings` array)
- DB table: `{prefix}nhrada_log`
- REST namespace: `nhrada/v1`
- All REST routes require `manage_options` capability
- Debug logging gated behind `nhrada_settings['debug_mode']`; use `maybe_debug_log()` in `AiClient` or `debug_log()` in `ModelFetcher`

## Release Exclusions

The following dev-only files are excluded from both the WordPress.org distribution (`.distignore`) and `git archive` exports (`.gitattributes`). Any new dev-only file must be added to **both** to stay in sync.

Excluded: `CLAUDE.md`, `.ai/`, `admin/src/` (React source — `admin/build/` ships and must NOT be excluded), `node_modules/`, `.github/`, `.gitattributes`, `.gitignore`, `.distignore`, `package.json`, `composer.lock`, `README.md`, and standard tooling files.

## Skills

- `/release_plugin` — step-by-step release procedure (branch sync, version bump, PR, tag, publish)
