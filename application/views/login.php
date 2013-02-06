<article id="login-form">
	<h1>Login</h1>
	<?php echo $errors = validation_errors() ? '<ul class="error">'.$errors.'</ul>' : ''; ?>
	<?php echo isset($login_msg) ? '<div class="error">'.$login_msg.'</div>' : ''; ?>
	<?php echo form_open('login'); ?>
		<input type="text" name="username" id="username" value="<?php echo set_value('username'); ?>" required autofocus />
		<input type="password" name="password" id="password" required />
		<input type="submit" value="Login" class="button" />
	<?php echo form_close(); ?>
</article>