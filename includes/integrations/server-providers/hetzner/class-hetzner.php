<?php 

namespace Siya\Integrations\ServerProviders;

use Siya\Interfaces\ServerProvider;

class Hetzner /*implements ServerProvider*/ {
    private $api_key;
    private $api_endpoint = 'https://api.hetzner.cloud/v1';

    public function __construct() {
        $this->api_key = get_option('hetzner_api_key');
    }

    public function setup() {
        return new HetznerSetup();
    }

    public function setup_ssh_key($server_name) {
        error_log(sprintf('[SIYA Server Manager][Hetzner] Setting up SSH key for server: %s', $server_name));
        
        $public_key = get_option('arsol_ssh_public_key');
        if (empty($public_key)) {
            error_log('[SIYA Server Manager][Hetzner] Error: SSH public key not found in settings');
            throw new \Exception('SSH public key not found');
        }

        error_log('[SIYA Server Manager][Hetzner] Attempting to add SSH key to Hetzner');
        $response = wp_remote_post($this->api_endpoint . '/ssh-keys', [
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
            error_log(sprintf('[SIYA Server Manager][Hetzner] Failed to add SSH key: %s', $error_message));
            throw new \Exception('Failed to add SSH key: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 201) {
            error_log(sprintf('[SIYA Server Manager][Hetzner] API Error: Failed to add SSH key. Status: %d, Response: %s', 
                $response_code, 
                $response_body
            ));
            throw new \Exception('Failed to add SSH key. Response code: ' . $response_code);
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);
        $ssh_key_id = $response_data['ssh_key']['id'] ?? null;

        if ($ssh_key_id) {
            error_log(sprintf('[SIYA Server Manager][Hetzner] Successfully added SSH key with ID: %s', $ssh_key_id));
        } else {
            error_log('[SIYA Server Manager][Hetzner] Warning: SSH key added but no ID returned');
        }

        return $ssh_key_id;
    }

    public function provision_server($server_post_id) {
   
        // Retrieve necessary information from metadata
        $server_name = get_post_meta($server_post_id, 'arsol_server_post_name', true);
        $server_plan = get_post_meta($server_post_id, 'arsol_server_plan_slug', true);
        $server_region = get_post_meta($server_post_id, 'arsol_server_region_slug', true) ?: 'nbg1';
        $server_image = get_post_meta($server_post_id, 'arsol_server_image_slug', true) ?: 161547269; // Default must support Runcloud
        $ssh_key_id = 26338453;

        error_log(sprintf('[SIYA Server Manager][Hetzner] Starting server provisioning with params:%sName: %s%sPlan: %s%sRegion: %s%sImage: %s', 
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
            error_log('[SIYA Server Manager][Hetzner] Error setting up SSH access: ' . $e->getMessage());
            throw new \Exception('Error setting up SSH access: ' . $e->getMessage());
        }


        $server_data = [
            'name' => $server_name,
            'server_type' => $server_plan,
            'location' => $server_region,
            'image' => $server_image,
           // 'user_data' => base64_encode($user_script),
            'ssh_keys' => [$ssh_key_id]
        ];

        $response = wp_remote_post($this->api_endpoint . '/servers', [
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
        if ($response_code !== 201) {
            throw new \Exception('Failed to provision server. Response code: ' . $response_code . ', Body: ' . $response_body);
        }

        $api_response = json_decode($response_body, true);
        error_log('[SIYA Server Manager] Hetzner: Raw API Response: ' . json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Compile server return data
        $server_data = $this->compile_server_return_data($api_response);
        error_log('[SIYA Server Manager] Hetzner: Compiled server data: ' . json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Return the compiled data
        return $server_data;
    }

    private function setup_ssh_access($server_post_id) {
        // Retrieve SSH key and username from server metadata
        $ssh_public_key = get_post_meta($server_post_id, 'arsol_ssh_public_key', true);
        $ssh_username = get_post_meta($server_post_id, 'arsol_ssh_username', true);

        if (empty($ssh_public_key) || empty($ssh_username)) {
            $error_message = 'SSH key or username not found in server metadata';
            error_log('[SIYA Server Manager][Hetzner] ' . $error_message);
            throw new \Exception($error_message);
        }

        error_log(sprintf('[SIYA Server Manager][Hetzner] Setting up SSH access for user: %s with public key: %s', $ssh_username, $ssh_public_key));

        $user_script = sprintf(
            "#!/bin/bash\n" .
            "echo '[SIYA Server Manager][Hetzner] Creating user: %s'\n" .
            "useradd -m -s /bin/bash %s\n" .
            "echo '[SIYA Server Manager][Hetzner] Creating SSH directory'\n" .
            "mkdir -p /home/%s/.ssh\n" .
            "echo '[SIYA Server Manager][Hetzner] Copying SSH key'\n" .
            "echo \"%s\" > /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][Hetzner] Setting permissions'\n" .
            "chown -R %s:%s /home/%s/.ssh\n" .
            "chmod 700 /home/%s/.ssh\n" .
            "chmod 600 /home/%s/.ssh/authorized_keys\n" .
            "echo '[SIYA Server Manager][Hetzner] User setup completed for: %s'\n",
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

        error_log('[SIYA Server Manager][Hetzner] SSH access setup script generated successfully.');

        return $user_script;
    }

    private function map_statuses($raw_status) {
        $status_map = [
            'initializing' => 'starting',
            'starting' => 'starting',
            'running' => 'active',
            'stopped' => 'off',
            'stopping' => 'off',
            'rebooting' => 'rebooting'
        ];
        $mapped_status = $status_map[$raw_status] ?? $raw_status;
        error_log(sprintf('[SIYA Server Manager][Hetzner] Full status mapping details:%sFrom: %s%sTo: %s', 
            PHP_EOL, var_export($raw_status, true), 
            PHP_EOL, var_export($mapped_status, true)
        ));
        return $mapped_status;
    }

    public function compile_server_return_data($api_response) {
        
        error_log(var_export($api_response, true)); // DELETE THIS IN PRODUCTION

        $raw_status = $api_response['server']['status'] ?? '';
        $os_name = $api_response['server']['image']['os_flavor'] ?? '';
        $os_version = $api_response['server']['image']['os_version'] ?? '';

        return [
            'provisioned_id' => $api_response['server']['id'] ?? '',
            'provisioned_name' => $api_response['server']['name'] ?? '',
            'provisioned_vcpu_count' => $api_response['server']['server_type']['cores'] ?? '',
            'provisioned_memory' => $api_response['server']['server_type']['memory'] ?? '',
            'provisioned_disk_size' => $api_response['server']['server_type']['disk'] ?? '',
            'provisioned_ipv4' => $api_response['server']['public_net']['ipv4']['ip'] ?? '',
            'provisioned_ipv6' => $api_response['server']['public_net']['ipv6']['ip'] ?? '',
            'provisioned_os' => $os_name,
            'provisioned_os_version' => $os_version,
            'provisioned_image_slug' => $api_response['server']['image']['name'] ?? '',
            'provisioned_region_slug' => $api_response['server']['datacenter']['location']['name'] ?? '',
            'provisioned_date' => $api_response['server']['created'] ?? '',
            'provisioned_add_ons' => '',
            'provisioned_root_password' => $api_response['root_password'] ?? '',
            'provisioned_remote_status' => $this->map_statuses($raw_status),
            'provisioned_remote_raw_status' => $raw_status
        ];
    }

    public function ping_server($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/servers/' . $server_provisioned_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] ping error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 200) {
            error_log('Hetzner API Error: Ping failed with response code ' . $response_code . ', Body: ' . $response_body);
            return false;
        }

        return true;
    }

    public function protect_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . "/servers/{$server_provisioned_id}/actions/change_protection", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'body' => json_encode([
                'delete' => true,  // Enables deletion protection
                'rebuild' => true  // Optional: also protect from rebuilding
            ])
        ]);
    
        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] protection error: ' . $response->get_error_message());
            return $response;
        }
    
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Protection change failed with response code ' . $response_code . ', Body: ' . $response_body);
        }
    
        return $response_body;
    }


    public function remove_protection_from_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . "/servers/{$server_provisioned_id}/actions/change_protection", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'body' => json_encode([
                'delete' => false,  // Disables deletion protection
                'rebuild' => false  // Optional: also remove protection from rebuilding
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] remove protection error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Remove protection failed with response code ' . $response_code . ', Body: ' . $response_body);
        }

        return $response_body;
    }

