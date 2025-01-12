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
        $server_image = get_post_meta($server_post_id, 'arsol_server_image_slug', true) ?: 2465;

        error_log(sprintf('[SIYA Server Manager] Vultr: Starting server provisioning with params:%sName: %s%sPlan: %s%sRegion: %s%sImage: %s', 
            PHP_EOL, $server_name, PHP_EOL, $server_plan, PHP_EOL, $server_region, PHP_EOL, $server_image
        ));

        if (empty($server_name)) {
            throw new \Exception('Server name required');
        }

        if (empty($server_plan)) {
            throw new \Exception('Server plan required');
        }

        // Setup SSH access
        $user_script = $this->setup_ssh_access($server_post_id);

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
                'user_data' => base64_encode($user_script)
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

    private function setup_ssh_access($server_post_id) {
        // Retrieve SSH key and username from server metadata
        $ssh_public_key = get_post_meta($server_post_id, 'arsol_ssh_public_key', true);
        $ssh_username = get_post_meta($server_post_id, 'arsol_ssh_username', true);

        if (empty($ssh_public_key) || empty($ssh_username)) {
            $error_message = 'SSH key or username not found in server metadata';
            error_log('[SIYA Server Manager][Vultr] ' . $error_message);
            throw new \Exception($error_message);
        }

        error_log(sprintf('[SIYA Server Manager][Vultr] Setting up SSH access for user: %s with public key: %s', $ssh_username, $ssh_public_key));

        $user_script = sprintf(
            "#!/bin/bash\n" .
            "echo '[SIYA Server Manager][Vultr] Creating user: %s'\n" .
            "useradd -m -s /bin/bash %s\n" .
            "echo '[SIYA Server Manager][Vultr] Creating SSH directory'\n" .
            "mkdir -p /home/%s/.ssh\n" .
            "echo '[SIYA Server Manager][Vultr] Copying SSH key'\n" .
            "echo \"%s\" > /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][Vultr] Setting permissions'\n" .
            "chown -R %s:%s /home/%s/.ssh\n" .
            "chmod 700 /home/%s/.ssh\n" .
            "chmod 600 /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][Vultr] User setup completed for: %s'\n",
            $ssh_username,
            $ssh_username,
            $ssh_username,
            $ssh_public_key,
            $ssh_username,
            $ssh_username, $ssh_username,
            $ssh_username,
            $ssh_username,
            $ssh_username,
            $ssh_username
        );

        error_log('[SIYA Server Manager][Vultr] SSH access setup script generated successfully.');

        return $user_script;
    }

    private function map_statuses($raw_status) {
        $status_map = [
            'pending' => 'starting',
            'installing' => 'starting',
            'active' => 'active',
            'stopped' => 'off',
            'rebooting' => 'rebooting'
        ];
        $mapped_status = $status_map[$raw_status] ?? $raw_status;
        error_log(sprintf('[SIYA Server Manager] Vultr: Full status mapping details:%sFrom: %s%sTo: %s', 
            PHP_EOL, var_export($raw_status, true), 
            PHP_EOL, var_export($mapped_status, true)
        ));
        return $mapped_status;
    }

    public function compile_server_return_data($api_response) {
        
        error_log(var_export($api_response, true)); // DELETE THIS IN PRODUCTION

        $raw_status = $api_response['instance']['status'] ?? '';
        $power_status = $api_response['instance']['power_status'] ?? '';
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
            'provisioned_remote_status' => $this->map_statuses($raw_status),
            'provisioned_remote_raw_status' => "$raw_status ($power_status)"
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

    public function get_server_status($server_provisioned_id) {
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
        $raw_status = $api_response['instance']['status'] ?? '';
        $power_status = $api_response['instance']['power_status'] ?? '';

        return [
            'provisioned_remote_status' => $this->map_statuses($raw_status),
            'provisioned_remote_raw_status' => "$raw_status ($power_status)"
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

    public function open_server_ports($server_provisioned_id) {
        $ports = [22, 80, 443, 34210];
        $rules = array_map(function($port) {
            return [
                'protocol' => 'tcp',
                'port' => $port,
                'source' => '0.0.0.0/0'
            ];
        }, $ports);

        $response = wp_remote_post($this->api_endpoint . "/firewalls/{$server_provisioned_id}/rules", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['rules' => $rules])
        ]);

        if (is_wp_error($response)) {
            error_log('Vultr open ports error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('Vultr open ports response: ' . $response_body . ', Status: ' . $response_code);

        return $response_code === 200;
    }
}
