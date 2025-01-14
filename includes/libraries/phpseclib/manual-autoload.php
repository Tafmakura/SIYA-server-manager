<?php
// Include the main SSH2 and RSA class files
require_once plugin_dir_path(__DIR__) . 'phpseclib/Net/SSH2.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/RSA.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Math/BigInteger.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/Hash.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/Random.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Common/Functions/Strings.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Common/Functions/Objects.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/Common/AsymmetricKey.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/Common/PrivateKey.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/Common/PublicKey.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/RSA/PrivateKey.php';
require_once plugin_dir_path(__DIR__) . 'phpseclib/Crypt/RSA/PublicKey.php';

// Add more class files here if needed
?>