<div id="main-controls">
</div>
<input type="hidden" id="rid" value="<?php echo $release->rid; ?>" />

<div id="release-details">
	<?php $this->load->view('docs/release_details'); ?>
</div>

<div id="releases">
	<span id="release_label">Select a release: </span>
	<div id="release_selector">
		<?php $this->load->view('docs/release_selector'); ?>
	</div>
</div>

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
	</tbody>
</table>
