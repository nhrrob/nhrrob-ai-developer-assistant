# WP AI Developer Assistant — Product Requirements Document

**Version:** 1.0  
**Date:** May 2026  
**Status:** Ready for implementation  
**Target:** Claude Code / Claude Projects

---

## 1. Product Overview

### 1.1 What it is

WP AI Developer Assistant is a WordPress plugin that gives site owners a personal AI developer inside their WordPress admin. The site owner types a request in plain English — "make my header sticky", "fix my contact form", "add a WhatsApp button" — and the plugin implements the change automatically on their live site.

No developer needed. No technical knowledge required.

### 1.2 The problem it solves

Non-technical WordPress site owners constantly need small fixes and improvements but face three bad options:
- Hire a freelancer ($20–50/hr, 24–72hr wait)
- Learn to code themselves (steep learning curve)
- Leave the site broken or unchanged

This plugin is a fourth option: talk to your site like you'd talk to a developer, and it just gets done.

### 1.3 Target users

- Small business owners with a WordPress site
- Bloggers and content creators
- WooCommerce store owners
- Freelancers managing their own portfolio site
- Non-technical site owners who currently hire developers for small tasks

### 1.4 Success metrics

- Plugin activated and first task completed within 5 minutes of install
- 10% of free users convert to Pro within 30 days
- Average user sends 8+ tasks per month
- Less than 2% of changes require manual undo

---

## 2. Core Concept & User Experience

### 2.1 Mental model

The site owner should feel like they have a developer sitting next to them. They talk. Things happen. They do not need to understand what happened under the hood.

### 2.2 Core user flow

```
User types request in chat
        ↓
Plugin collects site context silently
        ↓
Claude API understands request + context
        ↓
Claude generates appropriate code
        ↓
Plugin safely applies code to site
        ↓
User gets plain English confirmation + Undo button
```

### 2.3 Example interactions

**Request:** "Make my site header sticky when scrolling"  
**Response:** "Done! Your header will now stay fixed at the top as visitors scroll down. I added a CSS rule targeting your header element. [Undo]"

**Request:** "My contact form isn't sending emails"  
**Response:** "I found the issue — your SMTP settings weren't configured. I've enabled WordPress's default mail function and set the from address to your admin email. Please send a test message to confirm it's working. [Undo]"

**Request:** "Add a WhatsApp chat button, bottom right corner, my number is +8801712345678"  
**Response:** "Done! A floating WhatsApp button now appears on every page, bottom-right corner, in WhatsApp green. Clicking it opens a chat with your number. [Undo]"

**Request:** "Change my site's primary color to #e63946"  
**Response:** "Done! I've updated the primary color in your theme's custom CSS. Buttons, links, and accents now use your new color. [Undo]"

**Request (cannot do):** "Delete all orders from last month"  
**Response:** "I can't do that — deleting orders could permanently affect your store records. I can help you export them or change their status instead. What would you like?"

### 2.4 What the plugin can and cannot do

**Can do (MVP scope):**
- Add and modify CSS styles
- Add JavaScript snippets (enqueued properly)
- Add PHP code to a managed snippets file (not core files)
- Change theme customizer settings
- Update wp_options values for simple settings
- Modify WooCommerce text, labels, button text
- Add/edit widgets
- Explain errors from WP debug log
- Answer WordPress questions without changing anything
- Diagnose common issues (broken forms, slow site, plugin conflicts)

**Cannot do (safety boundaries — never cross these):**
- Edit WordPress core files
- Drop or truncate database tables
- Delete posts, orders, or users
- Modify wp-config.php
- Install or delete plugins automatically (can suggest, not execute)
- Access or transmit user passwords or payment data

---

## 3. Architecture

### 3.1 System components

