<?php 

namespace Siya\Integrations\ServerProviders\Hetzner;

use Siya\Interfaces\ServerProviderSetup;

class HetznerSetup implements ServerProviderSetup {
    public function register_server_provider(): void {
        // Register the server provider
    }

    public function get_server_provider_name(): string {
        return 'Hetzner';
    }

    public function get_server_provider_settings(): array {
        return [
            'api_key' => 'API Key',
            'api_secret' => 'API Secret',
        ];
    }
}