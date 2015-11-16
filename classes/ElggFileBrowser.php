<?php

class ElggFileBrowser {

    function __construct($container_guid) {
        $container = get_entity($container_guid);

        if ($container instanceof ElggUser) {
            $this->container = $container;
        } elseif ($container instanceof ElggGroup) {
            $this->container = $container;
        } else {
            throw new Exception('Invalid container');
        }
    }

    public function getFolderContents($path = array()) {
        $db_prefix = elgg_get_config("dbprefix");

        $options = array(
            'type' => 'object',
            'subtype' => 'file',
            'container_guid' => $this->container->guid,
            'limit' => false,
            'joins' => "JOIN {$db_prefix}objects_entity oe ON e.guid = oe.guid",
            'order_by' => 'oe.title ASC'
        );

        if (count($path) === 0) {
            $parent_guid = 0;
            $options['wheres'] = "NOT EXISTS (
                    SELECT 1 FROM {$db_prefix}entity_relationships r
                    WHERE r.guid_two = e.guid AND
                    r.relationship = '" . FILE_TOOLS_RELATIONSHIP . "')";
            $files = elgg_get_entities($options);
        } else {
            $parent_guid = array_slice($path, -1)[0];
            $options['relationship'] = FILE_TOOLS_RELATIONSHIP;
            $options['relationship_guid'] = $parent_guid;
            $files = elgg_get_entities_from_relationship($options);
        }

        $options = array(
            'type' => 'object',
            'subtype' => 'folder',
            'container_guid' => $this->container->guid,
            'limit' => false,
            'metadata_name_value_pairs' => array(array(
                'name' => 'parent_guid',
                'value' => $parent_guid
            )),
            'joins' => "JOIN {$db_prefix}objects_entity oe ON e.guid = oe.guid",
            'order_by' => 'oe.title ASC'
        );

        $folders = elgg_get_entities_from_metadata($options);