```
┌─────────────────────────────────────────┐
│           WordPress Plugin              │
│  - Admin chat UI (React or vanilla JS)  │
│  - REST API endpoints (receive/respond) │
│  - Code executor (safe file writer)     │
│  - Change log (DB table)                │
│  - Undo system (snapshots)              │
│  - Context collector                    │
└────────────────┬────────────────────────┘
                 │ HTTPS
┌────────────────▼────────────────────────┐
│         Your SaaS Backend (Laravel)     │
│  - Licence key validation               │
│  - Usage counting (free tier limits)    │
│  - Claude API proxy                     │
│  - Prompt builder                       │
│  - Billing (Freemius / LemonSqueezy)    │
└────────────────┬────────────────────────┘
                 │ API
┌────────────────▼────────────────────────┐
│           Claude API (Anthropic)        │
│  - claude-sonnet-4-5 model              │
│  - Structured JSON output               │
└─────────────────────────────────────────┘
```

### 3.2 Plugin file structure

```
wp-ai-developer/
├── wp-ai-developer.php          # Main plugin file, bootstrap
├── readme.txt                   # WP repo readme
├── uninstall.php                # Cleanup on uninstall
│
├── includes/
│   ├── class-plugin.php         # Core plugin class, init hooks
│   ├── class-admin.php          # Admin menu, pages
│   ├── class-api.php            # REST API endpoints
│   ├── class-ai-client.php      # Communicates with your Laravel backend
│   ├── class-executor.php       # Applies code changes to site
│   ├── class-context.php        # Collects site context
│   ├── class-changelog.php      # Logs all changes
│   ├── class-undo.php           # Snapshot + rollback system
│   ├── class-safety.php         # Validates code before execution
│   └── class-licence.php        # Licence key management
│
├── admin/
│   ├── views/
│   │   ├── chat.php             # Main chat page template
│   │   ├── history.php          # Change history page
│   │   └── settings.php         # Settings page (API key, licence)
│   ├── css/
│   │   └── admin.css            # Chat UI styles
│   └── js/
│       └── chat.js              # Chat UI logic (vanilla JS)
│
├── assets/
│   └── icon.png                 # Plugin icon for WP repo
│
└── languages/
    └── wp-ai-developer.pot      # i18n pot file
```

### 3.3 Database tables

```sql
-- Change log: every change ever made
CREATE TABLE {prefix}nhraa_changes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request     TEXT NOT NULL,              -- user's original message
    description TEXT NOT NULL,             -- plain English of what was done
    change_type VARCHAR(50) NOT NULL,      -- css | php | js | option | none
    file_target VARCHAR(255),              -- which file was modified
    created_at  DATETIME NOT NULL,
    status      VARCHAR(20) DEFAULT 'applied'  -- applied | undone
);

-- Snapshots: file state before each change (for undo)
CREATE TABLE {prefix}nhraa_snapshots (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    change_id   BIGINT UNSIGNED NOT NULL,
    snapshot_type VARCHAR(20) NOT NULL,    -- file | option
    target_key  VARCHAR(500) NOT NULL,     -- file path or option name
    original_value LONGTEXT,              -- content before change
    new_value   LONGTEXT,                 -- content after change
    created_at  DATETIME NOT NULL,
    FOREIGN KEY (change_id) REFERENCES {prefix}nhraa_changes(id)
);

-- Chat history: conversation per session
CREATE TABLE {prefix}nhraa_messages (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role        VARCHAR(10) NOT NULL,      -- user | assistant
    content     TEXT NOT NULL,
    change_id   BIGINT UNSIGNED,          -- linked change if one was made
    created_at  DATETIME NOT NULL
);
```

---

## 4. Feature Specifications

### 4.1 Chat interface

**Location:** WordPress Admin → AI Developer (top-level menu item)

**UI elements:**
- Chat message history (scrollable, most recent at bottom)
- Text input box (multi-line, submit on Enter or button)
- Send button
- Each assistant message shows an Undo button if a change was made
- Loading indicator while AI is thinking
- Character counter on input (max 1000 chars)
- "Thinking..." animation while processing

