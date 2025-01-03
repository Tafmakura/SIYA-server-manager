<?php

namespace Siya\Integrations\ServerManagers\Runcloud;

use Siya\Interfaces\ServerManager;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class Runcloud /*implements ServerManager*/ {
    private $api_key;
    private $api_endpoint = 'https://manage.runcloud.io/api/v3';

    public function __construct() {
        $this->api_key = get_option('runcloud_api_key');
    }

    public function setup(): RuncloudSetup {
        return new RuncloudSetup();
    }

    public function create_server_in_server_manager(
        string $server_name,
        string $ipAddress,
        string $webServerType,
        string $installationType,
        ?string $provider = null
    ) {
        if (empty($ipAddress) || empty($webServerType) || empty($installationType)) {
            throw new \InvalidArgumentException('IP Address, Web Server Type and Installation Type are required');
        }

        $args = [
            'name' => $server_name,
            'ipAddress' => $ipAddress,
            'webServerType' => $webServerType,
            'installationType' => $installationType
        ];

        if (!empty($provider)) {
            $args['provider'] = $provider;
        }

        error_log('RunCloud API Request Body: ' . json_encode($args, JSON_PRETTY_PRINT));

        $response = wp_remote_post(
            $this->api_endpoint . '/servers',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($args)
            ]
        );

        $status_code = wp_remote_retrieve_response_code($response); 
        $response_body = wp_remote_retrieve_body($response);
        
        // WP Error resulting in failure to send API Request
        if (is_wp_error($response)) {
            error_log('RunCloud API Error: ' . $response->get_error_message());
            return [
                'status' => $status_code,
                'body' => $response_body,
                'error' => $response->get_error_message()
            ];
        }

        error_log('RunCloud API Response Body: ' . var_export(json_decode($response_body, true), true ));

        // Error due to failed API requests with failed response 
        if ($status_code !== 201 && $status_code !== 200) {
            return [
                'status' => $status_code,
                'body' => $response_body,
                'error' => 'Failed API Request'
            ];
        }

        // Successful API response 
        return [
            'status' => $status_code,
            'body' => $response_body
        ];
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
            error_log('RunCloud Script Fetch Error: ' . $script_response->get_error_message());
            return new \WP_Error('script_fetch_failed', 'Failed to get installation script: ' . $script_response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($script_response);
        $script_body = wp_remote_retrieve_body($script_response);
        $script_data = json_decode($script_body, true);

        error_log('RunCloud Installation Script Response Status: ' . $response_code);
        error_log('RunCloud Installation Script Response: ' . $script_body);

        if ($response_code !== 200) {
            return new \WP_Error('invalid_response', 'Invalid response code from RunCloud: ' . $response_code);
        }

        if (!is_array($script_data) || !isset($script_data['data']) || !isset($script_data['data']['script'])) {
            return new \WP_Error('invalid_script', 'Invalid installation script format received from RunCloud', array(
                'response' => $script_data
            ));
        }

        if (empty($script_data['data']['script'])) {
            return new \WP_Error('empty_script', 'Empty installation script received from RunCloud');
        }

        try {
            // Initialize SSH connection
            $ssh = new SSH2($ipAddress, 22);
            
            // Get SSH credentials from WordPress options
            $ssh_username = get_option('server_ssh_username', 'root');
            $ssh_key_path = get_option('server_ssh_private_key_path');
            
            if (empty($ssh_key_path)) {
                $ssh_password = get_option('server_ssh_password');
                if (!$ssh->login($ssh_username, $ssh_password)) {
                    return new \WP_Error('ssh_auth_failed', 'SSH authentication failed');
                }
            } else {
                $key = PublicKeyLoader::load(file_get_contents($ssh_key_path));
                if (!$ssh->login($ssh_username, $key)) {
                    return new \WP_Error('ssh_key_auth_failed', 'SSH key authentication failed');
                }
            }

            // Execute the installation script
            $result = $ssh->exec($script_data['data']['script']);
            
            // Log the execution result
            error_log('RunCloud Installation Script Result: ' . $result);
            
            if ($ssh->getExitStatus() !== 0) {
                return new \WP_Error(
                    'script_execution_failed',
                    'Installation script execution failed',
                    array('output' => $result)
                );
            }

            return true;

        } catch (\Exception $e) {
            return new \WP_Error(
                'ssh_connection_failed',
                'Failed to establish SSH connection: ' . $e->getMessage()
            );
        }
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
