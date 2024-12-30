<?php 

namespace Siya\Interfaces;

interface ServerProvider {
    public function setup();
    public function provision_server();
    public function ping_server();
    public function protect_server();
    public function remove_protection_from_server();
    public function shutdown_server();
    public function poweroff_server();
    public function poweron_server();
    public function reboot_server();
    public function destroy_server();
}