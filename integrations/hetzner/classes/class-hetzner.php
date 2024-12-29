<?php 

namespace Siya\Intergrations\ServerProviders;

use Siya\Interfaces\ServerProvider;

class Hetzner implements ServerProvider {
    private $api_key;
    private $api_endpoint = 'https://api.hetzner.cloud/v1';

    public function __construct() {
        $this->api_key = get_option('hetzner_api_key');
    }

    public function setup() {
        return new HetznerSetup();
    }

    public function provision_server() {
        // Implement server creation via Hetzner API
        $response = wp_remote_post($this->api_endpoint . '/servers', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => 'new-server',
                'server_type' => 'cx11',
                'location' => 'nbg1',
                'image' => 'ubuntu-20.04'
            ])
        ]);

        return wp_remote_retrieve_body($response);
    }

    public function ping_server() {
        // Implement server status check
        $server_id = get_option('server_id');
        return wp_remote_get($this->api_endpoint . '/servers/' . $server_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);
    }

    public function protect_server() {
        // Implement firewall rules
        $server_id = get_option('server_id');
        return wp_remote_post($this->api_endpoint . '/firewalls', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'body' => json_encode([
                'name' => 'protect-server',
                'server' => $server_id
            ])
        ]);
    }

    public function get_server_status() {
        return $this->ping_server();
    }

    public function shutdown_server() {
        $server_id = get_option('server_id');
        return wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/shutdown', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);
    }

    public function poweroff_server() {
        $server_id = get_option('server_id');
        return wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/poweroff', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);
    }

    public function poweron_server() {
        $server_id = get_option('server_id');
        return wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/poweron', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);
    }

    public function backup_server() {
        $server_id = get_option('server_id');
        return wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/create_image', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);
    }

    public function restore_server() {
        $server_id = get_option('server_id');
        $image_id = get_option('backup_image_id');
        return wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/rebuild', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'body' => json_encode([
                'image' => $image_id
            ])
        ]);
    }

    public function destroy_server() {
        $server_id = get_option('server_id');
        return wp_remote_delete($this->api_endpoint . '/servers/' . $server_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);
    }
}