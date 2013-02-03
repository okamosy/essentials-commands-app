<div id="main-controls">
	<?php echo anchor('permissions', 'View Permissions Only', array('class' => 'button switch-view')); ?>
	<input type="hidden" id="rid" value="<?php echo !empty($release->rid) ? $release->rid : 0; ?>" />
	<?php if($is_logged_in) : ?>
	<button id="add-command" class="button">Add New Command</button>
	<div id="new-command-form" title="Create New Command">
		<p id="validation-msg"></p>
		<label for="cat">Category</label><input type="text" class="autocomplete" name="cat" id="cat" />
		<label for="trigger">Trigger</label><input type="text" name="trigger" id="trigger" />
		<label for="alias">Alias</label><input type="text" name="alias" id="alias" />
		<label for="desc">Description</label><input type="text" name="desc" id="desc" />
		<label for="syntax">Syntax</label><textarea name="syntax" id="syntax" cols="60" rows="4" placeholder="/[cmd] [options]"></textarea>
		<label for="instr">Command Instructions</label><textarea name="instr" id="instr" placeholder="Enter command instructions here"></textarea>
		<p><button class="add-permission button" id="cmd-x">Add New Permission</button></p>
		<table class="command-permissions">
			<tbody>
			</tbody>
		</table>
	</div>

	<?php
	$release_options = array();
	$rid = $release_list[0]->rid;
	foreach($release_list as $release_ptr) {
	    $release_options[$release_ptr->rid] = $release_ptr->name;
	}
	?>
	<button id="clone-release" class="button">Clone Release</button>
	<div id="new-release-form" title="Clone Release">
	    <p id="release-instructions">Enter a name for the new release and select the source to clone from.</p>
	    <p id="release-validation-msg"></p>
	    <label for="name">Release Name</label><input type="text" name="name" id="name" />
	    <label for="bukkit">Bukkit Version</label><input type="text" name="bukkit" id="bukkit" placeholder="Bukkit Compatibility" />
	    <label for="change_log">Change Log</label><input type="url" name="change_log" id="change_log" placeholder="URL to Essentials Change Log" />
	    <label for="notes">Release Notes</label><textarea name="notes" id="notes" placeholder="Enter Release notes here"></textarea>
	    <label for="source_release">Source Release</label>
	    <?php echo form_dropdown('source_release', $release_options, $rid, 'id="source_release"'); ?>
	</div>
<?php endif; ?>
</div>

<div id="release-details">
    <?php $this->load->view('docs/release_details'); ?>
</div>

<div id="releases">
    <span id="release_label">Select a release: </span>
    <div id="release_selector">
        <?php $this->load->view('docs/release_selector'); ?>
    </div>
</div>

<table id="commands" class="datatable">
	<thead>
		<tr>
			<th>TID</th>
			<th>&nbsp</th>
			<th>Cat</th>
			<th>Trigger</th>
			<th>Alias</th>
			<th>Description</th>
			<th>Syntax</th>
		</tr>
	</thead>
	<tbody>
<!--		--><?php //foreach($commands as $row) : ?>
<!--		<tr>-->
<!--			<td>--><?php //echo $row->tid; ?><!--</td>-->
<!--			<td>-->
<!--				--><?php //echo $is_logged_in ? img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'cmd-'.$row->tid)) : ''; ?>
<!--                <img src="http://essentials3.net/cache/doc/assets/img/round_add.png" class="details-img" alt=""/>-->
<!--			</td>-->
<!--			<td>--><?php //echo $row->cat; ?><!--</td>-->
<!--			<td>--><?php //echo $row->trigger; ?><!--</td>-->
<!--			<td>--><?php //echo $row->alias; ?><!--</td>-->
<!--			<td>--><?php //echo $row->desc; ?><!--</td>-->
<!--			<td>--><?php //echo nl2br(htmlspecialchars($row->syntax)); ?><!--</td>-->
<!--		--><?php //endforeach; ?>
	</tbody>
</table>