<?php 

namespace Siya\Interfaces;

interface ServerProvider {
    /**
     * Initial setup for the server provider
     */
    public function setup(): bool;

    /**
     * Create and provision a new server
     */
    public function provision_server(array $config = []): mixed;

    /**
     * Check if server is responding
     */
    public function ping_server(string $server_id): bool;

    /**
     * Enable protection features for the server
     */
    public function protect_server(string $server_id): bool;

    /**
     * Disable protection features for the server
     */
    public function remove_protection_from_server(string $server_id): bool;

    /**
     * Gracefully shutdown the server
     */
    public function shutdown_server(string $server_id): bool;

    /**
     * Force power off the server
     */
    public function poweroff_server(string $server_id): bool;

    /**
     * Power on the server
     */
    public function poweron_server(string $server_id): bool;

    /**
     * Reboot the server
     */
    public function reboot_server(string $server_id): bool;

    /**
     * Permanently delete the server
     */
    public function destroy_server(string $server_id): bool;
}