<?php 

namespace Siya\Interfaces;

interface ServerProvider {
    public function setup(); // Must Return ServerProviderSetup class instance use > public function setup(): ClassName ; )
    public function provision_server();
    public function ping_server();
    public function protect_server();
    public function get_server_status();
    public function shutdown_server();
    public function poweroff_server();
    public function poweron_server();
    public function backup_server();
    public function restore_server();
    public function destroy_server();
}