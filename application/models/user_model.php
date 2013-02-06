<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class User_model extends CI_Model {
	public function fetch($username, $password) {
		$result = $this->db->select('uid, username, email')
						->where(array('username' => $username, 'password' => md5($password)))
						->limit(1)
						->get('cmd_user')
						->row();

		return empty($result) ? FALSE : $result;
	}

	public function update_password($uid, $new_pass) {
		if(empty($new_pass)) {
			return FALSE;
		}
		$this->db->update('cmd_user', array('password' => md5($new_pass)), array('uid' => $uid));
		return ($this->db->affected_rows() == 1);
	}

	public function update_email($uid, $email) {
		$email = filter_var($email, FILTER_VALIDATE_EMAIL);
		if(empty($email)) {
			return FALSE;
		}
		
		$this->db->update('cmd_user', array('email' => $email), array('uid' => $uid));
		return ($this->db->affected_rows() == 1);
	}
}