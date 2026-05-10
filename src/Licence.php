<?php
namespace NHR\AIAssistant;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Licence {

    // Simple placeholder logic for MVP
    // In reality, this would communicate with the Laravel backend

    public function get_plan() {
        $key = get_option( 'nhraa_licence_key' );
        if ( ! empty( $key ) && strlen( $key ) > 10 ) {
            return 'pro';
        }
        return 'free';
    }

    public function check_usage() {
        if ( 'pro' === $this->get_plan() ) {
            return true; // Unlimited
        }

        // Free tier logic: max 10 requests per month
        $current_month = gmdate('Y_m');
        $usage_key = 'nhraa_usage_' . $current_month;
        $usage = (int) get_option( $usage_key, 0 );

        if ( $usage >= 10 ) {
            return false;
        }

        return true;
    }

    public function increment_usage() {
        if ( 'pro' === $this->get_plan() ) {
            return;
        }

        $current_month = gmdate('Y_m');
        $usage_key = 'nhraa_usage_' . $current_month;
        $usage = (int) get_option( $usage_key, 0 );
        update_option( $usage_key, $usage + 1 );
    }
}
