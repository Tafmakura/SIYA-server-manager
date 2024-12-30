<?php 

namespace Siya\Interfaces;

interface ServerManager {
    public function setup(): ServerManagerSetup; 
    public function deploy_server();
    public function connect_server();
    public function disconnect_server();
    public function ping_server();
    public function get_server_status();
    public function create_server_in_server_manager();
    public function connect_server_manager_to_provisioned_server();
}