<?php echo anchor('/', 'View Commands Only', array('class' => 'button switch-view')); ?>
<table id="permissions" class="datatable">
	<thead>
		<tr>
			<th>PID</th>
			<th>TID</th>
			<th>Cat</th>
			<th>Command</th>
			<th>Perm</th>
			<th>Description</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($permissions->result() as $perm) : ?>
		<tr>
			<td><?php echo $perm->pid; ?></td>
			<td><?php echo $perm->tid; ?></td>
			<td id="cmd-<?php echo $perm->tid.'-cat'; ?>"><?php echo htmlspecialchars($perm->cat); ?></td>
			<td id="cmd-<?php echo $perm->tid.'-trigger'; ?>"><?php echo htmlspecialchars($perm->trigger); ?></td>
			<td id="perm-<?php echo $perm->pid.'-perm'; ?>"><?php echo htmlspecialchars($perm->perm); ?></td>
			<td id="perm-<?php echo $perm->pid.'-pdesc'; ?>"><?php echo htmlspecialchars($perm->pdesc); ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
