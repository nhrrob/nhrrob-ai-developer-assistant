<?php
namespace NHR\AIDeveloperAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AiClient {

    const CLAUDE_MODEL = 'claude-sonnet-4-6';
    const OPENAI_MODEL = 'gpt-4o-mini';
    const GEMINI_MODEL = 'gemini-2.0-flash';

    /**
     * @param string $user_message
     * @param array  $context
     * @param array  $conversation_history  Previous messages [['role'=>'user','content'=>'...'], ...]
     */
    public function send_request( $user_message, $context, $conversation_history = array() ) {
        // Use WP 7.0+ native AI client when available and configured.
        if ( function_exists( 'wp_supports_ai' ) && wp_supports_ai() ) {
            $builder = wp_ai_client_prompt();
            if ( $builder->is_supported_for_text_generation() ) {
                return $this->call_wp_ai_client( $user_message, $context, $conversation_history );
            }
        }

        $provider = get_option( 'nhrada_ai_provider', 'claude' );

        if ( 'openai' === $provider ) {
            $api_key = get_option( 'nhrada_openai_api_key' );
            if ( ! empty( $api_key ) ) {
                return $this->call_openai( $api_key, $user_message, $context, $conversation_history );
            }
        } elseif ( 'gemini' === $provider ) {
            $api_key = get_option( 'nhrada_gemini_api_key' );
            if ( ! empty( $api_key ) ) {
                return $this->call_gemini( $api_key, $user_message, $context, $conversation_history );
            }
        } else {
            $api_key = get_option( 'nhrada_claude_api_key' );
            if ( ! empty( $api_key ) ) {
                return $this->call_anthropic( $api_key, $user_message, $context, $conversation_history );
            }
        }

        return array( 'error' => 'No AI provider configured. Please add an API key in AI Developer > Settings, or configure a WordPress AI provider.' );
    }

    /**
     * Call the WordPress 7.0+ native AI client.
     * Declares model preferences but lets WP route to whatever is configured.
     */
    private function call_wp_ai_client( $user_message, $context, $history ) {
        $system_prompt = $this->build_system_prompt( $context );

        // Convert flat history arrays to WP Message objects (role: user|model, not user|assistant).
        $history_messages = array();
        foreach ( $history as $h ) {
            if ( ! in_array( $h['role'], array( 'user', 'assistant' ), true ) ) {
                continue;
            }
            $role = 'assistant' === $h['role'] ? 'model' : 'user';
            try {
                $history_messages[] = \WordPress\AiClient\Messages\DTO\Message::fromArray( array(
                    'role'  => $role,
                    'parts' => array( array( 'type' => 'text', 'text' => $h['content'] ) ),
                ) );
            } catch ( \Exception $e ) {
                // Skip malformed history entries.
                continue;
            }
        }

        $builder = wp_ai_client_prompt()
            ->using_system_instruction( $system_prompt )
            ->using_model_preference( self::CLAUDE_MODEL, self::OPENAI_MODEL, self::GEMINI_MODEL )
            ->using_max_tokens( 4000 );

        if ( ! empty( $history_messages ) ) {
            $builder = $builder->with_history( ...$history_messages );
        }

        $text = $builder->with_text( $user_message )->generate_text();

        if ( is_wp_error( $text ) ) {
            $this->maybe_debug_log( 'WP AI Client error: ' . $text->get_error_message() );
            return array( 'error' => $text->get_error_message() );
        }

        return $this->parse_text_response( $text );
    }