    public function shutdown_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_provisioned_id . '/actions/shutdown', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] shutdown error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Shutdown failed with response code ' . $response_code . ', Body: ' . $response_body);
            return false;
        }

        return true;
    }

    public function poweroff_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_provisioned_id . '/actions/poweroff', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] poweroff error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Poweroff failed with response code ' . $response_code . ', Body: ' . $response_body);
            return false;
        }

        return true;
    }

    public function poweron_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_provisioned_id . '/actions/poweron', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] poweron error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Poweron failed with response code ' . $response_code . ', Body: ' . $response_body);
            return false;
        }

        return true;
    }

    public function upgrade_server(){

    }

    public function create_server_snapshot($server_provisioned_id){
        
    }

    public function get_server_snapshots(){
        
    }

    public function rebuild_server_from_snapshot(){
        
    }

    public function get_server_status($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/servers/' . $server_provisioned_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner status error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $api_response = json_decode(wp_remote_retrieve_body($response), true);
        $raw_status = $api_response['server']['status'] ?? '';

        return [
            'provisioned_remote_status' => $this->map_statuses($raw_status),
            'provisioned_remote_raw_status' => $raw_status
        ];
    }

    public function destroy_server($server_provisioned_id) {
        error_log('[SIYA Server Manager][Hetzner] Destroying server with ID: ' . $server_provisioned_id);

        $response = wp_remote_request($this->api_endpoint . '/servers/' . $server_provisioned_id, [
            'method' => 'DELETE',
            'headers' => [
            'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        error_log('[SIYA Server Manager][Hetzner] Server destroy response: ' . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] Error destroying server: ' . $response->get_error_message());
            throw new \Exception('Failed to destroy server: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('[SIYA Server Manager][Hetzner] Error destroying server. Response code: ' . $response_code);
            throw new \Exception('Failed to destroy server. Response code: ' . $response_code);
        }

        error_log('[SIYA Server Manager][Hetzner] Server destroyed successfully.');
        return $response;
    }

    public function reboot_server($server_provisioned_id) {
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_provisioned_id . '/actions/reboot', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][Hetzner] reboot error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Reboot failed with response code ' . $response_code . ', Body: ' . $response_body);
            return false;
        }

        return true;
    }

    public function get_server_ip($server_provisioned_id) {
        $response = wp_remote_get($this->api_endpoint . '/servers/' . $server_provisioned_id, [
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
            'ipv4' => $api_response['server']['public_net']['ipv4']['ip'] ?? '',
            'ipv6' => $api_response['server']['public_net']['ipv6']['ip'] ?? ''
        ];
    }

    public function open_server_ports($server_provisioned_id) {
        error_log('[SIYA Server Manager][Hetzner] Assigning firewall group to server: ' . $server_provisioned_id);

        $firewall_id = 1841021;
        $response = wp_remote_post($this->api_endpoint . '/firewalls/' . $firewall_id . '/actions/apply_to_resources', [
            'headers' => [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
            'apply_to' => [
                [
                'type' => 'server',
                'server' => ['id' => $server_provisioned_id]
                ]
            ]
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner open ports error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('Hetzner open ports response: ' . json_encode($response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ', Status: ' . $response_code);

        return $response_code === 201;
    }

}