**Design principles:**
- Clean, minimal — white background, subtle borders
- Feels like a chat app, not a settings panel
- Mobile responsive (site owners may use on phone via WP mobile app or browser)
- No jargon — plain English everywhere

**Chat history:**
- Stored in `nhraa_messages` table
- Last 50 messages shown on load
- Older messages accessible via "Load more"
- History persists across sessions

### 4.2 Context collector

Before every API call, the plugin silently collects site context. This is injected into the system prompt so Claude understands the site without the user having to explain it.

**Collected context:**
```
- WordPress version
- PHP version
- Active theme name and version
- List of active plugins (name + version)
- Site URL and admin email
- WooCommerce: yes/no, active if yes
- Current page URL (if determinable)
- Recent PHP errors from debug.log (last 10 lines, if WP_DEBUG enabled)
- Child theme: yes/no
- Customizer: active color palette, font choices
```

**Context is never stored server-side.** It is sent with each request and discarded after the response.

### 4.3 AI request pipeline

**Step 1 — Build system prompt:**
```
You are an expert WordPress developer assistant embedded inside a WordPress admin panel.

SITE CONTEXT:
- WordPress: {version}
- PHP: {version}
- Theme: {theme_name} {theme_version}
- Active plugins: {plugin_list}
- WooCommerce: {yes/no}
- Recent errors: {error_log_snippet}

YOUR JOB:
Understand the site owner's request and implement it safely. 
Always return a JSON response in this exact format:

{
  "can_do": true,
  "change_type": "css|php|js|option|none",
  "file_target": "custom-css|functions-snippet|custom-js|option-name",
  "code": "...the code to apply...",
  "description": "Plain English explanation of what you did",
  "confirmation_message": "Done! [plain English for the user]",
  "cannot_reason": null
}

If you cannot or should not do the request, set can_do to false and explain in cannot_reason.

SAFETY RULES — NEVER:
- Touch WordPress core files
- Delete database records or tables
- Modify wp-config.php
- Access payment or password data
- Execute shell commands
- Write code that calls external URLs not already in the site

CODING RULES:
- PHP: use add_action / add_filter hooks properly, never direct output
- CSS: scope selectors to avoid breaking other styles
- JS: wrap in DOMContentLoaded, use vanilla JS only
- Always check if a function exists before defining it
- Add a comment // WP AI Developer — [date] above every snippet
```

**Step 2 — Send to Laravel backend:**
```
POST https://your-backend.com/api/chat
Headers:
  X-Licence-Key: {key}
  X-Site-URL: {site_url}
Body:
  {
    "messages": [...conversation history...],
    "context": {...site context...},
    "user_message": "make my header sticky"
  }
```

**Step 3 — Laravel backend:**
- Validates licence key
- Checks usage count (free tier: 10/mo)
- Builds final prompt
- Calls Claude API
- Returns structured JSON response
- Increments usage counter

**Step 4 — Plugin receives response and executes**

### 4.4 Code executor

The executor applies changes to the site safely. It uses a whitelist approach — only specific targets are allowed.

**Allowed targets and how they are applied:**

| Target | Method | Location |
|--------|--------|----------|
| `custom-css` | Append to custom CSS via WP Customizer API | wp_options: `custom_css_post_id` |
| `functions-snippet` | Append to a managed snippets file | `/wp-content/nhraa-snippets.php` (required by child theme or plugin) |
| `custom-js` | Enqueue via wp_footer hook | Stored in wp_options, output via hook |
| `option-{name}` | `update_option()` | WordPress options table |

**The snippets file approach:**
Instead of writing to `functions.php`, all PHP snippets are written to `/wp-content/nhraa-snippets.php`. This file is auto-created and auto-required. It is structured with named sections so each snippet can be individually removed on undo.

