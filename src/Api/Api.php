<?php
namespace NHR\AIAssistant\Api;

use NHR\AIAssistant\Context;
use NHR\AIAssistant\AiClient;
use NHR\AIAssistant\Executor;
use NHR\AIAssistant\Undo;
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
        register_rest_route( 'nhraa/v1', '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_chat_request' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
        
        register_rest_route( 'nhraa/v1', '/undo', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_undo_request' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'nhraa/v1', '/settings', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_settings' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'nhraa/v1', '/settings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'save_settings' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'nhraa/v1', '/history', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_history' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    public function handle_chat_request( WP_REST_Request $request ) {
        $message = $request->get_param( 'message' );
        
        if ( empty( $message ) ) {
            return new WP_Error( 'empty_message', 'Message cannot be empty', array( 'status' => 400 ) );
        }

        // 1. Log the user message to DB
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'nhraa_messages',
            array(
                'role'       => 'user',
                'content'    => $message,
                'created_at' => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%s' )
        );

        // 2. Get context
        $context_manager = new Context();
        $context = $context_manager->get_context();

        // 3. Send to AI
        $client = new AiClient();
        $ai_response = $client->send_request( $message, $context );

        if ( isset( $ai_response['error'] ) ) {
            return rest_ensure_response( array( 'message' => $ai_response['error'] ) );
        }

        // 4. Apply changes
        $executor = new Executor();
        $change_id = $executor->apply_change( $message, $ai_response );

        if ( is_wp_error( $change_id ) ) {
            return rest_ensure_response( array( 'message' => $change_id->get_error_message() ) );
        }

        if ( false === $change_id ) {
            return rest_ensure_response( array( 'message' => 'Failed to apply the change.' ) );
        }

        if ( true !== $change_id ) {
            $ai_response['change_id'] = $change_id;
        }

        // 5. Log the assistant response to DB
        $wpdb->insert(
            $wpdb->prefix . 'nhraa_messages',
            array(
                'role'       => 'assistant',
                'content'    => isset($ai_response['confirmation_message']) ? $ai_response['confirmation_message'] : 'Done.',
                'change_id'  => is_numeric($change_id) ? $change_id : null,
                'created_at' => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%d', '%s' )
        );

        return rest_ensure_response( $ai_response );
    }

    public function handle_undo_request( WP_REST_Request $request ) {
        $change_id = $request->get_param( 'change_id' );

        if ( empty( $change_id ) ) {
            return new WP_Error( 'missing_id', 'Change ID is required', array( 'status' => 400 ) );
        }

        $undo = new Undo();
        $result = $undo->revert_change( $change_id );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response( array( 'error' => $result->get_error_message() ) );
        }

        return rest_ensure_response( array( 'success' => true, 'message' => 'Undone. Your site is back to how it was before.' ) );
    }

    public function get_settings( WP_REST_Request $request ) {
        return rest_ensure_response( array(
            'nhraa_licence_key' => get_option( 'nhraa_licence_key', '' ),
            'nhraa_claude_api_key' => get_option( 'nhraa_claude_api_key', '' ),
        ) );
    }

    public function save_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( isset( $params['nhraa_licence_key'] ) ) {
            update_option( 'nhraa_licence_key', sanitize_text_field( $params['nhraa_licence_key'] ) );
        }
        if ( isset( $params['nhraa_claude_api_key'] ) ) {
            update_option( 'nhraa_claude_api_key', sanitize_text_field( $params['nhraa_claude_api_key'] ) );
        }
        return rest_ensure_response( array( 'success' => true ) );
    }

    public function get_history( WP_REST_Request $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nhraa_changes';
        $results = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC LIMIT 50", ARRAY_A );
        return rest_ensure_response( $results );
    }
}
