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

    public function provision_server($server_name, $server_plan, $server_region = 'nbg1', $server_image = 'ubuntu-20.04') {
        error_log(sprintf('[SIYA Server Manager][Hetzner] Starting server provisioning with params:%sName: %s%sPlan: %s%sRegion: %s%sImage: %s', 
            PHP_EOL, $server_name, PHP_EOL, $server_plan, PHP_EOL, $server_region, PHP_EOL, $server_image
        ));

        if (empty($server_name)) {
            throw new \Exception('Server name required');
        }

        if (empty($server_plan)) {
            throw new \Exception('Server plan required');
        }

        $response = wp_remote_post($this->api_endpoint . '/servers', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $server_name,
                'server_type' => $server_plan,
                'location' => $server_region,
                'image' => $server_image
            ])
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

        return [
            'provisioned_id' => $api_response['server']['id'] ?? '',
            'provisioned_name' => $api_response['server']['name'] ?? '',
            'provisioned_vcpu_count' => $api_response['server']['server_type']['cores'] ?? '',
            'provisioned_memory' => $api_response['server']['server_type']['memory'] ?? '',
            'provisioned_disk_size' => $api_response['server']['server_type']['disk'] ?? '',
            'provisioned_ipv4' => $api_response['server']['public_net']['ipv4']['ip'] ?? '',
            'provisioned_ipv6' => $api_response['server']['public_net']['ipv6']['ip'] ?? '',
            'provisioned_os' => $api_response['server']['image']['os_flavor'] ?? '',
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

}