```php
<?php
// WP AI Developer — Managed Snippets File
// Do not edit manually. Use the AI Developer plugin to manage.

// [NHRAA-SNIPPET-001 | 2026-05-09 | sticky header]
add_action('wp_head', function() { ... });
// [/NHRAA-SNIPPET-001]

// [NHRAA-SNIPPET-002 | 2026-05-09 | whatsapp button]
add_action('wp_footer', function() { ... });
// [/NHRAA-SNIPPET-002]
```

**Before applying any change:**
1. Take a snapshot of the current state (file content or option value)
2. Store snapshot in `nhraa_snapshots`
3. Apply the change
4. Verify the change was written correctly
5. Log to `nhraa_changes`
6. Return confirmation

### 4.5 Undo system

Every applied change can be individually undone.

**Undo button appears:**
- On every assistant message that made a change
- On the Change History page
- Only available while `status = 'applied'`

**Undo process:**
1. Look up snapshot for the change
2. Restore original file content or option value
3. If it was a snippet: remove the named block from the snippets file
4. Update change status to `undone`
5. Confirm to user: "Undone. Your site is back to how it was before."

**Full history undo:**
- Change History page shows all changes in reverse chronological order
- Any change can be undone regardless of order
- Undoing an older change warns: "Newer changes may depend on this. Undo anyway?"

### 4.6 Safety layer

Before any code is executed, the safety class validates it:

**Checks:**
- No `exec()`, `shell_exec()`, `system()`, `passthru()` calls
- No `file_get_contents()` pointing to external URLs
- No `DROP`, `TRUNCATE`, `DELETE` SQL statements
- No references to `wp-config.php`
- No `define()` calls that could override constants
- PHP syntax check via `php -l` if available
- Code length limit: 5000 characters per snippet

If any check fails, the change is rejected and the user is told: "I generated code for that but it didn't pass my safety check. Please contact support."

### 4.7 Free vs Pro

**Free tier:**
- 10 AI requests per month
- CSS and JS changes only
- Undo last change only
- Basic site context
- Chat history: last 20 messages

**Pro tier ($9/month or $79/year):**
- Unlimited AI requests
- All change types (CSS, JS, PHP snippets, options)
- Full undo history (any change, any time)
- Full change history log
- Extended site context (error logs, WooCommerce data)
- Chat history: unlimited
- Bring your own Claude API key
- Priority support

**Upgrade trigger:**
When a free user hits their 10th request, their next request shows:
```
You've used your 10 free requests this month. 
Upgrade to Pro for unlimited requests — just $9/month.
[Upgrade to Pro] [Maybe later]
```

**Usage counter:**
- Stored in `wp_options` as `nhraa_usage_{year}_{month}`
- Reset automatically on the 1st of each month
- Incremented only on successful AI responses (not on errors or "cannot do" responses)

### 4.8 Settings page

**Fields:**
- Licence Key (text input, validate button)
- Your Backend URL (for self-hosted / white-label option later)
- Enable/disable debug mode
- Clear chat history (button)
- Delete all snippets (button, with warning)
- Export change log (CSV download)

---

## 5. Laravel Backend Specification

### 5.1 Purpose

The Laravel backend sits between the plugin and Claude API. It handles:
- Licence validation
- Usage counting per site
- Prompt construction
- Claude API calls (so the API key is never exposed in the plugin)
- Billing integration

### 5.2 API endpoints

```
POST /api/chat
  Headers: X-Licence-Key, X-Site-URL
  Body: { messages, context, user_message }
  Returns: { success, response, usage_remaining }

POST /api/validate-licence
  Body: { licence_key, site_url }
  Returns: { valid, plan, usage_count, usage_limit }

GET /api/usage
  Headers: X-Licence-Key
  Returns: { used, limit, resets_at }
```

### 5.3 Prompt construction (Laravel side)

The backend builds the full prompt:

