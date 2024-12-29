<?php 

Siya\Interfaces;

interface ServerManager {
    public function setup(); // Must Return ServerManagerSetup class instance use > public function setup(): ClassName ; )
    public function deploy_server();
    public function render_settings_page();
    public function connect_server();
    public function ping_server();
    public function get_server_status();
    public function disconnect_server();
}