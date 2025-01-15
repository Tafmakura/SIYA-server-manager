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


// Include the Composer autoload to load phpseclib classes
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

// Your existing functions and plugin setup...

/**
 * Test SSH connection to the server
 */
function test_ssh_connection() {
    // Retrieve necessary details from WordPress options
    $ssh_private_key = get_option('arsol_global_ssh_private_key'); // Path or contents of your private key
    $server_ip = '128.140.37.37'; // Your server IP
    $ssh_username = 'root'; // Your SSH username
    $ssh_port = 22; // SSH Port (usually 22, but verify it)
    
    echo 'HELOOOOOOOOOOOOOOOOOOOOOO33333332';

    try {
        // Initialize SSH connection
        $ssh = new SSH2($server_ip, $ssh_port);

        // Load the private key
        $private_key = PublicKeyLoader::load($ssh_private_key);

        // Attempt to authenticate using SSH key
        if (!$ssh->login($ssh_username, $private_key)) {
            throw new \Exception('SSH authentication failed');
        }

        // SSH connection is successful
        $output = $ssh->exec('echo "SSH Test Successful"');

        // Show success message
        echo "<div class='updated notice is-dismissible'><p>Success: SSH connection established to {$server_ip}. Command Output: " . esc_html($output) . "</p></div>";

    } catch (\Exception $e) {
        // If any error occurs, display the error message
        echo "<div class='error notice is-dismissible'><p>Error: " . esc_html($e->getMessage()) . "</p></div>";
    }
}

// Trigger the SSH test function when the plugin is loaded
add_action('admin_notices', 'test_ssh_connection');
