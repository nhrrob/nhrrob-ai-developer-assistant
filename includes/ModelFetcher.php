<?php
namespace Nhrada\AIDeveloperAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ModelFetcher {

    /**
     * Return available models for a provider.
     * Checks a 24-hour transient cache first; falls back to static list when no
     * API key is stored or the remote fetch fails.
     */
    public function fetch( $provider, $bust = false ) {
        $transient = 'nhrada_models_' . $provider;

        if ( ! $bust ) {
            $cached = get_transient( $transient );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $settings = get_option( 'nhrada_settings', array() );
        $api_key  = isset( $settings[ $provider . '_api_key' ] ) ? $settings[ $provider . '_api_key' ] : '';
        if ( empty( $api_key ) ) {
            return $this->get_static( $provider );
        }

        switch ( $provider ) {
            case 'claude':
                $models = $this->fetch_claude( $api_key );
                break;
            case 'openai':
                $models = $this->fetch_openai( $api_key );
                break;
            case 'gemini':
                $models = $this->fetch_gemini( $api_key );
                break;
            default:
                $models = array();
        }

        if ( empty( $models ) ) {
            return $this->get_static( $provider );
        }

        set_transient( $transient, $models, DAY_IN_SECONDS );
        return $models;
    }

    private function get_static( $provider ) {
        $map = array(
            'claude' => array(
                array( 'id' => 'claude-opus-4-7',          'name' => 'Claude Opus 4.7' ),
                array( 'id' => 'claude-sonnet-4-7',         'name' => 'Claude Sonnet 4.7' ),
                array( 'id' => 'claude-sonnet-4-6',         'name' => 'Claude Sonnet 4.6' ),
                array( 'id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5' ),
            ),
            'openai' => array(
                array( 'id' => 'gpt-4o',      'name' => 'GPT-4o' ),
                array( 'id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini' ),
                array( 'id' => 'o1',          'name' => 'o1' ),
                array( 'id' => 'o1-mini',     'name' => 'o1 Mini' ),
            ),
            'gemini' => array(
                array( 'id' => 'gemini-2.5-pro',   'name' => 'Gemini 2.5 Pro' ),
                array( 'id' => 'gemini-2.0-flash',  'name' => 'Gemini 2.0 Flash' ),
                array( 'id' => 'gemini-1.5-pro',   'name' => 'Gemini 1.5 Pro' ),
                array( 'id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash' ),
            ),
        );
        return isset( $map[ $provider ] ) ? $map[ $provider ] : array();
    }

    private function fetch_claude( $api_key ) {
        $response = wp_remote_get( 'https://api.anthropic.com/v1/models', array(
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $this->debug_log( 'Claude models fetch failed' );
            return array();
        }

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $models = array();

        foreach ( isset( $data['data'] ) ? $data['data'] : array() as $model ) {
            $id = isset( $model['id'] ) ? $model['id'] : '';
            if ( empty( $id ) ) {
                continue;
            }
            $models[] = array(
                'id'   => $id,
                'name' => isset( $model['display_name'] ) ? $model['display_name'] : $id,
            );
        }

        return $models;
    }

    private function fetch_openai( $api_key ) {
        $response = wp_remote_get( 'https://api.openai.com/v1/models', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $this->debug_log( 'OpenAI models fetch failed' );
            return array();
        }

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $models = array();

        foreach ( isset( $data['data'] ) ? $data['data'] : array() as $model ) {
            $id = isset( $model['id'] ) ? $model['id'] : '';
            if ( ! preg_match( '/^(gpt-|o\d)/', $id ) ) {
                continue;
            }
            if ( preg_match( '/(embedding|whisper|tts|dall-e|moderation|babbage|davinci|realtime|audio|search)/', $id ) ) {
                continue;
            }
            $models[] = array( 'id' => $id, 'name' => $id );
        }

        usort( $models, function ( $a, $b ) { return strcmp( $b['id'], $a['id'] ); } );

        return $models;
    }

    private function fetch_gemini( $api_key ) {
        $response = wp_remote_get(
            'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode( $api_key ),
            array( 'timeout' => 15 )
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $this->debug_log( 'Gemini models fetch failed' );
            return array();
        }

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $models = array();

        foreach ( isset( $data['models'] ) ? $data['models'] : array() as $model ) {
            $methods = isset( $model['supportedGenerationMethods'] ) ? $model['supportedGenerationMethods'] : array();
            if ( ! in_array( 'generateContent', $methods, true ) ) {
                continue;
            }
            $id = str_replace( 'models/', '', isset( $model['name'] ) ? $model['name'] : '' );
            if ( empty( $id ) ) {
                continue;
            }
            $models[] = array(
                'id'   => $id,
                'name' => isset( $model['displayName'] ) ? $model['displayName'] : $id,
            );
        }

        return $models;
    }

    private function debug_log( $message ) {
        $settings = get_option( 'nhrada_settings', array() );
        if ( ! empty( $settings['debug_mode'] ) ) {
            error_log( '[NHRAA] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
