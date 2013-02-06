<?php
$release_radios = '';
$select_options = array('0' => '');
$promoted_options = array();
$rid = isset($rid) ? $rid : $release_list[0]->rid;

foreach($release_list as $release_ptr) {
    if($release_ptr->status == ESS_PUBLISHED) {
        $select_options[$release_ptr->rid] = $release_ptr->name;
    }
    elseif($release_ptr->status == ESS_UNPUBLISHED) {
        $select_options[$release_ptr->rid] = $release_ptr->name.' (unpublished)';
    }
    else {
        $id = 'release-'.$release_ptr->rid;
	    $selected = FALSE;
	    if($rid == 0 && $release_ptr->status == ESS_DEFAULT || $rid == $release_ptr->rid) {
		    $selected = TRUE;
	    }
        $release_radios .= form_radio('release', $release_ptr->rid, $selected, 'id="'.$id.'"') . ' ' . form_label($release_ptr->name, $id);
    }
}

// If there are no default/promoted options, then we should use the selection options as radio buttons
if(empty($release_radios)) {
	$selected = TRUE;
	foreach($select_options as $rid => $name) {
		if($rid == 0) {
			continue;
		}
		$id = 'release-'.$rid;
		echo form_radio('release', $rid, $selected, 'id="'.$id.'"') . ' ' .form_label($name, $id);
		$selected = FALSE;
	}
}
else {
	echo $release_radios . ((count($select_options) > 1) ? form_dropdown('published_releases', $select_options, $rid, 'id="published_releases"') : '');
}
?>
