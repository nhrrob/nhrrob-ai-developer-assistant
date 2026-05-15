=== NHR AI Developer Assistant ===
Contributors: nhrrob
Tags: ai, developer, assistant, openai, automation
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gives site owners a personal AI developer inside their WordPress admin. Describe a change in plain English and it gets done — with full undo support.

== Description ==

NHR AI Developer Assistant lets you make changes to your WordPress site using plain English chat. No coding required.

Type what you want — "make my header sticky", "add a WhatsApp button", "change button color to red" — and the assistant implements it safely, with one-click undo for every change.

### Key Features

* **AI Chat** – Describe any site change in plain English.
* **Multiple AI Providers** – Works with WordPress native AI (7.0+), Claude (Anthropic), ChatGPT (OpenAI), or Gemini (Google). Bring your own API key.
* **Safety First** – All generated code is validated before being applied to your site.
* **Full Undo** – Every change is snapshotted. Revert any change instantly.
* **Change History** – Browse and manage every change the assistant has made.

### AI Provider Options

* **WordPress Native AI (7.0+)** – If you are on WordPress 7.0 or later and have configured a WordPress AI provider, no API key is needed.
* **Bring Your Own Key** – Enter your own API key for Claude (Anthropic), ChatGPT (OpenAI), or Gemini (Google) in the plugin settings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to **AI Developer** in the admin menu.
4. If you are on WordPress 7.0+ with a configured AI provider, the assistant works immediately. Otherwise, go to **Settings** and enter an API key for your preferred provider.

== Frequently Asked Questions ==

= Do I need an API key? =
Not if you are on WordPress 7.0 or later with a WordPress AI provider configured. Otherwise, you will need a free or paid API key from Anthropic, OpenAI, or Google.

= Is my data sent anywhere? =
Your messages are sent to the AI provider you choose (WordPress AI, Anthropic, OpenAI, or Google) to generate responses. No data is sent to any other third party.

= Can I undo a change? =
Yes. Every change has an Undo button in the chat, and all changes are listed in the History tab.

== Changelog ==

= 1.1.0 =
* Added support for WordPress 7.0 native AI client — no API key needed when a WordPress AI provider is configured.
* Removed SaaS/backend dependency — plugin is now fully self-contained.

= 1.0.0 =
* Initial release.
