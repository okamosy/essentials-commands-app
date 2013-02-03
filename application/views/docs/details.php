<table class="command-details">
	<tbody>
		<tr>
			<th>Instructions:</th>
			<td id="cmd-<?php echo $trigger->tid.'-instr'; ?>" class="editarea"><?php echo nl2br(htmlspecialchars($trigger->instr)); ?></td>
		</tr>
		<tr>
			<th>Permissions:</th>
			<td>
				<?php if($is_logged_in) : ?>
				<p><button class="add-permission button" id="cmd-<?php echo $trigger->tid; ?>">Add New Permission</button></p>
				<?php endif; ?>
				<table class="command-permissions">
				<?php foreach($permissions as $permission) : ?>
                    <?php $permission->tid = $trigger->tid; ?>
                    <?php $this->load->view('docs/permission_row', $permission); ?>
				<?php endforeach; ?>
				</table>
			</td>
		</tr>
	</tbody>
</table>
