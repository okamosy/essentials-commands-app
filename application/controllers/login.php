<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Login extends CI_Controller {
	public function __construct() {
		parent::__construct();
		
		$this->load->model('User_model');
		$this->load->helper('form');
		$this->load->library('form_validation');
		$this->load->library('session');
		$this->form_validation->set_error_delimiters('<li>', '</li>');
	}
	
	public function index() {
		$data['is_logged_in'] = $this->session->userdata('is_logged_in');
		if($data['is_logged_in']) {
			redirect('/');
		}
		elseif ($this->form_validation->run() == FALSE) {
			// Do nothing
		}
		elseif ($this->User_model->validate()) {
			$this->session->set_userdata('is_logged_in', TRUE);
			redirect('/');
		}
		else {
			$data['login_msg'] = 'Invalid username and/or password';
		}

		$data['title'] = 'ESS Wiki Admin Login';
		$data['view']  = 'login';
		$this->load->view('template', $data);
	}
	
	public function logout() {
		$this->session->sess_destroy();
		redirect('/');
	}
}