<?php

/**
 * @group Model
 */

class UserModelTest extends CIUnit_TestCase
{
	private $_um;

	public function __construct($name = NULL, array $data = array(), $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
	}
	
	public function setUp()
	{
		parent::tearDown();
		parent::setUp();
		
		$this->CI->load->model('User_model');
		$this->_um = $this->CI->User_model;
		$this->dbfixt('cmd_user');
		
		$users = array();
		foreach($this->cmd_user_fixt as $user) {
			$this->CI->db->update('cmd_user', array('password' => md5($user['password'])), array('uid' => $user['uid']));
			$users[] = (object)$user;
		}

		$this->cmd_user_fixt = $users;
	}

	public function testFetchUserValidCreds()
	{
		$user = $this->cmd_user_fixt[0];
		$expected = (object)array(
			'uid'      => $user->uid,
			'username' => $user->username,
			'email'    => $user->email,
		);

		$this->assertEquals($expected, $this->_um->fetch($user->username, $user->password));
	}

	public function testInabilityFetchUserInvalidCreds() {
		$this->assertFalse($this->_um->fetch('invalid', 'invalid'));
	}

	public function testAbilityChangePasswordValidUID() {
		$user = $this->cmd_user_fixt[0];
		$new_pass = 'NewPassword';
		$expected = (object)array(
			'uid'      => $user->uid,
			'username' => $user->username,
			'email'    => $user->email,
		);

		$this->assertTrue($this->_um->update_password($user->uid, $new_pass));
		$this->assertEquals($expected, $this->_um->fetch($user->username, $new_pass));
	}

	public function testInabilityChangePasswordInvalidUID() {
		$this->assertFalse($this->_um->update_password(1000, 'newPassword'));
	}

	public function testInabilityChangePasswordEmptyNewPassword() {
		$user = $this->cmd_user_fixt[0];
		$new_pass = '';
		$expected = (object)array(
			'uid'      => $user->uid,
			'username' => $user->username,
			'email'    => $user->email,
		);

		$this->assertFalse($this->_um->update_password($user->uid, $new_pass));
		$this->assertFalse($this->_um->fetch($user->username, $new_pass));
		$this->assertEquals($expected, $this->_um->fetch($user->username, $user->password));
	}

	public function testAbilityChangeEmailValidUID() {
		$user = $this->cmd_user_fixt[0];
		$expected = (object)array(
			'uid' => $user->uid,
			'username' => $user->username,
			'email' => 'newemail@email.com',
		);

		$this->assertTrue($this->_um->update_email($user->uid, $expected->email));
		$this->assertEquals($expected, $this->_um->fetch($user->username, $user->password));
	}

	public function testInabilityChangeEmailInvalidUID() {
		$this->assertFalse($this->_um->update_email(1000, 'newemail@email.com'));
	}

	public function testInabilityChangeEmailInvalidEmailAddressFormat() {
		$user = $this->cmd_user_fixt[0];
		$expected = (object)array(
			'uid'      => $user->uid,
			'username' => $user->username,
			'email'    => $user->email,
		);
		
		$this->assertFalse($this->_um->update_email($user->uid, 'bademail'));
		$this->assertEquals($expected, $this->_um->fetch($user->username, $user->password));
	}

	public function testInabilityChangeEmailEmptyEmailAddress() {
		$user = $this->cmd_user_fixt[0];
		$expected = (object)array(
			'uid'      => $user->uid,
			'username' => $user->username,
			'email'    => $user->email,
		);
		
		$this->assertFalse($this->_um->update_email($user->uid, ''));
		$this->assertEquals($expected, $this->_um->fetch($user->username, $user->password));
	}
}
