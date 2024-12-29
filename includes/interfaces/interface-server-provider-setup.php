<?php 

namespace Siya\Interfaces;

interface ServerProviderSetup {
    public function register_server_provider(): void;
    public function get_server_provider_name(): string;
    public function get_server_provider_settings(): array;
}