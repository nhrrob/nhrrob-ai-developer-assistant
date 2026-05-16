<?php
namespace Nhrada\AIDeveloperAssistant\Api;

use Nhrada\AIDeveloperAssistant\Context;
use Nhrada\AIDeveloperAssistant\AiClient;
use Nhrada\AIDeveloperAssistant\Executor;
use Nhrada\AIDeveloperAssistant\Undo;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Api {

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $ns = 'nhrada/v1';

        register_rest_route( $ns, '/messages', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_messages' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $ns, '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_chat' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $ns, '/undo', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_undo' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $ns, '/history', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_history' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $ns, '/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'save_settings' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( $ns, '/clear-history', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'clear_history' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * GET /messages — load recent chat messages.
     */
    public function get_messages( WP_REST_Request $request ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, role, content, change_id, created_at
             FROM {$wpdb->prefix}nhrada_messages
             ORDER BY id DESC
             LIMIT 50",
            ARRAY_A
        );

        $messages = array_reverse( $rows );

        foreach ( $messages as &$msg ) {
            $msg['id']        = (int) $msg['id'];
            $msg['change_id'] = $msg['change_id'] ? (int) $msg['change_id'] : null;
        }
        unset( $msg );

        return rest_ensure_response( array( 'messages' => $messages ) );
    }

    /**
     * POST /chat — handle a user message.
     */
    public function handle_chat( WP_REST_Request $request ) {
        global $wpdb;

        $message = sanitize_text_field( $request->get_param( 'message' ) );
        if ( empty( $message ) ) {
            return new WP_Error( 'empty_message', 'Message cannot be empty.', array( 'status' => 400 ) );
        }

        // Log the user message
        $wpdb->insert(
            $wpdb->prefix . 'nhrada_messages',
            array(
                'role'       => 'user',
                'content'    => $message,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s' )
        );

        // Build conversation history for context (last 10 messages)
        $history_rows = $wpdb->get_results(
            "SELECT role, content
             FROM {$wpdb->prefix}nhrada_messages
             ORDER BY id DESC
             LIMIT 11",
            ARRAY_A
        );
        // Reverse to chronological; exclude the message we just inserted (it's the newest)
        $history_rows  = array_reverse( $history_rows );
        $history_count = count( $history_rows );
        // Drop the last entry (the user message we just inserted — sent separately)
        if ( $history_count > 1 ) {
            array_pop( $history_rows );
        } else {
            $history_rows = array();
        }

        // Collect site context
        $context_obj = new Context();
        $context     = $context_obj->get_context();

        // Call AI
        $client      = new AiClient();
        $ai_response = $client->send_request( $message, $context, $history_rows );

        if ( isset( $ai_response['error'] ) ) {
            return rest_ensure_response( array( 'message' => $ai_response['error'] ) );
        }

        // Apply changes
        $executor  = new Executor();
        $change_id = $executor->apply_change( $message, $ai_response );

        if ( is_wp_error( $change_id ) ) {
            return rest_ensure_response( array( 'message' => $change_id->get_error_message() ) );
        }

        $display_msg = isset( $ai_response['confirmation_message'] )
            ? $ai_response['confirmation_message']
            : ( isset( $ai_response['cannot_reason'] ) ? $ai_response['cannot_reason'] : 'Done.' );

        // Log assistant response
        $assistant_data   = array(
            'role'       => 'assistant',
            'content'    => $display_msg,
            'created_at' => current_time( 'mysql' ),
        );
        $assistant_format = array( '%s', '%s', '%s' );

        if ( is_numeric( $change_id ) ) {
            $assistant_data['change_id'] = (int) $change_id;
            $assistant_format[]          = '%d';
        }

        $wpdb->insert( $wpdb->prefix . 'nhrada_messages', $assistant_data, $assistant_format );

        $change_type = isset( $ai_response['change_type'] ) ? $ai_response['change_type'] : 'none';

        $response = array(
            'confirmation_message' => $display_msg,
            'change_type'          => $change_type,
            'warnings'             => isset( $ai_response['warnings'] ) ? $ai_response['warnings'] : null,
        );

        if ( is_numeric( $change_id ) ) {
            $response['change_id'] = (int) $change_id;
        }

        return rest_ensure_response( $response );
    }

    /**
     * POST /undo
     */
    public function handle_undo( WP_REST_Request $request ) {
        $change_id = (int) $request->get_param( 'change_id' );

        if ( empty( $change_id ) ) {
            return new WP_Error( 'missing_id', 'Change ID is required.', array( 'status' => 400 ) );
        }

        $undo   = new Undo();
        $result = $undo->revert_change( $change_id );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response( array( 'error' => $result->get_error_message() ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Undone. Your site is back to how it was before.',
        ) );
    }

    /**
     * GET /history — change log
     */
    public function get_history( WP_REST_Request $request ) {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}nhrada_changes ORDER BY created_at DESC LIMIT 100",
            ARRAY_A
        );
        return rest_ensure_response( $results );
    }

    /**
     * GET /settings
     */
    public function get_settings( WP_REST_Request $request ) {
        $wp_ai_available = function_exists( 'wp_supports_ai' ) && wp_supports_ai()
            && wp_ai_client_prompt()->is_supported_for_text_generation();

        return rest_ensure_response( array(
            'nhrada_ai_provider'     => get_option( 'nhrada_ai_provider', 'claude' ),
            'nhrada_claude_api_key'  => get_option( 'nhrada_claude_api_key', '' ) ? '***' : '',
            'nhrada_openai_api_key'  => get_option( 'nhrada_openai_api_key', '' ) ? '***' : '',
            'nhrada_gemini_api_key'  => get_option( 'nhrada_gemini_api_key', '' ) ? '***' : '',
            'nhrada_debug_mode'      => (bool) get_option( 'nhrada_debug_mode', false ),
            'wp_ai_client_available' => $wp_ai_available,
        ) );
    }

    /**
     * POST /settings
     */
    public function save_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        $allowed_providers = array( 'claude', 'openai', 'gemini' );
        if ( isset( $params['nhrada_ai_provider'] ) && in_array( $params['nhrada_ai_provider'], $allowed_providers, true ) ) {
            update_option( 'nhrada_ai_provider', $params['nhrada_ai_provider'] );
        }

        if ( isset( $params['nhrada_claude_api_key'] ) && '***' !== $params['nhrada_claude_api_key'] ) {
            update_option( 'nhrada_claude_api_key', sanitize_text_field( $params['nhrada_claude_api_key'] ) );
        }

        if ( isset( $params['nhrada_openai_api_key'] ) && '***' !== $params['nhrada_openai_api_key'] ) {
            update_option( 'nhrada_openai_api_key', sanitize_text_field( $params['nhrada_openai_api_key'] ) );
        }

        if ( isset( $params['nhrada_gemini_api_key'] ) && '***' !== $params['nhrada_gemini_api_key'] ) {
            update_option( 'nhrada_gemini_api_key', sanitize_text_field( $params['nhrada_gemini_api_key'] ) );
        }

        if ( isset( $params['nhrada_debug_mode'] ) ) {
            update_option( 'nhrada_debug_mode', (bool) $params['nhrada_debug_mode'] );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * POST /clear-history
     */
    public function clear_history( WP_REST_Request $request ) {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}nhrada_messages" );
        return rest_ensure_response( array( 'success' => true ) );
    }

}
