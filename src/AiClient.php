<?php
namespace NHR\AIAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AiClient {

    private $backend_url = 'https://mock-backend.test/api/chat'; // Fallback to SaaS proxy if no direct key
    
    public function send_request( $user_message, $context ) {
        // Direct integration if API key is provided
        $api_key = get_option( 'nhraa_claude_api_key' );
        
        if ( ! empty( $api_key ) ) {
            return $this->call_anthropic_api( $api_key, $user_message, $context );
        }

        // ... existing fallback to backend logic
        $messages = array(
            array(
                'role' => 'user',
                'content' => $user_message
            )
        );

        $body = array(
            'messages'     => $messages,
            'context'      => $context,
            'user_message' => $user_message
        );

        $response = wp_remote_post( $this->backend_url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'X-Licence-Key' => get_option( 'nhraa_licence_key', '' ),
                'X-Site-URL'    => get_site_url()
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body_raw, true );

        if ( $status_code !== 200 ) {
            return array( 'error' => isset($data['message']) ? $data['message'] : 'Backend error' );
        }

        return isset($data['response']) ? $data['response'] : array('error' => 'Invalid response format');
    }

    private function call_anthropic_api( $api_key, $user_message, $context ) {
        $system_prompt = $this->build_system_prompt( $context );

        $body = array(
            'model'      => 'claude-3-7-sonnet-20250219',
            'max_tokens' => 4000,
            'system'     => $system_prompt,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $user_message
                )
            )
        );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json'
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 60
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'API Error: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body_raw, true );

        if ( $status_code !== 200 ) {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Claude API Error';
            return array( 'error' => $err_msg );
        }

        if ( isset($data['content'][0]['text']) ) {
            $json_str = $data['content'][0]['text'];
            
            // Clean up possible markdown wrappers
            $json_str = preg_replace('/```json\s*/i', '', $json_str);
            $json_str = preg_replace('/```\s*$/i', '', $json_str);
            $json_str = trim($json_str);

            $parsed = json_decode($json_str, true);
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $parsed;
            } else {
                return array( 'error' => 'Claude returned invalid JSON: ' . json_last_error_msg() );
            }
        }

        return array( 'error' => 'Unexpected Claude response format' );
    }

    private function build_system_prompt( $context ) {
        $date = gmdate('Y-m-d');
        return "You are an expert WordPress developer embedded as an AI assistant inside a WordPress admin panel. You help non-technical site owners implement changes to their website using plain English.

## Site context
WordPress version: {$context['wp_version']}
PHP version: {$context['php_version']}
Active theme: {$context['theme_name']} ({$context['theme_version']})
Child theme: {$context['child_theme']}
Active plugins: {$context['plugin_list']}
WooCommerce active: {$context['woocommerce']}
Recent PHP errors: {$context['error_log']}

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
  - \"css\" = add/change styles (goes into custom CSS)
  - \"js\" = JavaScript (enqueued in footer)
  - \"php\" = PHP snippet (goes into managed snippets file)
  - \"option\" = WordPress option update
  - \"none\" = answering a question, no code change
file_target: 
  - For css: \"custom-css\"
  - For js: \"custom-js\"  
  - For php: \"functions-snippet\"
  - For option: the option name e.g. \"blogname\"
code: The actual code to apply. Must be complete and ready to run.
description: Technical summary for the change log (1-2 sentences).
confirmation_message: What to show the user. Plain English. Start with \"Done!\" if successful.
cannot_reason: If can_do is false, explain why in one sentence.
warnings: Optional note about something the user should know.

## Coding standards

CSS:
- Scope selectors specifically, avoid * or body-level overrides
- Add comment: /* WP AI Developer | {$date} | {task} */
- Use CSS variables if theme uses them

JavaScript:
- Wrap in document.addEventListener('DOMContentLoaded', function() { ... })
- Vanilla JS only, no jQuery dependency (unless site definitely has it)
- Add comment: // WP AI Developer | {$date} | {task}

PHP:
- Always use add_action() or add_filter() hooks
- Check function_exists() before defining functions
- No direct database queries — use WP functions
- Add comment: // WP AI Developer | {$date} | {task}

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
- If there's a caveat, mention it simply";
    }
}
