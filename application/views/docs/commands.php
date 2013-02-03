<?php echo anchor('permissions', 'View Permissions Only', array('class' => 'button switch-view')); ?>
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
<?php endif; ?>
<table id="commands" class="datatable">
	<thead>
		<tr>
			<th>ID</th>
			<th>&nbsp</th>
			<th>Cat</th>
			<th>Trigger</th>
			<th>Alias</th>
			<th>Description</th>
			<th>Syntax</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($commands->result() as $row) : ?>
		<tr>
			<td><?php echo $row->tid; ?></td>
			<td>
				<?php echo $is_logged_in ? img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'cmd-'.$row->tid)) : ''; ?>
        <img src="http://essentials3.net/cache/doc/assets/img/round_add.png" class="details-img" alt=""/>
			</td>
			<td><?php echo $row->cat; ?></td>
			<td><?php echo $row->trigger; ?></td>
			<td><?php echo $row->alias; ?></td>
			<td><?php echo $row->desc; ?></td>
			<td><?php echo nl2br(htmlspecialchars($row->syntax)); ?></td>
		<?php endforeach; ?>
	</tbody>
</table>