    /**
     * Call the Anthropic API directly (Bring Your Own Key).
     */
    private function call_anthropic( $api_key, $user_message, $context, $history ) {
        $system_prompt = $this->build_system_prompt( $context );

        $messages = array();
        foreach ( $history as $h ) {
            if ( in_array( $h['role'], array( 'user', 'assistant' ), true ) ) {
                $messages[] = array(
                    'role'    => $h['role'],
                    'content' => $h['content'],
                );
            }
        }
        $messages[] = array( 'role' => 'user', 'content' => $user_message );

        $body = array(
            'model'      => self::CLAUDE_MODEL,
            'max_tokens' => 4000,
            'system'     => $system_prompt,
            'messages'   => $messages,
        );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'API connection error: ' . $response->get_error_message() );
        }

        $status   = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $data     = json_decode( $raw_body, true );

        if ( $status !== 200 ) {
            $err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown Claude API error';
            $this->maybe_debug_log( 'Claude API error (' . $status . '): ' . $err );
            return array( 'error' => $err );
        }

        $text = isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : null;
        return $this->parse_text_response( $text );
    }

    /**
     * Call the OpenAI API (gpt-4o-mini).
     */
    private function call_openai( $api_key, $user_message, $context, $history ) {
        $system_prompt = $this->build_system_prompt( $context );

        $messages = array( array( 'role' => 'system', 'content' => $system_prompt ) );
        foreach ( $history as $h ) {
            if ( in_array( $h['role'], array( 'user', 'assistant' ), true ) ) {
                $messages[] = array( 'role' => $h['role'], 'content' => $h['content'] );
            }
        }
        $messages[] = array( 'role' => 'user', 'content' => $user_message );

        $body = array(
            'model'      => self::OPENAI_MODEL,
            'messages'   => $messages,
            'max_tokens' => 4000,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'OpenAI connection error: ' . $response->get_error_message() );
        }

        $status   = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $data     = json_decode( $raw_body, true );

        if ( $status !== 200 ) {
            $err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown OpenAI error';
            $this->maybe_debug_log( 'OpenAI error (' . $status . '): ' . $err );
            return array( 'error' => $err );
        }

        $text = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : null;
        return $this->parse_text_response( $text );
    }

    /**
     * Call the Google Gemini API (gemini-1.5-flash — free tier).
     */
    private function call_gemini( $api_key, $user_message, $context, $history ) {
        $system_prompt = $this->build_system_prompt( $context );

        $contents = array();
        foreach ( $history as $h ) {
            // Gemini uses 'model' instead of 'assistant'
            $role       = 'assistant' === $h['role'] ? 'model' : 'user';
            $contents[] = array( 'role' => $role, 'parts' => array( array( 'text' => $h['content'] ) ) );
        }
        $contents[] = array( 'role' => 'user', 'parts' => array( array( 'text' => $user_message ) ) );

        $body = array(
            'system_instruction' => array( 'parts' => array( array( 'text' => $system_prompt ) ) ),
            'contents'           => $contents,
            'generationConfig'   => array( 'maxOutputTokens' => 4000 ),
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::GEMINI_MODEL . ':generateContent?key=' . $api_key;

        $response = wp_remote_post( $url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'Gemini connection error: ' . $response->get_error_message() );
        }

        $status   = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $data     = json_decode( $raw_body, true );

        if ( $status !== 200 ) {
            $err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown Gemini error';
            $this->maybe_debug_log( 'Gemini error (' . $status . '): ' . $err );
            return array( 'error' => $err );
        }

        $text = isset( $data['candidates'][0]['content']['parts'][0]['text'] )
            ? $data['candidates'][0]['content']['parts'][0]['text']
            : null;
        return $this->parse_text_response( $text );
    }

    /**
     * Parse raw text from any AI provider into the expected response array.
     */
    private function parse_text_response( $text ) {
        if ( null === $text ) {
            return array( 'error' => 'Unexpected AI response format.' );
        }

        // Strip markdown code fences if present
        $text = preg_replace( '/^```(?:json)?\s*/im', '', $text );
        $text = preg_replace( '/\s*```\s*$/im', '', $text );
        $text = trim( $text );

        // Extract first JSON object if there's extra text
        if ( '{' !== substr( $text, 0, 1 ) ) {
            if ( preg_match( '/\{.*\}/s', $text, $m ) ) {
                $text = $m[0];
            }
        }

        $parsed = json_decode( $text, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $this->maybe_debug_log( 'JSON parse error: ' . json_last_error_msg() . ' | Raw: ' . substr( $text, 0, 500 ) );
            return array( 'error' => 'Could not parse AI response. Please try again.' );
        }

        return $parsed;
    }

    /**
     * Build the system prompt with site context injected.
     */
    private function build_system_prompt( $context ) {
        $date         = gmdate( 'Y-m-d' );
        $plugin_list  = isset( $context['plugin_list'] ) ? $context['plugin_list'] : 'unknown';
        $error_log    = isset( $context['error_log'] ) ? $context['error_log'] : 'N/A';

        return "You are an expert WordPress developer embedded as an AI assistant inside a WordPress admin panel. You help non-technical site owners implement changes to their website using plain English.

## Site context
WordPress version: {$context['wp_version']}
PHP version: {$context['php_version']}
Active theme: {$context['theme_name']} ({$context['theme_version']})
Child theme: {$context['child_theme']}
Active plugins: {$plugin_list}
WooCommerce active: {$context['woocommerce']}
Recent PHP errors: {$error_log}
Today's date: {$date}

## How to respond

Always return valid JSON in this exact structure. No text outside the JSON.

{
  \"can_do\": boolean,
  \"change_type\": \"css\" | \"js\" | \"php\" | \"option\" | \"none\",
  \"file_target\": string | null,
  \"code\": string | null,
  \"description\": string,
  \"confirmation_message\": string,
  \"cannot_reason\": string | null,
  \"warnings\": string | null
}

## Field definitions

can_do: true if you can implement this, false if you cannot or should not.
change_type:
  - \"css\" = add/change styles (goes into WordPress custom CSS)
  - \"js\" = JavaScript snippet (output in footer)
  - \"php\" = PHP snippet (goes into managed snippets file at /wp-content/nhrada-snippets.php)
  - \"option\" = WordPress option update via update_option()
  - \"none\" = answering a question or diagnosing without making a change
file_target:
  - For css: \"custom-css\"
  - For js: \"custom-js\"
  - For php: \"functions-snippet\"
  - For option: the option name e.g. \"blogname\"
code: The complete code to apply. Must be ready to execute as-is.
description: One or two sentence technical summary for the change log.
confirmation_message: Friendly plain-English message for the user. Start with \"Done!\" if successful.
cannot_reason: If can_do is false, one sentence explaining why.
warnings: Optional note about something the user should be aware of.

## Coding standards

CSS:
- Scope selectors specifically; avoid * or broad overrides
- Add comment: /* WP AI Developer | {$date} | {task} */
- Prefer CSS variables if the theme uses them

JavaScript:
- Wrap in document.addEventListener('DOMContentLoaded', function() { ... })
- Use vanilla JS only (no jQuery unless the site definitely has it)
- Add comment: // WP AI Developer | {$date} | {task}

PHP:
- Use add_action() / add_filter() hooks — never echo directly at top level
- Wrap in if (!function_exists(...)) checks
- No direct database queries; use WordPress functions
- Add comment: // WP AI Developer | {$date} | {task}

## Safety rules — NEVER generate code that:
- Touches WordPress core files
- Calls exec(), shell_exec(), system(), passthru(), or eval()
- Accesses external URLs not already in use by the site
- Contains DROP, TRUNCATE, or DELETE SQL statements
- References wp-config.php
- Deletes posts, users, orders, or any user-created content
- Stores or transmits passwords or payment data

If the request would require any of the above, set can_do to false and explain in cannot_reason.

## Tone for confirmation_message
- Friendly and plain — like a helpful developer colleague
- One or two sentences explaining what the user will now see or experience
- Never use technical jargon; translate everything to plain English
- Always start with \"Done!\" for successful changes";
    }

    private function maybe_debug_log( $message ) {
        if ( get_option( 'nhrada_debug_mode' ) ) {
            error_log( '[NHRAA] ' . $message );
        }
    }
}
