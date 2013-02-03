<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class User_model extends CI_Model {
	public function validate() {
		$data = array(
			'username'	=> $this->input->post('username'),
			'password'	=> md5($this->input->post('password')),
		);
		return $this->db->get_where('cmd_user', $data, 1)->row();
	}
}