```php
$systemPrompt = view('prompts.system', [
    'context' => $request->context,
    'plan'    => $licence->plan,
])->render();

$messages = collect($request->messages)
    ->takeLast(10)  // last 10 for context window efficiency
    ->map(fn($m) => ['role' => $m['role'], 'content' => $m['content']])
    ->toArray();

$messages[] = ['role' => 'user', 'content' => $request->user_message];
```

### 5.4 Claude API call

```php
$response = Http::withHeaders([
    'x-api-key'         => config('services.anthropic.key'),
    'anthropic-version' => '2023-06-01',
    'content-type'      => 'application/json',
])->post('https://api.anthropic.com/v1/messages', [
    'model'      => 'claude-sonnet-4-5-20251001',
    'max_tokens' => 2000,
    'system'     => $systemPrompt,
    'messages'   => $messages,
]);
```

### 5.5 Database (Laravel)

```sql
-- Licences
CREATE TABLE licences (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key         VARCHAR(64) UNIQUE NOT NULL,
    plan        ENUM('free', 'pro') DEFAULT 'free',
    email       VARCHAR(255),
    site_url    VARCHAR(500),
    status      ENUM('active', 'suspended', 'expired') DEFAULT 'active',
    created_at  TIMESTAMP,
    expires_at  TIMESTAMP NULL
);

-- Usage tracking
CREATE TABLE usage_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    licence_id  BIGINT UNSIGNED NOT NULL,
    site_url    VARCHAR(500),
    period      VARCHAR(7) NOT NULL,    -- e.g. 2026-05
    count       INT DEFAULT 0,
    UNIQUE KEY period_licence (licence_id, period)
);
```

---

## 6. Security

### 6.1 Plugin security

- All REST endpoints require `manage_options` capability (admin only)
- Nonce verification on all AJAX and REST requests
- Licence key transmitted over HTTPS only, stored encrypted in wp_options
- No user data or site content is ever sent to the backend — only structure/metadata
- Snippets file is outside the theme so theme updates don't wipe it
- Safety layer validates all code before execution (see 4.6)

### 6.2 Backend security

- Rate limiting: 60 requests/minute per licence key
- Licence key hashed in database (bcrypt)
- Site URL locked to licence after first use
- All Claude API responses validated as JSON before passing to plugin
- Logging: all requests logged with timestamp, site URL, change type (not content)

### 6.3 What is never sent to the backend

- Post content or user data
- WooCommerce customer records
- Passwords or API keys from the site
- wp-config.php contents
- Media files

---

## 7. MVP Build Plan

### Week 1 — Plugin skeleton + chat UI

**Deliverables:**
- Plugin activates without errors
- Admin menu item "AI Developer" appears
- Chat page renders with input box and message area
- Messages stored to `nhraa_messages` table
- Basic REST endpoint `/wp-json/nhraa/v1/chat` accepts a message
- Hardcoded response echoed back (no AI yet) — confirms the loop works

**Files to create:**
- `wp-ai-developer.php` (bootstrap, register activation hook, create DB tables)
- `includes/class-plugin.php` (init, register menu, register REST routes)
- `includes/class-admin.php` (render chat page)
- `admin/views/chat.php` (HTML template)
- `admin/js/chat.js` (send message, display response, no page reload)
- `admin/css/admin.css` (chat UI styling)

### Week 2 — Claude integration + code execution

**Deliverables:**
- Context collector gathers real site data
- Plugin calls Laravel backend with message + context
- Laravel backend calls Claude API and returns JSON
- Executor applies CSS and JS changes to site
- Changes logged to `nhraa_changes`
- Snapshots saved to `nhraa_snapshots`
- Confirmation message shown in chat

**Files to create:**
- `includes/class-context.php`
- `includes/class-ai-client.php`
- `includes/class-executor.php`
- `includes/class-changelog.php`
- Laravel backend: routes, ChatController, LicenceService, ClaudeService

### Week 3 — Undo + safety + free/pro limits

**Deliverables:**
- Undo button on every change message
- Undo restores previous state correctly
- Safety checks block dangerous code
- Usage counter enforced (10/month free)
- Upgrade prompt shown when limit hit
- Settings page with licence key field

