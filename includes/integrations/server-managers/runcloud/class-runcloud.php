<?php

namespace Siya\Integrations\ServerManagers\Runcloud;

use Siya\Interfaces\ServerManager;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class Runcloud /*implements ServerManager*/ {
    private $api_key;
    private $api_endpoint = 'https://manage.runcloud.io/api/v3';
    private $ssh_log_file;

    public function __construct() {
        $this->api_key = get_option('runcloud_api_key');
        $this->ssh_log_file = plugin_dir_path(__DIR__) . 'logs/ssh_log.txt'; // Set the log file path
        $this->register_hooks(); // Register hooks
    }

    private function register_hooks() {
        add_action('arsol_finish_server_connection_hook', [$this, 'finish_server_connection']);
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

    public function finish_server_connection($args) {
        $subscription_id = $args['subscription_id'];
        $server_post_id = $args['server_post_id'];
        $ssh_host = $args['ssh_host'];
        $ssh_username = $args['ssh_username'];
        $ssh_private_key = $args['ssh_private_key'];
        $ssh_port = $args['ssh_port'];

        error_log('[SIYA Server Manager][RunCloud] Finishing server connection...');

        // Initialize SSH connection
        try {
            $ssh = new SSH2($ssh_host, $ssh_port);
            $private_key = PublicKeyLoader::load($ssh_private_key);

            if (!$ssh->login($ssh_username, $private_key)) {
                throw new \Exception('SSH login failed in finish_server_connection');
            }

            // Variables for backoff
            $max_attempts = 7; // Maximum number of attempts
            $timeout = 20 * 60; // Timeout after 20 minutes (in seconds)
            $backoff_time = 180; // Initial backoff time (3 minutes)
            $elapsed_time = 0; // Elapsed time

            // Loop with exponential backoff to check for script completion
            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                error_log("[SIYA Server Manager][RunCloud] Checking if installation script is complete (Attempt {$attempt}/{$max_attempts})...");

                // Check if RunCloud is installed by checking version
                $runcloud_version = $ssh->exec('runcloud --version');

                if (!empty($runcloud_version)) {
                    error_log('[SIYA Server Manager][RunCloud] RunCloud version found: ' . $runcloud_version);
                    update_post_meta($server_post_id, 'arsol_server_manager_connection', 'success');
                    return;
                }

                // Check if the script has been running for too long
                if ($elapsed_time >= $timeout) {
                    error_log('[SIYA Server Manager][RunCloud] Timeout reached. RunCloud not found after 20 minutes.');
                    update_post_meta($server_post_id, 'arsol_server_manager_connection', 'failed');
                    return;
                }

                // Exponential backoff
                error_log("[SIYA Server Manager][RunCloud] Backing off for {$backoff_time} seconds...");
                sleep($backoff_time);
                $elapsed_time += $backoff_time;

                // Increase backoff time (exponential backoff)
                $backoff_time = min($backoff_time * 2, 900); // Cap the backoff time at 15 minutes
            }

            // If the loop ends without success, mark the task as failed
            error_log('[SIYA Server Manager][RunCloud] Maximum attempts reached. RunCloud not found.');
            update_post_meta($server_post_id, 'arsol_server_manager_connection', 'failed');

        } catch (\Exception $e) {
            error_log('[SIYA Server Manager][RunCloud] Error during finish server connection: ' . $e->getMessage());
            update_post_meta($server_post_id, 'arsol_server_manager_connection', 'failed');
        }
    }



        public function finish_server_connection_with_check($server_post_id)
    {
        error_log('[SIYA Server Manager][RunCloud] Finishing server connection and checking installation status...');

        try {
            // Retrieve server IP and other metadata
            $server_ip = get_post_meta($server_post_id, 'arsol_server_provisioned_ipv4', true);
            $ssh_private_key = get_option('arsol_global_ssh_private_key');
            $ssh_username = 'root';
            $ssh_port = 22;
            
            // Initialize SSH connection
            $ssh = new SSH2($server_ip, $ssh_port);
            $private_key = PublicKeyLoader::load($ssh_private_key);

            if (!$ssh->login($ssh_username, $private_key)) {
                throw new \Exception('Failed to authenticate using SSH key');
            }

            error_log('[SIYA Server Manager][RunCloud] SSH authentication successful.');

            // Define retry times and max duration
            $max_attempts = 6; // Maximum number of attempts (will run for up to 20 minutes)
            $backoff_times = [180, 240, 300, 360, 420, 900]; // Exponential backoff times (in seconds): 3min, 4min, 5min, 6min, 7min, 15min
            $start_time = time();
            
            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                $current_time = time();
                $elapsed_time = $current_time - $start_time;

                // If elapsed time exceeds 20 minutes, break
                if ($elapsed_time >= 1200) {  // 20 minutes = 1200 seconds
                    error_log('[SIYA Server Manager][RunCloud] Timeout reached. Installation not completed in time.');
                    $this->update_server_post_status($server_post_id, 'failed');
                    return;
                }

                // Log the attempt
                error_log("[SIYA Server Manager][RunCloud] Checking log status attempt {$attempt}...");

                // Check the log file for the script's progress
                $log_check_output = $ssh->exec('tail -n 20 /tmp/runcloud_script.log');

                // Log the output for debugging
                error_log("[SIYA Server Manager][RunCloud] Log output from attempt {$attempt}: {$log_check_output}");

                // Check if installation is complete
                if (strpos($log_check_output, 'Installation Complete') !== false) {
                    error_log("[SIYA Server Manager][RunCloud] Installation completed successfully.");
                    $this->update_server_post_status($server_post_id, 'success');
                    return;
                }

                // Check for any error in the log output
                if (strpos($log_check_output, 'error') !== false) {
                    error_log("[SIYA Server Manager][RunCloud] Installation error detected in log.");
                    $this->update_server_post_status($server_post_id, 'failed');
                    return;
                }

                // Log that the process will retry after a backoff
                if ($attempt < $max_attempts) {
                    $wait_time = $backoff_times[$attempt - 1];
                    error_log("[SIYA Server Manager][RunCloud] Waiting {$wait_time} seconds before retrying...");
                    sleep($wait_time);
                }
            }

            // If we reached here, all attempts failed
            error_log('[SIYA Server Manager][RunCloud] Installation process failed after maximum retries.');
            $this->update_server_post_status($server_post_id, 'failed');

        } catch (\Exception $e) {
            $error_message = 'Failed to finish server connection: ' . $e->getMessage();
            error_log('[SIYA Server Manager][RunCloud] ' . $error_message);
            $this->update_server_post_status($server_post_id, 'failed');
        }
    }

    public function update_server_post_status($server_post_id, $status)
    {
        // Update the post meta for arsol_server_manager_connection with the given status
        update_post_meta($server_post_id, 'arsol_server_manager_connection', $status);
        error_log('[SIYA Server Manager][RunCloud] Server connection status updated to: ' . $status);
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


