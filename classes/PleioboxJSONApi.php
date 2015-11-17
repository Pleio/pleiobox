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
        $site = elgg_get_site_entity();

        $response = array(
            'BaseUrl' => $site->url,
            'Name' => $site->name,
            'User' => $user->username,
            'LogoUrl' => $site->url . 'mod/pleiobox/_graphics/icon.png',
            'BackColor' => '#005dac',
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
            ))
        );

        return $this->sendResponse($response);
    }

    public function getSites() {
        $db_prefix = get_config('dbprefix');

        $sites = array();
        foreach (subsite_manager_get_user_subsites($user->guid) as $site) {
            $plugin_enabled = elgg_get_entities_from_relationship(array(
                'type' => 'object',
                'subtype' => 'plugin',
                'relationship_guid' => $site->guid,
                'relationship' => 'active_plugin',
                'inverse_relationship' => true,
                'site_guid' => $site->guid,
                'joins' => array("JOIN {$db_prefix}objects_entity oe on oe.guid = e.guid"),
                'selects' => array("oe.title", "oe.description"),
                'wheres' => array("oe.title = 'pleiobox'"),
                'limit' => 1
            ));

            if (count($plugin_enabled) === 1) {
                $sites[] = array(
                    'name' => $site->name,
                    'url' => $site->url
                );
            }
        }

        return $this->sendResponse($sites);
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
        $return['is_writable'] = false;
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

            if ($child instanceof ElggGroup) {
                if ($child->file_enable === "no") {
                    continue;
                }
            }

            if ($child instanceof ElggFile) {
                $filename = $child->getFilenameOnFilestore();

                // odt files have no extension on storage
                if (strpos($filename, 'blob')) {
                    $extension = 'odt';
                } else {
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                }

                if (!strpos($attributes['title'], $extension)) {
                    $attributes['title'] = $attributes['title'] . '.' . $extension;
                }

                $attributes['path'] = $attributes['path'] . '/' . $attributes['title'];
                $attributes['is_dir'] = false;
                $attributes['is_share'] = false;
                $attributes['is_writable'] = $child->canEdit();
                $attributes['size'] = 888055;
                $attributes['modified_at'] = date('c', $child->time_updated);
                $attributes['mime_type'] = $child->getMimeType();
                $attributes['revision'] = date('c', $child->time_updated);
                $attributes['shared_with'] = $child->access_id;
                $attributes['icon'] = $extension;
            } else {
                $attributes['is_dir'] = true;
                $attributes['is_share'] = false;

                if ($child instanceof ElggGroup) {
                    $attributes['is_writable'] = false;
                } else {
                    $attributes['is_writable'] = $child->canEdit();
                }

                $attributes['shared_with'] = $child->access_id;
                $attributes['has_keys'] = false;
                $attributes['icon'] = 'folder';
            }

            $return['children'][] = $attributes;
        }

        return $this->sendResponse($return, 200);
    }

    public function createFolder($container_guid, $path = array()) {
        try {

            $browser = new ElggFileBrowser($container_guid);

            if ($folder = $browser->createFolder($path)) {
                $folder = get_entity($folder);
                $return = array();
                $return['is_dir'] = true;
                $return['title'] = $folder->title;
                $return['modified_at'] = date('c', $folder->time_updated);
                $return['is_shared'] = false;
                $return['is_share'] = false;
                $return['is_writable'] = $folder->canEdit();
                $return['has_keys'] = false;
                $return['path'] = $path;
                $return['icon'] = 'folder';

                return $this->sendResponse($return, 200);
            } else {
                return $this->sendResponse(null, 500);
            }
        } catch (Exception $e) {
            return $this->sendResponse(null, 404);
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
