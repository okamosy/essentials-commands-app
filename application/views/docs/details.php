<table class="command-details">
	<tbody>
		<tr>
			<th>Instructions:</th>
			<td id="cmd-<?php echo $command->tid.'-instr'; ?>" class="editarea"><?php echo $command->instr; ?></td>
		</tr>
		<tr>
			<th>Permissions:</th>
			<td>
				<?php if($is_logged_in) : ?>
				<p><button class="add-permission button" id="cmd-<?php echo $command->tid; ?>">Add New Permission</button></p>
				<?php endif; ?>
				<table class="command-permissions">
				<?php foreach($permissions->result() as $permission) : ?>
					<tr>
						<?php if($is_logged_in) : ?>
						<td><?php echo img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'perm-'.$permission->pid)); ?>
						<?php endif; ?>
						<td id="perm-<?php echo $permission->pid.'-perm'; ?>" class="editable"><?php echo htmlspecialchars($permission->perm); ?></td>
						<td id="perm-<?php echo $permission->pid.'-pdesc'; ?>" class="editable"><?php echo htmlspecialchars($permission->pdesc); 
?></td>
					</tr>
				<?php endforeach; ?>
				</table>
			</td>
		</tr>
	</tbody>
</table>
