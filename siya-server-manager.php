<?php
/**
 * Plugin Name: SIYA Server Manager
 * Description: Server integration and yield augmentation plugin for WooCommerce.
 * Version: 0.0.79
 * Author: Tafadzwa Makura
 * Text Domain: arsol
 */

// Include the Setup class
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-setup.php';

use Siya\Setup;


// Instantiate the Setup class
$siyaServerManager = new Setup();


use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;


function test_ssh_connection() {
    // Retrieve necessary details
    $ssh_private_key = get_option('arsol_global_ssh_private_key'); // or the correct location of your private key
    $ssh_public_key = get_option('arsol_global_ssh_public_key');  // You can use this later for other purposes
    $server_ip = '128.140.37.37'; // Your server IP
    $ssh_username = 'root'; // Your SSH username
    $ssh_port = 22; // SSH Port (usually 22, but verify it)
    
    try {
        // Initialize SSH connection
        $ssh = new SSH2($server_ip, $ssh_port);

        // Load the private key
        $private_key = PublicKeyLoader::load($ssh_private_key);

        // Attempt to authenticate using SSH key
        if (!$ssh->login($ssh_username, $private_key)) {
            throw new \Exception('SSH authentication failed');
        }

        // SSH connection is successful, echo success
        echo "Success: SSH connection established to {$server_ip}.\n";

        // You can also execute a command to verify further
        $output = $ssh->exec('echo "SSH Test Successful"');
        echo "Command Output: " . $output . "\n";

    } catch (\Exception $e) {
        // If any error occurs, display the error message
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Run the SSH test function
test_ssh_connection();