**Files to create:**
- `includes/class-undo.php`
- `includes/class-safety.php`
- `includes/class-licence.php`
- `admin/views/settings.php`
- `admin/views/history.php`

### After MVP (Phase 2)

- Telegram bot integration
- WooCommerce-specific AI context and capabilities
- Scheduled tasks ("every Monday do X")
- Bring your own API key
- WP plugin repository submission
- Billing integration (Freemius or LemonSqueezy)

---

## 8. Billing & Distribution

### 8.1 Recommended billing platform

**Freemius** — purpose-built for WordPress plugins. Handles:
- Licence key generation and validation
- Payment processing (Stripe under the hood)
- Annual/monthly plans
- Free trial management
- WP plugin repo integration
- Automatic upgrade emails

Alternative: **LemonSqueezy** (simpler, modern UI, good API).

### 8.2 Pricing tiers

| Plan | Price | Requests | Key features |
|------|-------|----------|--------------|
| Free | $0/mo | 10/mo | CSS + JS only, 1-step undo |
| Pro Monthly | $9/mo | Unlimited | All features |
| Pro Annual | $79/yr | Unlimited | All features, 2 months free |

### 8.3 Distribution

1. **WordPress Plugin Repository** — primary channel. Submit after MVP is stable. Free plan lives here.
2. **Your own website** — landing page explaining the product, pro plan purchase.
3. **ProductHunt** — launch after 50+ installs and real testimonials.
4. **WP Facebook groups and communities** — organic posting with demo video.

---

## 9. Claude API Prompt — Full Reference

### 9.1 System prompt template

```
You are an expert WordPress developer embedded as an AI assistant inside a WordPress admin panel. You help non-technical site owners implement changes to their website using plain English.

## Site context
WordPress version: {{wp_version}}
PHP version: {{php_version}}
Active theme: {{theme_name}} ({{theme_version}})
Child theme: {{child_theme}}
Active plugins: {{plugin_list}}
WooCommerce active: {{woocommerce}}
Recent PHP errors: {{error_log}}

## How to respond

Always return valid JSON in this exact structure. No text outside the JSON.

{
  "can_do": boolean,
  "change_type": "css" | "js" | "php" | "option" | "none",
  "file_target": string | null,
  "code": string | null,
  "description": string,
  "confirmation_message": string,
  "cannot_reason": string | null,
  "warnings": string | null
}

## Field definitions

can_do: true if you can implement this, false if you cannot or should not.
change_type: 
  - "css" = add/change styles (goes into custom CSS)
  - "js" = JavaScript (enqueued in footer)
  - "php" = PHP snippet (goes into managed snippets file)
  - "option" = WordPress option update
  - "none" = answering a question, no code change
file_target: 
  - For css: "custom-css"
  - For js: "custom-js"  
  - For php: "functions-snippet"
  - For option: the option name e.g. "blogname"
code: The actual code to apply. Must be complete and ready to run.
description: Technical summary for the change log (1-2 sentences).
confirmation_message: What to show the user. Plain English. Start with "Done!" if successful.
cannot_reason: If can_do is false, explain why in one sentence.
warnings: Optional note about something the user should know.

## Coding standards

CSS:
- Scope selectors specifically, avoid * or body-level overrides
- Add comment: /* WP AI Developer | {date} | {task} */
- Use CSS variables if theme uses them

JavaScript:
- Wrap in document.addEventListener('DOMContentLoaded', function() { ... })
- Vanilla JS only, no jQuery dependency (unless site definitely has it)
- Add comment: // WP AI Developer | {date} | {task}

PHP:
- Always use add_action() or add_filter() hooks
- Check function_exists() before defining functions
- No direct database queries — use WP functions
- Add comment: // WP AI Developer | {date} | {task}

## Safety rules — NEVER generate code that:
- Touches WordPress core files
- Calls exec(), shell_exec(), system(), passthru()
- Makes requests to external URLs not already used by the site
- Contains DROP, TRUNCATE, DELETE SQL
- References wp-config.php
- Deletes posts, users, orders, or any content
- Stores or transmits user passwords or payment data

If the request would require any of the above, set can_do to false.

## Tone for confirmation_message
- Friendly and plain — like a helpful colleague, not a robot
- Explain what was done in one sentence the owner will understand
- Mention what they'll see/experience, not what code was written
- If there's a caveat, mention it simply
```

