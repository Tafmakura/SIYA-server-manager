<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;

class ServerCircuitBreaker {
    private $subscription;
    private $server;
    private $max_attempts = 3;
    private $check_interval = 300; // 5 minutes

    public function __construct($subscription_id) {
        $this->subscription = wcs_get_subscription($subscription_id);
        $this->server = ServerPost::get_server_post_by_subscription($subscription_id);
    }

    public function force_on_hold_and_check_server_status() {
        if (!$this->subscription || !$this->server) {
            return false;
        }

        // Put subscription on-hold
        $this->subscription->update_status('on-hold', 'Checking server status');

        // Schedule status check
        wp_schedule_single_event(
            time() + $this->check_interval,
            'check_server_status_event',
            array($this->subscription->get_id())
        );

        return true;
    }

    public function check_server_status() {
        $server_status = $this->server->get_server_status();
        $attempts = get_post_meta($this->subscription->get_id(), '_server_check_attempts', true) ?: 0;

        if ($server_status === 'running') {
            $this->subscription->update_status('active');
            delete_post_meta($this->subscription->get_id(), '_server_check_attempts');
            return true;
        }

        if ($attempts >= $this->max_attempts) {
            $this->subscription->update_status('cancelled', 'Server failed to start after multiple attempts');
            delete_post_meta($this->subscription->get_id(), '_server_check_attempts');
            return false;
        }

        update_post_meta($this->subscription->get_id(), '_server_check_attempts', ++$attempts);
        
        // Schedule next check
        wp_schedule_single_event(
            time() + $this->check_interval,
            'check_server_status_event',
            array($this->subscription->get_id())
        );

        return null;
    }
}

add_action('check_server_status_event', function($subscription_id) {
    $circuit_breaker = new CircuitBreaker($subscription_id);
    $circuit_breaker->check_server_status();
});
