<?php

namespace Siya\Integrations\ServerManagers\Runcloud;

use Siya\Interfaces\ServerManagerSetup;

class RuncloudSetup implements ServerManagerSetup {
    public function register_server_manager(): void {
        // Register the server manager
    }

    public function get_server_manager_name(): string {
        return 'Runcloud';
    }

    public function get_server_manager_settings(): array {
        return [
            'api_key' => 'API Key',
            'api_secret' => 'API Secret',
            'oauth_client_id' => 'OAuth Client ID',
            'oauth_client_secret' => 'OAuth Client Secret'
        ];
    }
}