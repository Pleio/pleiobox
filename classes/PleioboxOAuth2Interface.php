<?php

class PleioboxOAuth2Interface {

    public function __construct() {
        global $CONFIG;

        OAuth2\Autoloader::register();

        $storage = new OAuth2\Storage\Pdo(array(
            'dsn' => "mysql:dbname=" . $CONFIG->dbname . ";host=" . $CONFIG->dbhost,
            'username' => $CONFIG->dbuser,
            'password' => $CONFIG->dbpass)
        );

        $server = new OAuth2\Server($storage, array(
            'enforce_state' => false
        ));

        $server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
        $server->addGrantType(new OAuth2\GrantType\RefreshToken($storage));

        $this->server = $server;
    }

    public function getServer() {
        return $this->server;
    }

}