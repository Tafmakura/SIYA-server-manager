<?php

namespace Siya\Integrations\ServerProviders;

use Siya\Interfaces\ServerProvider;

class Vultr /*implements ServerProvider*/ {
    private $api_key;
    private $api_endpoint = 'https://api.vultr.com/v2';

    public function __construct() {
        $this->api_key = get_option('vultr_api_key');
    }

    public function setup() {
        return new VultrSetup();
    }

    public function provision_server($server_name, $server_plan, $server_region = 'ewr', $server_image = 2465) {
        if (empty($server_name)) {
            throw new \Exception('Server name required');
        }

        if (empty($server_plan)) {
            throw new \Exception('Server plan required');
        }

        $response = wp_remote_post($this->api_endpoint . '/instances', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'label' => $server_name,
                'plan' => $server_plan,
                'region' => $server_region,
                'os_id' => $server_image
            ])
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Failed to provision server: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 202) {
            throw new \Exception('Failed to provision server. Response code: ' . $response_code . ', Body: ' . $response_body);
        }

        $api_response = json_decode($response_body, true);
        
        // Compile server return data
        $server_data = $this->compile_server_return_data($api_response);

        // Return the compiled data
        return $server_data;
    }

    private function map_statuses($raw_status) {
        $status_map = [
            'pending' => 'building',
            'installing' => 'building',
            'active' => 'active',
            'stopped' => 'off',
            'rebooting' => 'rebooting'
        ];
        return $status_map[$raw_status] ?? $raw_status;
    }

    public function compile_server_return_data($api_response) {
        
        error_log(var_export($api_response, true)); // DELETE THIS IN PRODUCTION

        $raw_status = $api_response['instance']['status'] ?? '';
        $power_status = $api_response['instance']['power_status'] ?? '';
        
        return [
            'provisioned_name' => $api_response['instance']['label'] ?? '',
            'provisioned_vcpu_count' => $api_response['instance']['vcpu_count'] ?? '',
            'provisioned_memory' => $api_response['instance']['ram'] ?? '',
            'provisioned_disk_size' => $api_response['instance']['disk'] ?? '',
            'provisioned_ipv4' => $api_response['instance']['main_ip'] ?? '',
            'provisioned_ipv6' => $api_response['instance']['v6_main_ip'] ?? '',
            'provisioned_os' => $api_response['instance']['os'] ?? '',
            'provisioned_image_slug' => $api_response['instance']['os_id'] ?? '',
            'provisioned_region_slug' => $api_response['instance']['region'] ?? '',
            'provisioned_date' => $api_response['instance']['date_created'] ?? '',
            'provisioned_add_ons' => '',
            'provisioned_root_password' => $api_response['instance']['default_password'] ?? '',
            'provisioned_remote_status' => $this->map_statuses($raw_status),
            'provisioned_raw_status' => "$raw_status ($power_status)"
        ];
    }

    public function ping_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_get($this->api_endpoint . '/instances/' . $server_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr ping error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }

    public function protect_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . "/instances/{$server_id}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'backups' => 'enabled',
                'ddos_protection' => true
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr protection error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 202;
    }

    public function remove_protection_from_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . "/instances/{$server_id}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'backups' => 'disabled',
                'ddos_protection' => false
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr remove protection error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 202;
    }

    public function shutdown_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/instances/' . $server_id . '/halt', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr shutdown error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 204;
    }

    public function poweroff_server() {
        // Vultr uses the same endpoint for shutdown and poweroff
        return $this->shutdown_server();
    }

    public function poweron_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/instances/' . $server_id . '/start', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr poweron error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 204;
    }

    public function upgrade_server() {
        // To be implemented
    }

    public function create_server_snapshot() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/snapshots', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'instance_id' => $server_id
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr snapshot error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    public function get_server_snapshots() {
        $response = wp_remote_get($this->api_endpoint . '/snapshots', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr get snapshots error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            return json_decode(wp_remote_retrieve_body($response), true);
        }
        return false;
    }

    public function rebuild_server_from_snapshot() {
        // To be implemented
    }

    public function destroy_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_request($this->api_endpoint . '/instances/' . $server_id, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr destroy error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 204;
    }

    public function reboot_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/instances/' . $server_id . '/reboot', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr reboot error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 204;
    }
}
