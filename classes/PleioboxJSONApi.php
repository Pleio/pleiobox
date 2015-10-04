<?php
class PleioboxJSONApi {

    public function sendResponse($data, $status = 200) {
        if ($status != 200) {
            http_response_code($status);
        }

        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return true;
    }

    public function getUser() {
        $user = elgg_get_logged_in_user_entity();

        $response = array(
            'name' => $user->username,
            'public_key' => '',
            'private_key' => ''
        );

        return $this->sendResponse($response);
    }

    public function getRegisterApp() {
        $user = elgg_get_logged_in_user_entity();

        $sites = array();
        foreach (subsite_manager_get_user_subsites($user->guid) as $site) {
            $sites[] = array(
                'name' => $site->name,
                'url' => $site->url
            );
        }

        $site = elgg_get_site_entity();
        $response = array(
            'BaseUrl' => $site->url,
            'Name' => $site->name,
            'User' => $user->username,
            'BackColor' => '#BDBDBD',
            'FontColor' => '#999999',
            'pin_cert' => '',
            'APIKeys' => array(array(
                'Name' => 'LocalBox iOS',
                'Key' => 'testclient',
                'Secret' => 'testpass'

            ), array(
                'Name' => 'Localbox Android',
                'Key' => 'testclient',
                'Secret' => 'testpass'
            )),
            'Sites' => $sites
        );

        return $this->sendResponse($response);
    }

    public function getFile($container_guid, $path = array()) {
        try {
            $browser = new ElggFileBrowser($container_guid);
            $browser->getFile($path);
        } catch(Exception $e) {
            return $this->sendResponse(null, 404);
        }
    }

    public function createFile($container_guid, $path = array()) {
        try {
            $browser = new ElggFileBrowser($container_guid);
            $browser->createFile($path);
            return $this->sendResponse(null, 200);
        } catch(Exception $e) {
            return $this->sendResponse(null, 500);
        }
    }

    public function getInvitations() {
        // Pleio does not support invitations for folders
        return $this->sendResponse(array(), 200);
    }

    public function getMeta($container_guid = 0, $path = array()) {

        $db_prefix = elgg_get_config('dbprefix');
        $user = elgg_get_logged_in_user_entity();

        if ($container_guid === 0) {
            $title = 'Home';
            $parent_path = '/';

            $children = $user->getGroups('', 50);
        } else {
            $parent_path = '/' . $container_guid;

            if (count($path) > 0) {
                $parent_path .= '/' . implode('/', $path);
            }

            $browser = new ElggFileBrowser($container_guid);
            $children = $browser->getFolderContents($path);

            $parent_guid = array_slice($path, -1)[0];
            $parent = get_entity($parent_guid);
            $title = $parent->title;
        }

        $return = array();
        $return['title'] = $title;
        $return['path'] = $parent_path;
        $return['modified_at'] = date('c');
        $return['is_dir'] = true;
        $return['is_share'] = false;
        $return['has_keys'] = false;
        $return['children'] = array();

        foreach ($children as $child) {
            $attributes = array();

            if ($parent_path == '/') {
                $attributes['path'] = $parent_path . $child->guid;
            } else {
                $attributes['path'] = $parent_path . '/' . $child->guid;
            }

            if (isset($child->name)) {
                $attributes['title'] = $child->name;
            } else {
                $attributes['title'] = htmlspecialchars_decode($child->title, ENT_QUOTES);
            }

            if ($child instanceof ElggFile) {
                $attributes['path'] = $attributes['path'] . '/' . $child->originalfilename;
                $attributes['is_dir'] = false;
                $attributes['is_share'] = false;
                $attributes['size'] = 888055;
                $attributes['mime_type'] = $child->getMimeType();
                $attributes['revision'] = $child->time_updated;
                $attributes['icon'] = pathinfo($child->getFilename(), PATHINFO_EXTENSION);
            } else {
                $attributes['is_dir'] = true;
                $attributes['is_share'] = false;
                $attributes['has_keys'] = false;
                $attributes['icon'] = 'folder';
            }

            $return['children'][] = $attributes;
        }

        return $this->sendResponse($return, 200);
    }

    public function createFolder($container_guid, $path = array()) {
        $browser = new ElggFileBrowser($container_guid);

        if ($folder = $browser->createFolder($path)) {
            $return = array();
            $return['is_dir'] = true;
            $return['title'] = $folder->title;
            $return['modified_at'] = date('c', $folder->time_updated);
            $return['is_shared'] = false;
            $return['is_share'] = false;
            $return['has_keys'] = false;
            $return['path'] = $path;
            $return['icon'] = 'folder';

            return $this->sendResponse($return, 200);
        } else {
            return $this->sendResponse(null, 500);
        }
    }

    public function move($from_container_guid, $from_path = array(), $to_container_guid, $to_path = array()) {
        $browser = new ElggFileBrowser($from_container_guid);

        if ($browser->move($from_path, $to_container_guid, $to_path)) {
            return $this->sendResponse(null, 200);
        } else {
            return $this->sendResponse(null, 500);
        }
    }

    public function delete($container_guid, $path = array()) {
        $browser = new ElggFileBrowser($container_guid);

        if (strpos(array_slice($path, -1)[0], '.') !== false) { // it is a file
            $browser->deleteFile($path);
        } else {
            $browser->deleteFolder($path);
        }

        return $this->sendResponse(null);
    }
}