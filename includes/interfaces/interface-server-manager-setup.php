<?php 

namespace Siya\Interfaces;

interface ServerManagerSetup {
    public function register_server_manager(): void;
    public function get_server_manager_name(): string;
    public function get_server_manager_settings(): array;
}