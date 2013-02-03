<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Docs extends CI_Controller {
	private $is_logged_in;

	public function __construct() {
		parent::__construct();

		if($this->input->cookie('cmd_session')) {
			$this->load->library('session');
			$this->is_logged_in = $this->session->userdata('is_logged_in');
			if($this->is_logged_in == FALSE) {
				$this->session->sess_destroy();
			}
		}
		else {
			$this->is_logged_in = FALSE;
		}
	}
	
	public function index($view = '') {
		$data['is_logged_in'] = $this->is_logged_in;

		$data['title']		= 'ESS Command Wiki';
		if($view == 'permissions') {
			$data['permissions']	= $this->Command_model->get_permissions();
			$data['view']			= 'docs/permissions';
		}
		else {
			$data['commands']	= $this->Command_model->get_commands();
			$data['view']		= 'docs/commands';
		}

		$this->load->view('template', $data);
	}
	
	public function permissions() {
		$this->index('permissions');
	}
	
	public function fetch($type = '', $id = 0) {
		switch(strtolower($type)) {
			case 'command_details':
				$command = $this->Command_model->get_command_details($id);
				$command['is_logged_in'] = $this->is_logged_in;
				if(!$command['command']) {
					$data = array(
						'status'	=> FALSE,
						'message'	=> 'Unable to find the requested command',
					);
				}
				else {
					$data = array(
						'status'		=> TRUE,
						'permissions'	=> $this->load->view('docs/details', $command, TRUE),
					);
				}
				break;
			case 'category':
				$categories = $this->Command_model->search_commands('cat', $id, TRUE);
				$data = array();
				foreach($categories->result() as $row) {
					$data[] = $row->cat;
				}
				die(json_encode($data));
			default:
				$data = array(
					'status'	=> FALSE,
					'message'	=> 'Unable to understand what you are requesting',
				);
		}
		
		echo json_encode($data);
		exit();
	}
	
	public function update() {
		if($this->is_logged_in == FALSE) {
			return $value;
		}
		
		$id = explode('-', $this->input->post('id'));
		$value = html_entity_decode($this->input->post('value'));
		
		switch(strtolower($id[0])) {
			case 'cmd':
				if($this->Command_model->update_command($id[1], $id[2], $value) == FALSE) {
					$value = '';
				}
				break;
			case 'perm':
				if($this->Command_model->update_permission($id[1], $id[2], $value) == FALSE) {
					$value = '';
				}
				break;
			default:
				$value = '';
		}
		
		echo nl2br(htmlentities($value));
		exit();
	}
	
	public function insert($type) {
		if($this->is_logged_in == FALSE) {
			die(json_encode(array('status' => FALSE, 'message' => 'You don\'t have permission to do that.')));
		}
		
		$data = array('status' => FALSE);
		
		switch(strtolower($type)) {
			case 'command':
				if(($cmd = $this->Command_model->insert_command()) == FALSE) {
					$data['message'] = 'Unable to insert the command.';
				}
				else {
					$data['status'] = TRUE;
					$data['command'] = array(
						$cmd['tid'],
						img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'cmd-'.$cmd['tid'])).img(array('src' => 'assets/img/round_add.png', 'class' => 'details-img')),
						$cmd['cat'],
						$cmd['trigger'],
						$cmd['alias'],
						$cmd['desc'],
						htmlentities($cmd['syntax']),
					);
				}
				break;
			case 'permission':
				if(($perm = $this->Command_model->insert_permission()) == FALSE) {
					$data['message'] = 'Unable to insert the permission.';
				}
				else {
					$data['status'] = TRUE;
					$data['permission'] = '<tr>'.
											'<td>'.img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'perm-'.$perm['pid'])).'</td>'.
											'<td id="perm-'.$perm['pid'].'-perm" class="editable">'.$perm['perm'].'</td>'.
											'<td id="perm-'.$perm['pid'].'-pdesc" class="editable">'.$perm['pdesc'].'</td>'.
											'</tr>';
				}
				break;
			default:
				$data['message'] = 'You can\'t do that.';
		}
		
		echo json_encode($data);
		exit();
	}

	public function delete($type, $id) {
		if($this->is_logged_in == FALSE) {
			die(json_encode(array('status' => FALSE, 'message' => 'You do not have permission to do that.')));
		}

		$data = array('status' => FALSE);
		switch(strtolower($type)) {
			case 'cmd':
				if($this->Command_model->delete_command($id) == FALSE) {
					$data['message'] = 'There was a problem deleting this command.';
				}
				else {
					$data['status'] = TRUE;
				}
				break;
			case 'perm':
				if($this->Command_model->delete_permission($id) == FALSE) {
					$data['message'] = 'There was a problem deleting this permission.';
				}
				else {
					$data['status'] = TRUE;
				}
				break;
			default:
				$data['message'] = 'You can\'t delete that.';
		}
		
		 echo json_encode($data);
		 exit();
	}
}