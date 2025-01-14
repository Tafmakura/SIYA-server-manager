<?php

namespace Siya\Integrations\ServerProviders;

use Siya\Interfaces\ServerProvider;

class DigitalOcean /*implements ServerProvider*/ {
    private $api_key;
    private $api_endpoint = 'https://api.digitalocean.com/v2';

    public function __construct() {
        $this->api_key = get_option('digitalocean_api_key');
    }

    public function setup() {
        return new DigitalOceanSetup();
    }

    public function setup_ssh_key($server_name) {
        error_log(sprintf('[SIYA Server Manager][DigitalOcean] Setting up SSH key for server: %s', $server_name));
        
        $public_key = get_option('arsol_ssh_public_key');
        if (empty($public_key)) {
            error_log('[SIYA Server Manager][DigitalOcean] Error: SSH public key not found in settings');
            throw new \Exception('SSH public key not found');
        }

        error_log('[SIYA Server Manager][DigitalOcean] Attempting to add SSH key to DigitalOcean');
        $response = wp_remote_post($this->api_endpoint . '/account/keys', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $server_name,
                'public_key' => $public_key
            ])
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log(sprintf('[SIYA Server Manager][DigitalOcean] Failed to add SSH key: %s', $error_message));
            throw new \Exception('Failed to add SSH key: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 201) {
            error_log(sprintf('[SIYA Server Manager][DigitalOcean] API Error: Failed to add SSH key. Status: %d, Response: %s', 
                $response_code, 
                $response_body
            ));
            throw new \Exception('Failed to add SSH key. Response code: ' . $response_code);
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);
        $ssh_key_id = $response_data['ssh_key']['id'] ?? null;

        if ($ssh_key_id) {
            error_log(sprintf('[SIYA Server Manager][DigitalOcean] Successfully added SSH key with ID: %s', $ssh_key_id));
        } else {
            error_log('[SIYA Server Manager][DigitalOcean] Warning: SSH key added but no ID returned');
        }

