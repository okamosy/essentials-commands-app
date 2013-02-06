<?php if(empty($release)) : ?>
	<p>There are no published releases at this time.  Please check back later</p>
<?php else: ?>
	<?php if($is_logged_in) : ?>
		<?php echo img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item delete-release', 'id' => 'release-'.$release->rid)); ?>
	<?php endif; ?>
	<label for="release-name" class="release-details-label">Selected Release: </label>
	<span id="release-name" class="release-detail <?php echo $is_logged_in ? 'editable' : ''; ?>"><?php echo $release->name; ?></span>

	<?php if(!empty($release->bukkit) || $is_logged_in) : ?>
		<label for="release-bukkit" class="release-details-label">Bukkit Version: </label>
		<span id="release-bukkit" class="release-detail <?php echo $is_logged_in ? 'editable' : ''; ?>"><?php echo $release->bukkit; ?></span>
	<?php endif; ?>

	<?php if(!empty($release->change_log) || $is_logged_in) : ?>
		<?php if($is_logged_in) : ?>
			<label for="release-change_log" class="release-details-label">Change Log: </label>
		<?php endif; ?>
		<?php $change_log = $is_logged_in ? $release->change_log : anchor_popup($release->change_log, 'View the change log here'); ?>
		<span id="release-change_log" class="release-detail <?php echo $is_logged_in ? 'editable' : ''; ?>"><?php echo $change_log; ?></span>
	<?php endif; ?>

	<?php if($is_logged_in) : ?>
		<label for="release-notes" class="release-details-label">Release Notes: </label>
		<span id="release-notes" class="release-detail <?php echo $is_logged_in ? 'editarea' : ''; ?>"><?php echo nl2br(htmlspecialchars($release->notes)); ?></span>

		<label for="release-status" class="release-details-label">Release State: </label><span id="release-status" class="release-detail <?php echo $is_logged_in ? 'editselect' : ''; ?>"><?php echo $release->status; ?></span>
	<?php endif; ?>
<?php endif; ?>