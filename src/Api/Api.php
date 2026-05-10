<?php
namespace NHR\AIAssistant\Api;

use NHR\AIAssistant\Context;
use NHR\AIAssistant\AiClient;
use NHR\AIAssistant\Executor;
use NHR\AIAssistant\Undo;
use NHR\AIAssistant\Licence;
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
        $ns = 'nhraa/v1';

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
     * GET /messages — load recent chat messages + usage info.
     */
    public function get_messages( WP_REST_Request $request ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, role, content, change_id, created_at
             FROM {$wpdb->prefix}nhraa_messages
             ORDER BY id DESC
             LIMIT 50",
            ARRAY_A
        );

        // Return in chronological order
        $messages = array_reverse( $rows );

        // Cast IDs
        foreach ( $messages as &$msg ) {
            $msg['id']        = (int) $msg['id'];
            $msg['change_id'] = $msg['change_id'] ? (int) $msg['change_id'] : null;
        }
        unset( $msg );

        $licence = new Licence();
        $usage   = $this->get_usage_info( $licence );

        return rest_ensure_response( array(
            'messages' => $messages,
            'usage'    => $usage,
        ) );
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

        $licence = new Licence();

        // Enforce usage limit for free users
        if ( ! $licence->check_usage() ) {
            $current_month = gmdate( 'Y_m' );
            $used          = (int) get_option( 'nhraa_usage_' . $current_month, 0 );
            return rest_ensure_response( array(
                'upgrade_required' => true,
                'message'          => "You've used your {$used} free requests this month. Upgrade to Pro for unlimited requests — just \$9/month.",
                'usage'            => $this->get_usage_info( $licence ),
            ) );
        }

        // Log the user message
        $wpdb->insert(
            $wpdb->prefix . 'nhraa_messages',
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
             FROM {$wpdb->prefix}nhraa_messages
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

        $wpdb->insert( $wpdb->prefix . 'nhraa_messages', $assistant_data, $assistant_format );

        // Increment usage for successful responses (skip 'none' type — informational only)
        $change_type = isset( $ai_response['change_type'] ) ? $ai_response['change_type'] : 'none';
        if ( 'none' !== $change_type && ! empty( $ai_response['can_do'] ) ) {
            $licence->increment_usage();
        }

        $response = array(
            'confirmation_message' => $display_msg,
            'change_type'          => $change_type,
            'warnings'             => isset( $ai_response['warnings'] ) ? $ai_response['warnings'] : null,
            'usage'                => $this->get_usage_info( $licence ),
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
            "SELECT * FROM {$wpdb->prefix}nhraa_changes ORDER BY created_at DESC LIMIT 100",
            ARRAY_A
        );
        return rest_ensure_response( $results );
    }

    /**
     * GET /settings
     */
    public function get_settings( WP_REST_Request $request ) {
        return rest_ensure_response( array(
            'nhraa_licence_key'    => get_option( 'nhraa_licence_key', '' ),
            'nhraa_ai_provider'    => get_option( 'nhraa_ai_provider', 'claude' ),
            'nhraa_claude_api_key' => get_option( 'nhraa_claude_api_key', '' ) ? '***' : '',
            'nhraa_openai_api_key' => get_option( 'nhraa_openai_api_key', '' ) ? '***' : '',
            'nhraa_gemini_api_key' => get_option( 'nhraa_gemini_api_key', '' ) ? '***' : '',
            'nhraa_debug_mode'     => (bool) get_option( 'nhraa_debug_mode', false ),
        ) );
    }

    /**
     * POST /settings
     */
    public function save_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( isset( $params['nhraa_licence_key'] ) ) {
            update_option( 'nhraa_licence_key', sanitize_text_field( $params['nhraa_licence_key'] ) );
        }

        $allowed_providers = array( 'claude', 'openai', 'gemini' );
        if ( isset( $params['nhraa_ai_provider'] ) && in_array( $params['nhraa_ai_provider'], $allowed_providers, true ) ) {
            update_option( 'nhraa_ai_provider', $params['nhraa_ai_provider'] );
        }

        if ( isset( $params['nhraa_claude_api_key'] ) && '***' !== $params['nhraa_claude_api_key'] ) {
            update_option( 'nhraa_claude_api_key', sanitize_text_field( $params['nhraa_claude_api_key'] ) );
        }

        if ( isset( $params['nhraa_openai_api_key'] ) && '***' !== $params['nhraa_openai_api_key'] ) {
            update_option( 'nhraa_openai_api_key', sanitize_text_field( $params['nhraa_openai_api_key'] ) );
        }

        if ( isset( $params['nhraa_gemini_api_key'] ) && '***' !== $params['nhraa_gemini_api_key'] ) {
            update_option( 'nhraa_gemini_api_key', sanitize_text_field( $params['nhraa_gemini_api_key'] ) );
        }

        if ( isset( $params['nhraa_debug_mode'] ) ) {
            update_option( 'nhraa_debug_mode', (bool) $params['nhraa_debug_mode'] );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * POST /clear-history
     */
    public function clear_history( WP_REST_Request $request ) {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}nhraa_messages" );
        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Build usage info array.
     */
    private function get_usage_info( Licence $licence ) {
        $plan = $licence->get_plan();
        if ( 'pro' === $plan ) {
            return array(
                'plan'  => 'pro',
                'used'  => null,
                'limit' => null,
            );
        }

        $current_month = gmdate( 'Y_m' );
        $used          = (int) get_option( 'nhraa_usage_' . $current_month, 0 );

        return array(
            'plan'  => 'free',
            'used'  => $used,
            'limit' => 10,
        );
    }
}