        return $ssh_key_id;
    }

    public function provision_server($server_post_id) {
        // Retrieve necessary information from metadata
        $server_name = get_post_meta($server_post_id, 'arsol_server_post_name', true);
        $server_plan = get_post_meta($server_post_id, 'arsol_server_plan_slug', true);
        $server_region = get_post_meta($server_post_id, 'arsol_server_region_slug', true) ?: 'nyc1';
        $server_image = get_post_meta($server_post_id, 'arsol_server_image_slug', true) ?: 'ubuntu-20-04-x64';
        $ssh_key_id = 'ad:a1:8f:2f:ec:a0:c6:f9:ba:f5:f2:63:d5:4d:8c:d9';

        error_log(sprintf('[SIYA Server Manager][DigitalOcean] Starting server provisioning with params:%sName: %s%sPlan: %s%sRegion: %s%sImage: %s', 
            PHP_EOL, $server_name, PHP_EOL, $server_plan, PHP_EOL, $server_region, PHP_EOL, $server_image
        ));

        if (empty($server_name)) {
            throw new \Exception('Server name required');
        }

        if (empty($server_plan)) {
            throw new \Exception('Server plan required');
        }

        // Setup SSH access
        try {
            $user_script = $this->setup_ssh_access($server_post_id);
        } catch (\Exception $e) {
            error_log('[SIYA Server Manager][DigitalOcean] Error setting up SSH access: ' . $e->getMessage());
            throw new \Exception('Error setting up SSH access: ' . $e->getMessage());
        }

        $server_data = [
            'name' => $server_name,
            'size' => $server_plan,
            'region' => $server_region,
            'image' => $server_image,
            //'user_data' => base64_encode($user_script),
          //  'user_data' => $user_script,
            'ssh_keys' => [$ssh_key_id]
        ];

        $response = wp_remote_post($this->api_endpoint . '/droplets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($server_data)
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
        error_log('[SIYA Server Manager][DigitalOcean] Raw API Response: ' . json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $server_data = $this->compile_server_return_data($api_response);
        error_log('[SIYA Server Manager] DigitalOcean: Compiled server data: ' . json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $server_data;
    }

    private function setup_ssh_access($server_post_id) {
        // Retrieve SSH key and username from server metadata
        $ssh_public_key = get_post_meta($server_post_id, 'arsol_ssh_public_key', true);
        $ssh_username = get_post_meta($server_post_id, 'arsol_ssh_username', true);

        if (empty($ssh_public_key) || empty($ssh_username)) {
            $error_message = 'SSH key or username not found in server metadata';
            error_log('[SIYA Server Manager][DigitalOcean] ' . $error_message);
            throw new \Exception($error_message);
        }

        error_log(sprintf('[SIYA Server Manager][DigitalOcean] Setting up SSH access for user: %s with public key: %s', $ssh_username, $ssh_public_key));

        $user_script = sprintf(
            "#!/bin/bash\n" .
            "echo '[SIYA Server Manager][DigitalOcean] Creating user: %s'\n" .
            "useradd -m -s /bin/bash %s\n" .
            "echo '[SIYA Server Manager][DigitalOcean] Creating SSH directory'\n" .
            "mkdir -p /home/%s/.ssh\n" .
            "echo '[SIYA Server Manager][DigitalOcean] Copying SSH key'\n" .
            "echo \"%s\" > /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][DigitalOcean] Setting permissions'\n" .
            "chown -R %s:%s /home/%s/.ssh\n" .
            "chmod 700 /home/%s/.ssh\n" .
            "chmod 600 /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][DigitalOcean] User setup completed for: %s'\n",
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

        error_log('[SIYA Server Manager][DigitalOcean] SSH access setup script generated successfully.');

        return $user_script;
    }


    private function map_statuses($raw_status) {
        $status_map = [
            'new' => 'starting',
            'active' => 'active',
            'off' => 'off',
            'rebooting' => 'rebooting'
        ];
        $mapped_status = $status_map[$raw_status] ?? $raw_status;
        error_log(sprintf('[SIYA Server Manager] DigitalOcean: Mapping status from "%s" to "%s"', $raw_status, $mapped_status));
        error_log(sprintf('[SIYA Server Manager][DigitalOcean] Full status mapping details:%sFrom: %s%sTo: %s', 
            PHP_EOL, var_export($raw_status, true), 
            PHP_EOL, var_export($mapped_status, true)
        ));
        return $mapped_status;
    }

    public function compile_server_return_data($api_response) {
        $droplet = $api_response['droplet'] ?? [];
        $networks = $droplet['networks'] ?? [];
        
        // Get first available IPv4 and IPv6
        $ipv4 = '';
        $ipv6 = '';
        foreach (($networks['v4'] ?? []) as $network) {
            if (!empty($network['ip_address'])) {
                $ipv4 = $network['ip_address'];
                break;
            }
        }
        foreach (($networks['v6'] ?? []) as $network) {
            if (!empty($network['ip_address'])) {
                $ipv6 = $network['ip_address'];
                break;
            }
        }
        
        $os_name = $droplet['image']['distribution'] ?? '';
        $os_version = $droplet['image']['version'] ?? '';

        return [
            'provisioned_id' => $droplet['id'] ?? '',
            'provisioned_name' => $droplet['name'] ?? '',
            'provisioned_vcpu_count' => $droplet['vcpus'] ?? '',
            'provisioned_memory' => $droplet['memory'] ?? '',
            'provisioned_disk_size' => $droplet['disk'] ?? '',
            'provisioned_ipv4' => $ipv4,
            'provisioned_ipv6' => $ipv6,
            'provisioned_os' => $os_name,
            'provisioned_os_version' => $os_version,
            'provisioned_image_slug' => $droplet['image']['slug'] ?? '',
            'provisioned_region_slug' => $droplet['region']['slug'] ?? '',
            'provisioned_date' => $droplet['created_at'] ?? '',
            'provisioned_root_password' => '',
            'provisioned_remote_status' => $this->map_statuses($droplet['status'] ?? ''),
            'provisioned_remote_raw_status' => $droplet['status'] ?? ''
        ];
    }

    public function ping_server($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/droplets/' . $server_provisioned_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] ping error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }

    public function protect_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . "/droplets/{$server_provisioned_id}/actions", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'type' => 'enable_droplet_deletion_protection'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] protection error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    public function remove_protection_from_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . "/droplets/{$server_provisioned_id}/actions", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'type' => 'disable_droplet_deletion_protection'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] remove protection error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    public function shutdown_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/droplets/' . $server_provisioned_id . '/actions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'type' => 'shutdown'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] shutdown error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    public function poweroff_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/droplets/' . $server_provisioned_id . '/actions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'type' => 'power_off'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] poweroff error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    public function poweron_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/droplets/' . $server_provisioned_id . '/actions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'type' => 'power_on'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] poweron error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    public function upgrade_server() {
        // To be implemented
    }

    public function create_server_snapshot($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/droplets/' . $server_provisioned_id . '/actions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'type' => 'snapshot'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('DigitalOcean snapshot error: ' . $response->get_error_message());
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
            error_log('DigitalOcean get snapshots error: ' . $response->get_error_message());
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
        error_log('[SIYA Server Manager][DigitalOcean] Destroying server with ID: ' . $server_provisioned_id);

        $response = wp_remote_request($this->api_endpoint . '/droplets/' . $server_provisioned_id, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        error_log('[SIYA Server Manager][DigitalOcean] Server destroy response: ' . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] Error destroying server: ' . $response->get_error_message());
            throw new \Exception('Failed to destroy server: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 204) {
            error_log('[SIYA Server Manager][DigitalOcean] Error destroying server. Response code: ' . $response_code);
            throw new \Exception('Failed to destroy server. Response code: ' . $response_code);
        }

        error_log('[SIYA Server Manager][DigitalOcean] Server destroyed successfully.');
        return true;
    }

    public function reboot_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/droplets/' . $server_provisioned_id . '/actions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'type' => 'reboot'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] reboot error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    public function get_server_status($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/droplets/' . $server_provisioned_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('DigitalOcean status error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $api_response = json_decode(wp_remote_retrieve_body($response), true);
        $raw_status = $api_response['droplet']['status'] ?? '';

        return [
            'provisioned_remote_status' => $this->map_statuses($raw_status),
            'provisioned_remote_raw_status' => $raw_status
        ];
    }

    public function get_server_ip($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/droplets/' . $server_provisioned_id, [
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
        $networks = $api_response['droplet']['networks'] ?? [];
        
        $ipv4 = '';
        $ipv6 = '';
        foreach (($networks['v4'] ?? []) as $network) {
            if (!empty($network['ip_address'])) {
                $ipv4 = $network['ip_address'];
                break;
            }
        }
        foreach (($networks['v6'] ?? []) as $network) {
            if (!empty($network['ip_address'])) {
                $ipv6 = $network['ip_address'];
                break;
            }
        }

        return [
            'ipv4' => $ipv4,
            'ipv6' => $ipv6
        ];
    }

    public function open_server_ports($server_provisioned_id) {
        error_log('[SIYA Server Manager][DigitalOcean] Assigning firewall group to server: ' . $server_provisioned_id);

        $firewall_id = 'e08f1e94-778d-4184-97ea-8091b3b64a83'; // Replace with your actual firewall group ID

        $response = wp_remote_post($this->api_endpoint . '/firewalls/' . $firewall_id . '/droplets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'droplet_ids' => [$server_provisioned_id]
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('DigitalOcean open ports error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('DigitalOcean open ports response: ' . $response_body . ', Status: ' . $response_code);

        return $response_code === 204;
    }
}
