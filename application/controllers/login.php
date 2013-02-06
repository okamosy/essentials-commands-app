<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Login extends CI_Controller {
	public function __construct() {
		parent::__construct();
		
		$this->load->helper('form', 'my_admin');
		$this->load->library('form_validation');
		$this->form_validation->set_error_delimiters('<li>', '</li>');
	}
	
	public function index() {
		$data['user'] = $this->Command_model->is_authenticated();
		if($data['user']) {
			redirect(base_url());
		}
		elseif ($this->form_validation->run() == FALSE) {
			// Do nothing
		}
		elseif (($user = $this->Command_model->authenticate($this->input->post('username'), $this->input->post('password')))) {
			redirect(base_url());
		}
		else {
			$data['login_msg'] = 'Invalid username and/or password';
		}

		$data['title'] = 'ESS Wiki Admin Login';
		$data['view']  = 'login';
		$this->load->view('template', $data);
	}
	
	public function logout() {
		$this->Command_model->logout();
		redirect(base_url());
	}
}