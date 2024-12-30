<?php 

namespace Siya\Integrations\ServerProviders\Hetzner;

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

    public function provision_server() {
        $defaults = array(
            'name' => 'wordpress-' . time(),
            'server_type' => 'cx11',
            'location'    => 'nbg1',
            'image'      => 'ubuntu-20.04'
        );
        $server_config = wp_parse_args($config, $defaults);
        
        $response = wp_remote_post($this->api_endpoint . '/servers', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $name,
                'server_type' => $server_config['server_type'],
                'location' => $server_config['location'],
                'image' => $server_config['image']
            ])
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Failed to provision server: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            throw new \Exception('Failed to provision server. Response code: ' . $response_code);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function ping_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_get($this->api_endpoint . '/servers/' . $server_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner ping error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Hetzner API Error: Ping failed with response code ' . $response_code);
            return false;
        }

        return true;
    }

    public function protect_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . "/servers/{$server_id}/actions/change_protection", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'body' => json_encode([
                'delete' => true,  // Enables deletion protection
                'rebuild' => true  // Optional: also protect from rebuilding
            ])
        ]);
    
        if (is_wp_error($response)) {
            error_log('Hetzner protection error: ' . $response->get_error_message());
            return $response;
        }
    
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Received response code ' . $response_code . ' with body: ' . wp_remote_retrieve_body($response));
        }
    
        return wp_remote_retrieve_body($response);
    }


    public function remove_protection_from_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . "/servers/{$server_id}/actions/change_protection", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'body' => json_encode([
                'delete' => false,  // Disables deletion protection
                'rebuild' => false  // Optional: also remove protection from rebuilding
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner remove protection error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Received response code ' . $response_code . ' with body: ' . wp_remote_retrieve_body($response));
        }

        return wp_remote_retrieve_body($response);
    }

    public function shutdown_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/shutdown', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner shutdown error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Shutdown failed with response code ' . $response_code);
            return false;
        }

        return true;
    }

    public function poweroff_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/poweroff', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner poweroff error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Poweroff failed with response code ' . $response_code);
            return false;
        }

        return true;
    }

    public function poweron_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/poweron', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner poweron error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Poweron failed with response code ' . $response_code);
            return false;
        }

        return true;
    }

    public function destroy_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_delete($this->api_endpoint . '/servers/' . $server_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner destroy error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Hetzner API Error: Destroy failed with response code ' . $response_code);
            return false;
        }

        return true;
    }

    public function reboot_server() {
        $server_id = get_option('server_id');
        $response = wp_remote_post($this->api_endpoint . '/servers/' . $server_id . '/actions/reboot', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Hetzner reboot error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            error_log('Hetzner API Error: Reboot failed with response code ' . $response_code);
            return false;
        }

        return true;
    }

}