        return array_merge($folders, $files);
    }

    public function createFolder($path = array(), $access_id = 0) {

        if (count($path) < 1) {
            throw new Exception('Path length must be at least one.');
        }

        if (count($path) == 1) {
            $parent = $this->container;
        } else {
            $parent_guid = array_slice($path, -2)[0];
            $parent = get_entity($parent_guid);
        }

        if (!$parent | !$parent->canWriteToContainer()) {
            return false;
        }

        $folder = new ElggObject();
        $folder->subtype = 'folder';
        $folder->title = array_slice($path, -1)[0];

        if ($parent instanceof ElggObject) { // lower level folder
            $folder->container_guid = $parent->container_guid;
            $folder->parent_guid = $parent->guid;
        } elseif ($parent instanceof ElggGroup) { // top level folder
            $folder->container_guid = $parent->guid;
            $folder->parent_guid = 0;
        }

        if (!$access_id) {
            if ($parent instanceof ElggObject) {
                $folder->access_id = $parent->access_id;
            } elseif ($parent instanceof ElggGroup) {
                $folder->access_id = $parent->group_acl;
            }
        } else {
            $folder->access_id = $access_id;
        }

        return $folder->save();
    }

    public function deleteFolder($path = array()) {
        $folder_guid = array_slice($path, -1)[0];
        $folder = get_entity($folder_guid);

        if (!$folder instanceof ElggObject) {
            return false;
        }

        if (!$folder->canEdit()) {
            return false;
        }

        $contents = $this->getFolderContents($path);
        foreach ($contents as $content) {
            $subtype = $content->getSubtype();
            if ($subtype == 'folder') {
                $this->deleteFolder(array_merge($path, array($content->guid)));
            } elseif ($subtype == 'file') {
                $content->delete();
            }
        }

        $folder->delete();
    }

    public function createFile($path = array(), $filename = "", $filestream = false, $access_id = 0) {

        if (!isset($filename)) {
            if (count($path) < 1) {
                throw new Exception('Path length must be at least one.');
            }
            if (count($path) == 1) {
                $parent = $this->container;
            } else {
                $parent_guid = array_slice($path, -2)[0];
                $parent = get_entity($parent_guid);
            }

            $filename = array_slice($path, -1)[0];
        } else {
            if (count($path) == 0) {
                $parent = $this->container;
            } else {
                $parent_guid = array_slice($path, -1)[0];
                $parent = get_entity($parent_guid);
            }
        }

        if (!$parent | !$parent->canWriteToContainer()) {
            return false;
        }

        if (!$access_id) {
            if ($parent instanceof ElggObject) { // lower level folder
                $access_id = $parent->access_id;
            } elseif ($parent instanceof ElggGroup) { // top level folder
                $access_id = $parent->group_acl;
            } else {
                throw new Exception('Invalid container.');
            }
        }

        $file = new FilePluginFile();
        $file->subtype = "file";
        $file->title = $filename;
        $file->access_id = $access_id;
        $file->container_guid = $this->container->guid;

        $filestorename = elgg_strtolower(time() . $filename);
        $file->setFilename("file/" . $filestorename);
        $file->originalfilename = $filename;

        if (!isset($filestream)) {
            $input = fopen("php://input", "r");
            file_put_contents($file->getFilenameOnFilestore(), $input);
        } else {
            file_put_contents($file->getFilenameOnFilestore(), $filestream);
        }

        $mime_type = ElggFile::detectMimeType($file->getFilenameOnFilestore(), mime_content_type($filename));
        $file->setMimeType($mime_type);
        $file->simpletype = file_get_simple_type($mime_type);

        $file->save();

        if ($file->simpletype == "image") {
            $file->icontime = time();

            $thumbnail = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 60, 60, true);
            if ($thumbnail) {
                $thumb = new ElggFile();
                $thumb->setMimeType($_FILES['upload']['type']);

                $thumb->setFilename($prefix."thumb".$filestorename);
                $thumb->open("write");
                $thumb->write($thumbnail);
                $thumb->close();

                $file->thumbnail = $prefix."thumb".$filestorename;
                unset($thumbnail);
            }

            $thumbsmall = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 153, 153, true);
            if ($thumbsmall) {
                $thumb->setFilename($prefix."smallthumb".$filestorename);
                $thumb->open("write");
                $thumb->write($thumbsmall);
                $thumb->close();
                $file->smallthumb = $prefix."smallthumb".$filestorename;
                unset($thumbsmall);
            }

            $thumblarge = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 600, 600, false);
            if ($thumblarge) {
                $thumb->setFilename($prefix."largethumb".$filestorename);
                $thumb->open("write");
                $thumb->write($thumblarge);
                $thumb->close();
                $file->largethumb = $prefix."largethumb".$filestorename;
                unset($thumblarge);
            }
        }

        if ($parent != $this->container && $parent instanceof ElggObject) {
            add_entity_relationship($parent->guid, FILE_TOOLS_RELATIONSHIP, $file->guid);
        }
    }

    public function getFile($path = array()) {
        $container_guid = $this->container->guid;

        $file_guid = array_slice($path, -2)[0];
        $file = get_entity($file_guid);

        if (!isset($file) | !$file instanceof ElggFile) {
            throw new Exception('Could not find this specific file.');
        }

        $mime = $file->getMimeType();
        if (!$mime) {
            $mime = "application/octet-stream";
        }

        $filename = $file->originalfilename;
        header("Content-Type: $mime");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Length: " . filesize($file->getFilenameOnFilestore()));

        ob_clean();
        flush();
        readfile($file->getFilenameOnFilestore());
        exit;
    }

    public function deleteFile($path = array()) {
        $file_guid = array_slice($path, -2)[0];
        $file = get_entity($file_guid);

        if (!$file) {
            throw new Exception('Could not find entity.');
        }

        if (!$file instanceof ElggFile) {
            throw new Exception('This is not an ElggFile object.');
        }

        if (!$file->canEdit()) {
            throw new Exception('No write access to the object.');
        }

        return $file->delete();
    }

}