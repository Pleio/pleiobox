<?php
/**
* Pleiobox
*
* @package pleiobox
* @author Stichting Pleio
* @link https://www.pleio.nl
*/

require_once(dirname(__FILE__) . "/../../vendor/autoload.php");

function pleiobox_init() {
    elgg_register_page_handler("oauth", "pleiobox_oauth_page_handler");
    elgg_register_page_handler("lox_api", "pleiobox_lox_api_page_handler");
}

elgg_register_event_handler('init', 'system', 'pleiobox_init');

function pleiobox_oauth_page_handler($url) {
    $oauth = new PleioboxOAuth2();
    $server = $oauth->getServer();

    $request = OAuth2\Request::createFromGlobals();
    $response = new OAuth2\Response();

    if ($url[0] != "v2") {
        return false;
    }

    switch ($url[1]) {
        case "token":
            $request = OAuth2\Request::createFromGlobals();
            $response = new OAuth2\Response();

            $server->handleTokenRequest($request)->send();
            return true;
            break;
    }

    return false;
}

function pleiobox_parse_path($path) {
    $path = str_replace('//','/', $path);
    return array_slice(explode('/', $path), 1);
}

function pleiobox_is_authorized() {
    // only enable for development purposes as this occurs in a XSS vulnerability in production.
    // if (elgg_is_logged_in()) {
    //     return true;
    // }

    $oauth = new PleioboxOAuth2();
    $server = $oauth->getServer();

    if (!$server->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
        http_response_code(403);
        return false;
    }

    $token = $server->getAccessTokenData(OAuth2\Request::createFromGlobals());
    $user = get_user($token['user_id']);

    if ($user) {
        login($user);
        return true;
    }

    return false;
}

function pleiobox_lox_api_page_handler($url) {

    // authorize request to API
    if (!pleiobox_is_authorized()) {
        die;
    }

    $api = new PleioboxJSONApi();

    switch ($url[0]) {
        case "register_app":
            $api->getRegisterApp();
            break;
        case "sites":
            $api->getSites();
            break;
        case "files":
            if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                $api->getFile($url[1], array_slice($url, 2));
            } else {
                $api->createFile($url[1], array_slice($url, 2));
            }

            break;
        case "meta":
            if (isset($url[1])) {
                $container_guid = $url[1];
            } else {
                $container_guid = 0;
            }

            $api->getMeta($container_guid, array_slice($url,2));
            break;
        case "operations":

            if ($_SERVER['REQUEST_METHOD'] != 'POST') {
                exit;
            }

            switch ($url[1]) {
                case "create_folder":
                    $path = pleiobox_parse_path(get_input('path'));
                    $api->createFolder($path[0], array_slice($path, 1));
                    break;
                case "move":
                    $from_path = pleiobox_parse_path(get_input('from_path'));
                    $to_path = pleiobox_parse_path(get_input('to_path'));
                    $api->move($from_path[0], array_slice($from_path, 1), $to_path[0], array_slice($to_path, 1));
                case "delete":
                    $path = pleiobox_parse_path(get_input('path'));
                    $api->delete($path[0], array_slice($path, 1));
            }
            break;
        case "invitations":
            $api->getInvitations();
            break;
        case "user":
            $api->getUser();
            break;
    }

    return true;
}
