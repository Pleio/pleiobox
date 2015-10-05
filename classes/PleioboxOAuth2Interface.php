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
            'access_lifetime' => 3600*24*7,
            'enforce_state' => false
        ));

        $server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
        $server->addGrantType(new OAuth2\GrantType\RefreshToken($storage, array(
            'always_issue_new_refresh_token' => true
        )));

        $this->server = $server;
    }

    public function getServer() {
        return $this->server;
    }

}