<?php

elgg_load_js('jquery-19');
elgg_load_js('jquery-noconflict');
elgg_load_js('bootstrap');

elgg_load_css('bootstrap');
elgg_load_css('pleiobox');

$page_owner = elgg_get_page_owner_entity();
$title_text = elgg_echo("file:user", array($page_owner->name));

$params = array(
    "title" => $title_text,
);

if ($page_owner instanceof ElggGroup) {
    $params['filter'] = false;
} else {
    if($page_owner->getGUID() == elgg_get_logged_in_user_guid()){
        $params["filter_context"] = "mine";
    } else {
        $params["filter_context"] = $page_owner->username;
    }
}

$data = array(
    'accessIds' => get_write_access_array(),
    'folderNames' => array(28712902 => 'Informatie voor groepsbeheerders')
);

$params['content'] = "<script> var _appData = " . json_encode($data) . "; </script>";
$params['content'] .= "<div id=\"pleiobox\"></div>";
$params['content'] .= "<script src=\"/mod/pleiobox/js/site.js\"></script>";

echo elgg_view_page($title_text, elgg_view_layout("content", $params));