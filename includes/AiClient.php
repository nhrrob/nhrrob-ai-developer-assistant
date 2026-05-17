<?php
namespace Nhrada\AIDeveloperAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AiClient {

    const CLAUDE_MODEL = 'claude-sonnet-4-6';
    const OPENAI_MODEL = 'gpt-4o-mini';
    const GEMINI_MODEL = 'gemini-2.0-flash';

    public function send_request( $user_message, $context, $conversation_history = array() ) {
        if ( function_exists( 'wp_supports_ai' ) && wp_supports_ai() ) {
            $builder = wp_ai_client_prompt();
            if ( $builder->is_supported_for_text_generation() ) {
                return $this->call_wp_ai_client( $user_message, $context, $conversation_history );
            }
        }

        $settings = get_option( 'nhrada_settings', array() );
        $provider = isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'claude';

        if ( 'openai' === $provider ) {
            $api_key = isset( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : '';
            if ( ! empty( $api_key ) ) {
                return $this->call_openai( $api_key, $user_message, $context, $conversation_history );
            }
        } elseif ( 'gemini' === $provider ) {
            $api_key = isset( $settings['gemini_api_key'] ) ? $settings['gemini_api_key'] : '';
            if ( ! empty( $api_key ) ) {
                return $this->call_gemini( $api_key, $user_message, $context, $conversation_history );
            }
        } else {
            $api_key = isset( $settings['claude_api_key'] ) ? $settings['claude_api_key'] : '';
            if ( ! empty( $api_key ) ) {
                return $this->call_anthropic( $api_key, $user_message, $context, $conversation_history );
            }
        }

        return array( 'error' => 'No AI provider configured. Please add an API key in AI Developer > Settings, or configure a WordPress AI provider.' );
    }

    private function call_wp_ai_client( $user_message, $context, $history ) {
        $prompt           = ( new PromptBuilder() )->build( $context );
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
                continue;
            }
        }

        $builder = wp_ai_client_prompt()
            ->using_system_instruction( $prompt )
            ->using_model_preference( $this->get_model( 'claude' ), $this->get_model( 'openai' ), $this->get_model( 'gemini' ) )
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

    private function call_anthropic( $api_key, $user_message, $context, $history ) {
        $messages = array();
        foreach ( $history as $h ) {
            if ( in_array( $h['role'], array( 'user', 'assistant' ), true ) ) {
                $messages[] = array( 'role' => $h['role'], 'content' => $h['content'] );
            }
        }
        $messages[] = array( 'role' => 'user', 'content' => $user_message );

        $data = $this->post_to_api(
            'https://api.anthropic.com/v1/messages',
            array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ),
            array(
                'model'      => $this->get_model( 'claude' ),
                'max_tokens' => 4000,
                'system'     => ( new PromptBuilder() )->build( $context ),
                'messages'   => $messages,
            ),
            'Claude'
        );

        if ( isset( $data['error'] ) ) {
            return $data;
        }

        return $this->parse_text_response( isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : null );
    }

    private function call_openai( $api_key, $user_message, $context, $history ) {
        $messages = array( array( 'role' => 'system', 'content' => ( new PromptBuilder() )->build( $context ) ) );
        foreach ( $history as $h ) {
            if ( in_array( $h['role'], array( 'user', 'assistant' ), true ) ) {
                $messages[] = array( 'role' => $h['role'], 'content' => $h['content'] );
            }
        }
        $messages[] = array( 'role' => 'user', 'content' => $user_message );

        $data = $this->post_to_api(
            'https://api.openai.com/v1/chat/completions',
            array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            array(
                'model'      => $this->get_model( 'openai' ),
                'messages'   => $messages,
                'max_tokens' => 4000,
            ),
            'OpenAI'
        );

        if ( isset( $data['error'] ) ) {
            return $data;
        }

        return $this->parse_text_response( isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : null );
    }

    private function call_gemini( $api_key, $user_message, $context, $history ) {
        $contents = array();
        foreach ( $history as $h ) {
            $role       = 'assistant' === $h['role'] ? 'model' : 'user';
            $contents[] = array( 'role' => $role, 'parts' => array( array( 'text' => $h['content'] ) ) );
        }
        $contents[] = array( 'role' => 'user', 'parts' => array( array( 'text' => $user_message ) ) );

        $data = $this->post_to_api(
            'https://generativelanguage.googleapis.com/v1beta/models/' . $this->get_model( 'gemini' ) . ':generateContent?key=' . $api_key,
            array( 'Content-Type' => 'application/json' ),
            array(
                'system_instruction' => array( 'parts' => array( array( 'text' => ( new PromptBuilder() )->build( $context ) ) ) ),
                'contents'           => $contents,
                'generationConfig'   => array( 'maxOutputTokens' => 4000 ),
            ),
            'Gemini'
        );

        if ( isset( $data['error'] ) ) {
            return $data;
        }

        return $this->parse_text_response(
            isset( $data['candidates'][0]['content']['parts'][0]['text'] )
                ? $data['candidates'][0]['content']['parts'][0]['text']
                : null
        );
    }

    /**
     * Shared HTTP POST helper — handles the identical boilerplate across all three
     * provider call methods: send request, check WP_Error, decode JSON, check HTTP status.
     * Returns the decoded response array on success, or ['error' => '...'] on failure.
     */
    private function post_to_api( $url, $headers, $body, $provider_name ) {
        $response = wp_remote_post( $url, array(
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $provider_name . ' connection error: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status ) {
            $err = isset( $data['error']['message'] ) ? $data['error']['message'] : "Unknown {$provider_name} error";
            $this->maybe_debug_log( "{$provider_name} error ({$status}): {$err}" );
            return array( 'error' => $err );
        }

        return $data;
    }

    private function parse_text_response( $text ) {
        if ( null === $text ) {
            return array( 'error' => 'Unexpected AI response format.' );
        }

        $text = preg_replace( '/^```(?:json)?\s*/im', '', $text );
        $text = preg_replace( '/\s*```\s*$/im', '', $text );
        $text = trim( $text );

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

    private function get_model( $provider ) {
        $defaults = array(
            'claude' => self::CLAUDE_MODEL,
            'openai' => self::OPENAI_MODEL,
            'gemini' => self::GEMINI_MODEL,
        );
        $settings = get_option( 'nhrada_settings', array() );
        $saved    = isset( $settings[ $provider . '_model' ] ) ? $settings[ $provider . '_model' ] : '';
        return ! empty( $saved ) ? $saved : $defaults[ $provider ];
    }

    private function maybe_debug_log( $message ) {
        $settings = get_option( 'nhrada_settings', array() );
        if ( ! empty( $settings['debug_mode'] ) ) {
            error_log( '[NHRAA] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
