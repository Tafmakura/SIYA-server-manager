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

    public function provision_server($server_post_id) {
        // Retrieve necessary information from metadata
        $server_name = get_post_meta($server_post_id, 'arsol_server_post_name', true);
        $server_plan = get_post_meta($server_post_id, 'arsol_server_plan_slug', true);
        $server_region = get_post_meta($server_post_id, 'arsol_server_region_slug', true) ?: 'ewr';
        $server_image = get_post_meta($server_post_id, 'arsol_server_image_slug', true) ?: 2284;  // Default must support Runcloud
        $ssh_key_id = '86125daf-08ed-4950-9052-fc6eb9eb9207';

        error_log(sprintf('[SIYA Server Manager] Vultr: Starting server provisioning with params:%sName: %s%sPlan: %s%sRegion: %s%sImage: %s', 
            PHP_EOL, $server_name, PHP_EOL, $server_plan, PHP_EOL, $server_region, PHP_EOL, $server_image
        ));

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
                'os_id' => $server_image,
              //  'user_data' => base64_encode($user_script),
                'sshkey_id' => [$ssh_key_id]
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
        error_log('[SIYA Server Manager] Vultr: Raw API Response: ' . json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        // Compile server return data
        $server_data = $this->compile_server_return_data($api_response);
        error_log('[SIYA Server Manager] Vultr: Compiled server data: ' . json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Return the compiled data
        return $server_data;
    }

    private function map_statuses($raw_power_status, $raw_status, $raw_server_status) {

        error_log (sprintf('[SIYA Server Manager] Vultr: Mapping statuses with raw power status: %s, raw status: %s, raw server status: %s', 
            $raw_power_status, $raw_status, $raw_server_status )
        );

        if ($raw_power_status == 'stopped') {
            $mapped_status = 'off';
            
        } elseif ($raw_power_status == 'running') {
            $status_map = [
                'active' => 'active',
                'active (running)' => 'active',
                'pending' => 'starting',
                'resizing' => 'upgrading'
            ];
            $mapped_status = $status_map[$raw_status] ?? $raw_status;

        } else {

            $mapped_status = $raw_power_status;
        }

        error_log(sprintf('[SIYA Server Manager] Vultr: Full status mapping details:%sRaw Status From: %s%sTo: %s', 
            PHP_EOL, var_export($raw_status, true), PHP_EOL, var_export($mapped_status, true)
        ));

        return $mapped_status;
    }

    public function compile_server_return_data($api_response) {
        
        error_log(var_export($api_response, true)); // DELETE THIS IN PRODUCTION

        $raw_power_status = $api_response['instance']['power_status'] ?? '';
        $raw_status = $api_response['instance']['status'] ?? '';
        $raw_server_status = $api_response['instance']['server_status'] ?? '';
        $os_name = $api_response['instance']['os'] ?? '';
        $os_version = $api_response['instance']['os_version'] ?? '';

        return [
            'provisioned_id' => $api_response['instance']['id'] ?? '',
            'provisioned_name' => $api_response['instance']['label'] ?? '',
            'provisioned_vcpu_count' => $api_response['instance']['vcpu_count'] ?? '',
            'provisioned_memory' => $api_response['instance']['ram'] ?? '',
            'provisioned_disk_size' => $api_response['instance']['disk'] ?? '',
            'provisioned_ipv4' => $api_response['instance']['main_ip'] ?? '',
            'provisioned_ipv6' => $api_response['instance']['v6_main_ip'] ?? '',
            'provisioned_os' => $os_name,
            'provisioned_os_version' => $os_version,
            'provisioned_image_slug' => $api_response['instance']['os_id'] ?? '',
            'provisioned_region_slug' => $api_response['instance']['region'] ?? '',
            'provisioned_date' => $api_response['instance']['date_created'] ?? '',
            'provisioned_add_ons' => '',
            'provisioned_root_password' => $api_response['instance']['default_password'] ?? '',
            'provisioned_remote_status' => $this->map_statuses($raw_power_status, $raw_status, $raw_server_status),
            'provisioned_remote_raw_status' => $raw_status,
        ];
    }

    public function ping_server($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/instances/' . $server_provisioned_id, [
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

    public function protect_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . "/instances/{$server_provisioned_id}", [
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

    public function remove_protection_from_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . "/instances/{$server_provisioned_id}", [
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

    public function shutdown_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/instances/' . $server_provisioned_id . '/halt', [
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

    public function poweroff_server($server_provisioned_id) {
        // Vultr uses the same endpoint for shutdown and poweroff
        return $this->shutdown_server($server_provisioned_id);
    }

    public function poweron_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/instances/' . $server_provisioned_id . '/start', [
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

    public function create_server_snapshot($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/snapshots', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'instance_id' => $server_provisioned_id
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

    public function destroy_server($server_provisioned_id) {
        error_log('[SIYA Server Manager][Vultr] Destroying server with ID: ' . $server_provisioned_id);

        $response = wp_remote_request($this->api_endpoint . '/instances/' . $server_provisioned_id, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        error_log('[SIYA Server Manager][Vultr] Server destroy response: ' . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Vultr] Error destroying server: ' . $response->get_error_message());
            throw new \Exception('Failed to destroy server: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 204) {
            error_log('[SIYA Server Manager][Vultr] Error destroying server. Response code: ' . $response_code);
            throw new \Exception('Failed to destroy server. Response code: ' . $response_code);
        }

        error_log('[SIYA Server Manager][Vultr] Server destroyed successfully.');
        return true;
    }

    public function reboot_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/instances/' . $server_provisioned_id . '/reboot', [
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

    public function get_server_status($server_post_id) {

        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        $response = wp_remote_get($this->api_endpoint . '/instances/' . $server_provisioned_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr status error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $api_response = json_decode(wp_remote_retrieve_body($response), true);
        $raw_power_status = $api_response['instance']['power_status'] ?? '';
        $raw_status = $api_response['instance']['status'] ?? '';
        $raw_server_status = $api_response['instance']['server_status'] ?? '';

        return [
            'provisioned_remote_status' => $this->map_statuses($raw_power_status, $raw_status, $raw_server_status),
            'provisioned_remote_raw_status' => $raw_status,
        ];
    }

    public function get_server_ip($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/instances/' . $server_provisioned_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Failed to get server IP: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new \Exception('Failed to get server IP. Response code: ' . $response_code);
        }

        $api_response = json_decode(wp_remote_retrieve_body($response), true);
        return [
            'ipv4' => $api_response['instance']['main_ip'] ?? '',
            'ipv6' => $api_response['instance']['v6_main_ip'] ?? ''
        ];
    }

    public function assign_firewall_rules_to_server($server_provisioned_id) {
        error_log('[SIYA Server Manager][Vultr] Assigning firewall group to server: ' . $server_provisioned_id);

        $firewall_id = '1d93959e-06c7-43d0-9f87-85e91b6d27ac'; // Replace with your actual firewall group ID

        $response = wp_remote_request($this->api_endpoint . "/instances/{$server_provisioned_id}", [
            'method' => 'PATCH',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'firewall_group_id' => $firewall_id
            ])
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('Failed to assign firewall rules to server: ' . $response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) !== 202) {
            throw new \Exception('Failed to assign firewall rules to server. Response code: ' . wp_remote_retrieve_response_code($response));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('Vultr open ports response: ' . $response_body . ', Status: ' . $response_code);

        return $response_code === 202;
    }

}