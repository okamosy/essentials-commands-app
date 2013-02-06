<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Docs extends CI_Controller
{
	private $is_logged_in;

	public function __construct() {
		parent::__construct();

		$this->is_logged_in = $this->Command_model->is_authenticated();
	}

	public function index($view = '', $rid = 0) {
		$data['is_logged_in'] = $this->is_logged_in;
		$data['release']      = $this->Command_model->fetch_release($rid);
		$data['rid'] = $rid;
		$data['release_list'] = $this->Command_model->fetch_releases();

		$data['title'] = 'ESS Command Wiki';
		if ($view == 'permissions') {
			$data['view']        = 'docs/permissions';
		} else {
			$data['view'] = 'docs/commands';
		}

		$this->load->view('template', $data);
	}

	public function permissions() {
		$this->index('permissions');
	}

	public function commands($rid) {
		$this->index('commands', $rid);
	}

	public function search() {
		$term = !empty($_GET['term']) ? $_GET['term'] : '';
		$release = !empty($_GET['release']) ? $_GET['release'] : '';
		$type = !empty($_GET['type']) ? $_GET['type'] : '';

		$type = empty($type) ? 'trigger' : $type;

		if(($results = $this->Command_model->search($term, $release, $type)) === FALSE) {
			$data = array(
				'status' => FALSE,
				'message' => "There was a problem searching for your command. Please check your syntax and try again.",
			);
		}
		else {
			$data = array(
				'status' => TRUE,
				'results' => $results,
			);
		}

		exit(json_encode($data));
	}

	public function fetch($type = '', $rid = 0, $id = 0) {
		switch (strtolower($type)) {
			case 'release':
				$details = array(
					'is_logged_in' => $this->is_logged_in,
					'release'      => $this->Command_model->fetch_release($rid),
				);
				$data    = array(
					'status'  => TRUE,
					'details' => $this->load->view('docs/release_details', $details, TRUE),
				);
				break;
			case 'release_selector':
				$release = array(
					'rid'          => $rid,
					'release_list' => $this->Command_model->fetch_releases(),
				);
				$data    = array(
					'status'   => TRUE,
					'selector' => $this->load->view('docs/release_selector', $release, TRUE),
				);
				break;
			case 'commands':
				$data = array(
					'aaData' => $this->_process_commands($rid),
				);
				break;
			case 'command_details':
				$command                 = $this->Command_model->fetch_trigger_details($rid, $id);
				$command['is_logged_in'] = $this->is_logged_in;
				if (empty($command)) {
					$data = array(
						'status'  => FALSE,
						'message' => 'Unable to find the requested command',
					);
				} else {
					$data = array(
						'status'      => TRUE,
						'permissions' => $this->load->view('docs/details', $command, TRUE),
					);
				}
				break;
			case 'permissions':
				$data = array(
					'aaData' => $this->_process_permissions($rid),
				);
				break;
			case 'category':
				$categories = $this->Command_model->search_commands('cat', $id, TRUE);
				$data       = array();
				foreach ($categories->result() as $row) {
					$data[] = $row->cat;
				}
				die(json_encode($data));
			case 'states':
				$states = array(
					ESS_DEFAULT     => ESS_DEFAULT,
					ESS_PROMOTED    => ESS_PROMOTED,
					ESS_PUBLISHED   => ESS_PUBLISHED,
					ESS_UNPUBLISHED => ESS_UNPUBLISHED,
				);
				die(json_encode($states));
			default:
				$data = array(
					'status'  => FALSE,
					'message' => 'Unable to understand what you are requesting',
				);
		}

		echo json_encode($data);
		exit();
	}

	public function update() {
		if ($this->is_logged_in == FALSE) {
			return $value;
		}

		$id    = explode('-', $this->input->post('id'));
		$rid   = $this->input->post('rid');
		$value = html_entity_decode($this->input->post('value'));

		switch (strtolower($id[0])) {
			case 'cmd':
				if ($this->Command_model->edit_trigger($rid, $id[1], array($id[2] => $value)) == FALSE) {
					$value = '';
				}
				break;
			case 'perm':
				list($tid, $pid) = explode('_', $id[1]);
				if ($this->Command_model->edit_permission($rid, $tid, $pid, array($id[2] => $value)) == FALSE) {
					$value = '';
				}
				break;
			case 'release':
				if ($id[1] == 'status') {
					$release = $this->Command_model->update_release_status($rid, $value);
				} else {
					$release = $this->Command_model->edit_release($rid, array($id[1] => $value));
				}
				$value = !empty($release) ? $release->{$id[1]} : '';
				break;
			default:
				$value = '';
		}

		echo nl2br(htmlentities($value));
		exit();
	}

	public function insert($type, $rid = 0) {
		if ($this->is_logged_in == FALSE) {
			die(json_encode(array('status' => FALSE, 'message' => 'You don\'t have permission to do that.')));
		}

		$data = array('status' => FALSE);

		switch (strtolower($type)) {
			case 'command':
				$data   = $this->input->post(NULL, TRUE);
				$perms  = $data['perm'];
				$pdescs = $data['pdesc'];

				unset($data['perm']);
				unset($data['pdesc']);

				if (($trigger = $this->Command_model->create_trigger($rid, $data)) == FALSE) {
					$data['message'] = 'Unable to insert the command.';
				} else {
					foreach ($perms as $index => $perm) {
						$this->Command_model->create_permission($rid, $trigger->tid, array('perm' => $perm, 'pdesc' => $pdescs[$index]));
					}

					$data['status']  = TRUE;
					$data['command'] = array(
						$trigger->tid,
						img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'cmd-' . $trigger->tid)) . img(array('src' => 'assets/img/round_add.png', 'class' => 'details-img')),
						$trigger->cat,
						$trigger->trigger,
						$trigger->alias,
						$trigger->desc,
						htmlentities($trigger->syntax),
					);
				}
				break;
			case 'permission':
				$tid  = $this->input->post('tid');
				$data = array(
					'perm'  => $this->input->post('perm'),
					'pdesc' => $this->input->post('pdesc'),
				);
				if (($perm = $this->Command_model->create_permission($rid, $tid, $data)) == FALSE) {
					$data['message'] = 'Unable to insert the permission.';
				} else {
					$perm->is_logged_in = $this->is_logged_in;
					$perm->tid          = $tid;
					$data['status']     = TRUE;
					$data['permission'] = $this->load->view('docs/permission_row', $perm, TRUE);
				}
				break;
			case 'release':
				$source_rid  = $this->input->post('source_release');
				$new_release = array(
					'name'       => $this->input->post('name'),
					'bukkit'     => $this->input->post('bukkit'),
					'change_log' => $this->input->post('change_log'),
					'notes'      => $this->input->post('notes'),
				);
				if (is_string(($release = $this->Command_model->clone_release($source_rid, $new_release)))) {
					$data['message'] = $release;
				} else {
					$data['status'] = TRUE;
					$data['rid'] = $release->rid;
				}
				break;
			default:
				$data['message'] = 'You can\'t do that.';
		}

		echo json_encode($data);
		exit();
	}

	public function delete($type, $rid, $id) {
		if ($this->is_logged_in == FALSE) {
			die(json_encode(array('status' => FALSE, 'message' => 'You do not have permission to do that.')));
		}

		$data = array('status' => FALSE);
		switch (strtolower($type)) {
			case 'cmd':
				if ($this->Command_model->delete_trigger($rid, $id) == FALSE) {
					$data['message'] = 'There was a problem deleting this command.';
				} else {
					$data['status'] = TRUE;
				}
				break;
			case 'perm':
				list($tid, $pid) = explode('_', $id);
				if ($this->Command_model->delete_permission($rid, $tid, $pid) == FALSE) {
					$data['message'] = 'There was a problem deleting this permission.';
				} else {
					$data['status'] = TRUE;
				}
				break;
			case 'release':
				if ($this->Command_model->delete_release($rid) == FALSE) {
					$data['message'] = 'There was a problem deleting this release. No changes have been made.';
				} else {
					$default_release = $this->Command_model->fetch_release();

					$data['rid']            = $default_release->rid;
					$data['status']         = TRUE;
					$data['release_filter'] = $this->load->view('docs/release_selector', array('release_list' => $this->Command_model->fetch_releases()), TRUE);
					$data['details']        = $this->load->view('docs/release_details', array('release' => $default_release, 'is_logged_in' => $this->is_logged_in), TRUE);
				}
				break;
			default:
				$data['message'] = 'You can\'t delete that.';
		}

		echo json_encode($data);
		exit();
	}

	private function _process_commands($rid) {
		$commands = $this->Command_model->fetch_triggers($rid);
		$data     = array();
		foreach ($commands as $command) {
			$data[] = array(
				$command->tid,
				($this->is_logged_in ? img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'cmd-' . $command->tid)) : '') .
				'<img src="http://essentials3.net/cache/doc/assets/img/round_add.png" class="details-img" alt=""/>',
				$command->cat,
				$command->trigger,
				$command->alias,
				$command->desc,
				nl2br(htmlspecialchars($command->syntax)),
			);
		}

		return $data;
	}

	private function _process_permissions($rid) {
		$permissions = $this->Command_model->fetch_permissions($rid);
		$data = array();
		foreach($permissions as $permission) {
			$data[] = array(
				$permission->pid,
				$permission->tid,
				htmlspecialchars($permission->cat),
				htmlspecialchars($permission->trigger),
				htmlspecialchars($permission->perm),
				htmlspecialchars($permission->pdesc),
			);
		}

		return $data;
	}
}