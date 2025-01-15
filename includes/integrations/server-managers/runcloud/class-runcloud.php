<?php

namespace Siya\Integrations\ServerManagers\Runcloud;

use Siya\Interfaces\ServerManager;
use \Exception;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class Runcloud /*implements ServerManager*/ {
    private $api_key;
    private $api_endpoint = 'https://manage.runcloud.io/api/v3';
    private $ssh_log_file;

    public function __construct() {
        $this->api_key = get_option('runcloud_api_key');
        $this->ssh_log_file = plugin_dir_path(__DIR__) . 'logs/ssh_log.txt'; // Set the log file path
    }

    public function create_server_in_server_manager(
        string $server_name,
        string $ipAddress,
        string $webServerType,
        string $installationType,
        ?string $provider = null
    ) {

        error_log ('Mileston Y1');

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

    public function connect_server_manager_to_provisioned_server($server_post_id) {
        $server_id = get_post_meta($server_post_id, 'arsol_server_deployed_server_id', true);
        //$installation_script = $this->get_installation_script($server_id);

        try {
            // Retrieve necessary details from server post metadata
            $server_ip = get_post_meta($server_post_id, 'arsol_server_provisioned_ipv4', true);
            $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
            $ssh_private_key = get_option('arsol_global_ssh_private_key');
            $ssh_public_key = get_option('arsol_global_ssh_public_key');
            $ssh_host = $server_ip;
            $ssh_username = 'root';
            $ssh_port = 22;

            error_log('[SIYA Server Manager][RunCloud] SSH Private Key: ' . $ssh_private_key);
            error_log('[SIYA Server Manager][RunCloud] SSH Public Key: ' . $ssh_public_key);

            // Initialize SSH connection
            error_log('[SIYA Server Manager][RunCloud] Initializing SSH connection...');
            $ssh = new SSH2($ssh_host, $ssh_port);
            $private_key = PublicKeyLoader::load($ssh_private_key);

            if (!$ssh->login($ssh_username, $private_key)) {
                throw new \Exception('Failed to authenticate using SSH key');
            }

            error_log('[SIYA Server Manager][RunCloud] SSH authentication succeeded.');

            // Test SSH connection with a simple command
            error_log('[SIYA Server Manager][RunCloud] Testing SSH connection with simple command...');
            $test_output = $ssh->exec('echo "SSH Connection Test Successful"');

            if (empty($test_output)) {
                $error_message = 'Test command output is blank';
                error_log('[SIYA Server Manager][RunCloud] ' . $error_message);
                throw new \Exception($error_message);
            }

            error_log('[SIYA Server Manager][RunCloud] Test Command Output: ' . $test_output);

            // Execute the installation script
            error_log('[SIYA Server Manager][RunCloud] Executing installation script...');
            $execution_output = $ssh->exec($installation_script);

            if (empty($execution_output)) {
                $error_message = 'SSH connection timed out during script execution.';
                error_log('[SIYA Server Manager][RunCloud] ' . $error_message);
                throw new \Exception($error_message);
            }

            error_log('[SIYA Server Manager][RunCloud] Installation Script Output: ' . $execution_output);
            error_log('[SIYA Server Manager][RunCloud] Installation script executed successfully.');
            return true;

        } catch (\Exception $e) {
            $error_message = 'Failed to establish SSH connection: ' . $e->getMessage();
            error_log('[SIYA Server Manager][RunCloud] ' . $error_message);
            throw new \Exception($error_message);
        }
    }

    public function get_installation_script($server_id) {
        $script_response = wp_remote_get(
            $this->api_endpoint . '/servers/' . $server_id . '/installationscript',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                )
            )
        );

        if (is_wp_error($script_response)) {
            $error_message = 'Failed to get installation script: ' . $script_response->get_error_message();
            error_log('[SIYA Server Manager][RunCloud] Script Fetch Error: ' . $error_message);
            throw new \Exception($error_message);
        }

        $response_code = wp_remote_retrieve_response_code($script_response);
        $script_body = wp_remote_retrieve_body($script_response);
        $script_data = json_decode($script_body, true);

        error_log('[SIYA Server Manager][RunCloud] Installation Script Response Status: ' . $response_code);
        error_log('[SIYA Server Manager][RunCloud] Installation Script Response: ' . $script_body);

        if ($response_code !== 200) {
            throw new \Exception('Invalid response code from RunCloud: ' . $response_code);
        }

        if (!is_array($script_data) || !isset($script_data['script'])) {
            throw new \Exception('Invalid installation script format received from RunCloud');
        }

        if (empty($script_data['script'])) {
            throw new \Exception('Empty installation script received from RunCloud');
        }

        return $script_data['script'];
    }

    private function attempt_ssh_connection($ssh_host, $ssh_port, $max_attempts = 5) {
        $attempt = 1;
        
        while ($attempt <= $max_attempts) {
            try {
                error_log("SSH Connection attempt {$attempt} of {$max_attempts} to {$ssh_host}:{$ssh_port}");
                
                $ssh_connection = @ssh2_connect($ssh_host, $ssh_port);
                
                if ($ssh_connection) {
                    error_log("SSH Connection successful on attempt {$attempt}");
                    return $ssh_connection;
                }
                
                error_log("SSH Connection failed on attempt {$attempt}");
                $attempt++;
                
                if ($attempt <= $max_attempts) {
                    sleep(3); // Wait 3 seconds before next attempt
                }
                
            } catch (\Exception $e) {
                error_log("SSH Connection error on attempt {$attempt}: " . $e->getMessage());
                $attempt++;
            }
        }
        
        return false;
    }

    private function is_static_ip($ip) {
        // Implement logic to check if the IP is static
        // For now, assume all IPs are static
        return true;
    }

    private function are_ports_open($ip, $ports) {
        foreach ($ports as $port) {
            $connection = @fsockopen($ip, $port);
            if (is_resource($connection)) {
                fclose($connection);
            } else {
                return false;
            }
        }
        return true;
    }

    private function is_openvz_virtualization($server_post_id) {
        $server_virtualization = get_post_meta($server_post_id, 'arsol_server_virtualization', true);
        return $server_virtualization === 'openvz';
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

    public function delete_server($server_id) {
        // Build the full API endpoint URL
        $url = $this->api_endpoint . '/servers/' . $server_id;
    
        // Send the DELETE request to the RunCloud API
        $response = wp_remote_request($url, [
            'method'    => 'DELETE',
            'headers'   => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
        ]);
    
        // Check if there was an error in the request
        if (is_wp_error($response)) {
            error_log('[SIYA Server Manager][RunCloud] Delete error: ' . $response->get_error_message());
            return false; // Return false if there was a request error
        }
    
        // Retrieve the HTTP response code
        $response_code = wp_remote_retrieve_response_code($response);
    
        // Return true if the deletion was successful (HTTP 204)
        if ($response_code === 200) {
            error_log('[SIYA Server Manager][RunCloud] Server deleted successfully.');
            return true;
        }
    
        // Log the error if the deletion failed
        error_log('[SIYA Server Manager][RunCloud] Server deletion failed with response code: ' . $response_code);
        return false; // Return false if the deletion failed
    }
    
}
