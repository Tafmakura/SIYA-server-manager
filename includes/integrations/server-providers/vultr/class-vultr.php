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

    public function setup_ssh_key($server_name) {
        error_log(sprintf('[SIYA Server Manager][Vultr] Setting up SSH key for server: %s', $server_name));
        
        $public_key = get_option('arsol_ssh_public_key');
        if (empty($public_key)) {
            error_log('[SIYA Server Manager][Vultr] Error: SSH public key not found in settings');
            throw new \Exception('SSH public key not found');
        }

        error_log('[SIYA Server Manager][Vultr] Attempting to add SSH key to Vultr');
        $response = wp_remote_post($this->api_endpoint . '/ssh-keys', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $server_name,
                'ssh_key' => $public_key
            ])
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log(sprintf('[SIYA Server Manager][Vultr] Failed to add SSH key: %s', $error_message));
            throw new \Exception('Failed to add SSH key: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 201) {
            error_log(sprintf('[SIYA Server Manager][Vultr] API Error: Failed to add SSH key. Status: %d, Response: %s', 
                $response_code, 
                $response_body
            ));
            throw new \Exception('Failed to add SSH key. Response code: ' . $response_code);
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);
        $ssh_key_id = $response_data['ssh_key']['id'] ?? null;

        if ($ssh_key_id) {
            error_log(sprintf('[SIYA Server Manager][Vultr] Successfully added SSH key with ID: %s', $ssh_key_id));
        } else {
            error_log('[SIYA Server Manager][Vultr] Warning: SSH key added but no ID returned');
        }

        return $ssh_key_id;
    }

    public function provision_server($server_name, $server_plan, $server_region = 'ewr', $server_image = 2465) {
        error_log(sprintf('[SIYA Server Manager] Vultr: Starting server provisioning with params:%sName: %s%sPlan: %s%sRegion: %s%sImage: %s', 
            PHP_EOL, $server_name, PHP_EOL, $server_plan, PHP_EOL, $server_region, PHP_EOL, $server_image
        ));

        if (empty($server_name)) {
            throw new \Exception('Server name required');
        }

        if (empty($server_plan)) {
            throw new \Exception('Server plan required');
        }

        // Add SSH key before creating server
        $ssh_key_id = $this->setup_ssh_key($server_name);

        error_log(sprintf('[SIYA Server Manager][Vultr] Creating user with username: %s', $server_name));

        $user_script = sprintf(
            "#!/bin/bash\n" .
            "echo '[SIYA Server Manager][Vultr] Creating user: %s'\n" .
            "useradd -m -s /bin/bash %s\n" .
            "echo '[SIYA Server Manager][Vultr] Creating SSH directory'\n" .
            "mkdir -p /home/%s/.ssh\n" .
            "echo '[SIYA Server Manager][Vultr] Copying SSH key'\n" .
            "echo \"$(cat /root/.ssh/authorized_keys)\" > /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][Vultr] Setting permissions'\n" .
            "chown -R %s:%s /home/%s/.ssh\n" .
            "chmod 700 /home/%s/.ssh\n" .
            "chmod 600 /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][Vultr] User setup completed for: %s'\n",
            $server_name,
            $server_name,
            $server_name,
            $server_name,
            $server_name, $server_name,
            $server_name,
            $server_name,
            $server_name,
            $server_name
        );

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
                'ssh_keys' => [$ssh_key_id], // Add SSH key ID to instance creation
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
        
        return [
            'provisioned_id' => $api_response['instance']['id'] ?? '',
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
}