### 9.2 Example responses

**CSS change:**
```json
{
  "can_do": true,
  "change_type": "css",
  "file_target": "custom-css",
  "code": "/* WP AI Developer | 2026-05-09 | sticky header */\n.site-header {\n  position: sticky;\n  top: 0;\n  z-index: 9999;\n  background: inherit;\n}",
  "description": "Added position:sticky to .site-header with z-index 9999 to keep header visible during scroll.",
  "confirmation_message": "Done! Your header will now stay at the top of the page as visitors scroll down.",
  "cannot_reason": null,
  "warnings": null
}
```

**Cannot do:**
```json
{
  "can_do": false,
  "change_type": "none",
  "file_target": null,
  "code": null,
  "description": "Request to delete orders — outside safe scope.",
  "confirmation_message": null,
  "cannot_reason": "I can't delete orders — that would permanently affect your store records and can't be undone. I can help you export them or change their status instead.",
  "warnings": null
}
```

---

## 10. Testing Checklist

Before shipping each week's work, verify:

### Plugin fundamentals
- [ ] Plugin activates without PHP errors
- [ ] Plugin deactivates cleanly
- [ ] Plugin uninstalls and removes all DB tables and options
- [ ] Works on WordPress 6.0+
- [ ] Works on PHP 8.0+
- [ ] No conflicts with Astra, GeneratePress, Hello Elementor themes
- [ ] No conflicts with WooCommerce, Yoast, CF7, Elementor

### Chat UI
- [ ] Message sends on Enter key
- [ ] Message sends on button click
- [ ] Loading indicator shows while waiting
- [ ] Long messages wrap correctly
- [ ] Chat history loads on page refresh
- [ ] Works on mobile viewport

### Code execution
- [ ] CSS change appears on site frontend immediately
- [ ] JS snippet loads in footer correctly
- [ ] PHP snippet loads without fatal errors
- [ ] Snapshot saved before every change
- [ ] Change logged to DB with correct data

### Undo
- [ ] Undo button appears on changed messages
- [ ] Undo restores CSS to pre-change state
- [ ] Undo restores PHP snippet (removes block from file)
- [ ] Undo updates change status to 'undone'
- [ ] Cannot undo an already-undone change

### Safety
- [ ] exec() in generated code is blocked
- [ ] DROP TABLE in generated code is blocked
- [ ] wp-config.php reference is blocked
- [ ] Blocked code shows error message to user, does not execute

### Free/Pro
- [ ] Usage counter increments on each successful request
- [ ] Upgrade prompt shows on 11th request
- [ ] Counter resets on 1st of month
- [ ] Pro licence key validates correctly
- [ ] Invalid licence key shows clear error

---

## 11. Glossary

| Term | Meaning |
|------|---------|
| Snippet | A block of PHP code managed by the plugin in `nhraa-snippets.php` |
| Snapshot | A saved copy of a file or option before a change was made |
| Change | A single AI-implemented modification to the site |
| Undo | Restoring a change to its pre-change state using a snapshot |
| Context | Site metadata sent with every AI request |
| Licence key | A string that identifies a paid subscription, validated by the backend |
| Backend | Your Laravel SaaS that proxies Claude API calls |
| Free tier | 10 requests/month, CSS+JS only, no account required |
| Pro tier | Unlimited requests, all features, licence key required |

---

*End of document. Ready for implementation with Claude Code.*
