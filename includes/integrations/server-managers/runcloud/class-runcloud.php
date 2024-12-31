<?php

namespace Siya\Integrations\ServerManagers\Runcloud;

use Siya\Interfaces\ServerManager;

class Runcloud /*implements ServerManager*/ {
    private $api_key;
    private $api_endpoint = 'https://manage.runcloud.io/api/v3';

    public function __construct() {
        $this->api_key = get_option('runcloud_api_key');
    }

    public function setup(): RuncloudSetup {
        return new RuncloudSetup();
    }

    public function deploy_server(
        string $name,
        string $ipAddress,
        string $webServerType,
        string $installationType,
        ?string $provider = null
    ) {
        if (empty($name) || empty($ipAddress) || empty($webServerType) || empty($installationType)) {
            throw new \InvalidArgumentException('Name, IP Address, Web Server Type and Installation Type are required');
        }

        // Step 1: Create server in RunCloud
        $create_response = $this->create_server_in_server_manager($name, $ipAddress, $webServerType, $installationType, $provider);
        
        if (is_wp_error($create_response)) {
            throw new \Exception('Failed to deploy server in RunCloud: ' . $create_response->get_error_message());
        }

        // Step 2: Connect server to server manager
        $connection_response = $this->connect_server_manager_to_provisioned_server($create_response['data']['id'], $ipAddress);
        
        if (is_wp_error($connection_response)) {
            throw new \Exception('Failed to connect to server in RunCloud: ' . $connection_response->get_error_message());
        }

        return $create_response;
    }

    private function create_server_in_server_manager($name, $ipAddress, $webServerType, $installationType, $provider) {
        $args = array(
            'name' => $name,
            'ipAddress' => $ipAddress,
            'webServerType' => $webServerType,
            'installationType' => $installationType
        );

        if (!empty($provider)) {
            $args['provider'] = $provider;
        }

        // Log the API request body
        error_log('RunCloud API Request Body: ' . json_encode($args, JSON_PRETTY_PRINT));

        $response = wp_remote_post(
            $this->api_endpoint . '/servers',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($args)
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        // Log the complete API response
        error_log('RunCloud API Response >>>>>>>>>>>: ' . print_r(array(
            'status_code' => $status_code,
            'headers' => wp_remote_retrieve_headers($response),
            'body' => wp_remote_retrieve_body($response)
        ), true));

        if ($status_code !== 201) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['message']) ? $body['message'] : 'Unknown error';
            return new \WP_Error(
            'deployment_failed', 
            $error_message, 
            array(
                'status' => $status_code,
                'response_body' => $body,
                'raw_response' => wp_remote_retrieve_body($response)
            )
            );
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function connect_server_manager_to_provisioned_server($server_id, $ipAddress) {
        // Get installation script
        $script_response = wp_remote_get(
            $this->api_endpoint . '/servers/' . $server_id . '/installation-script',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                )
            )
        );

        if (is_wp_error($script_response)) {
            return new \WP_Error('script_fetch_failed', 'Failed to get installation script: ' . $script_response->get_error_message());
        }

        $script_data = json_decode(wp_remote_retrieve_body($script_response), true);
        
        if (empty($script_data['data']['script'])) {
            return new \WP_Error('invalid_script', 'Invalid installation script received from RunCloud');
        }

        // TODO: Implement SSH connection and script execution
        // This would involve:
        // 1. Establishing SSH connection to $ipAddress
        // 2. Executing $script_data['data']['script']
        // 3. Handling execution results
        
        return true;
    }

    public function ping_server() {
        $response = wp_remote_get(
            $this->api_endpoint . '/servers/ping',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                ),
            )
        );

        return wp_remote_retrieve_response_code($response) === 200;
    }

    public function get_server_status() {
        // Implement status check logic
    }

    public function disconnect_server() {
        // Implement server disconnection logic
        return true;
    }
}
