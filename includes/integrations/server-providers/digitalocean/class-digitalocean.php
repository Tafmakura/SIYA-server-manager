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

    public function provision_server($server_name, $server_plan, $server_region = 'nyc1', $server_image = 'ubuntu-20-04-x64') {
        error_log(sprintf('[SIYA Server Manager][DigitalOcean] Starting server provisioning with params:%sName: %s%sPlan: %s%sRegion: %s%sImage: %s', 
            PHP_EOL, $server_name, PHP_EOL, $server_plan, PHP_EOL, $server_region, PHP_EOL, $server_image
        ));

        if (empty($server_name)) {
            throw new \Exception('Server name required');
        }

        if (empty($server_plan)) {
            throw new \Exception('Server plan required');
        }

        $response = wp_remote_post($this->api_endpoint . '/droplets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $server_name,
                'size' => $server_plan,
                'region' => $server_region,
                'image' => $server_image
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
        error_log('[SIYA Server Manager][DigitalOcean] Raw API Response: ' . json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $server_data = $this->compile_server_return_data($api_response);
        error_log('[SIYA Server Manager] DigitalOcean: Compiled server data: ' . json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $server_data;
    }

    private function map_statuses($raw_status) {
        $status_map = [
            'new' => 'building',
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
        
        return [
            'provisioned_id' => $droplet['id'] ?? '',
            'provisioned_name' => $droplet['name'] ?? '',
            'provisioned_vcpu_count' => $droplet['vcpus'] ?? '',
            'provisioned_memory' => $droplet['memory'] ?? '',
            'provisioned_disk_size' => $droplet['disk'] ?? '',
            'provisioned_ipv4' => $ipv4,
            'provisioned_ipv6' => $ipv6,
            'provisioned_os' => $droplet['image']['distribution'] ?? '',
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
        $response = wp_remote_request($this->api_endpoint . '/droplets/' . $server_provisioned_id, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][DigitalOcean] destroy error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 204;
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
}
