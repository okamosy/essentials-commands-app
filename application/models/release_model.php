<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Release_model extends CI_Model
{
	public function __construct() {
		parent::__construct();
	}

	public function fetch($rid, $version = 0) {
		$where = array(
			'rid' => $rid,
		);
		if (!empty($version)) {
			$where['version'] = $version;
		}

		$release = $this->db->order_by('version DESC')
			->limit(1)
			->get_where('cmd_release', $where)
			->row();

		if (empty($release) || $release->status == ESS_DELETED) {
			return FALSE;
		}

		return $release;
	}

	public function fetch_all() {
		$query        = $this->db->group_by('rid')
			->order_by('rid DESC, version DESC')
			->get('cmd_release');
		$release_list = array();
		foreach ($query->result() as $release) {
			if ($release->status != ESS_DELETED) {
				$release_list[] = $release;
			}
		}

		return $release_list;
	}

	public function fetch_published() {
		$order  = array(ESS_DEFAULT, ESS_PROMOTED, ESS_PUBLISHED);
		$result = $this->_fetch_type($order);

		$release_list = array();
		foreach ($order as $status) {
			foreach ($result as $release) {
				if ($release->status == $status) {
					$release_list[] = $release;
				}
			}
		}

		return $release_list;
	}

	public function fetch_default() {
		$releases = $this->_fetch_type(array(ESS_DEFAULT));

		return !empty($releases) ? $releases[0] : FALSE;;
	}

	public function fetch_versions($rid) {
		$query    = $this->db->order_by('version DESC')
			->get_where('cmd_release', array('rid' => $rid));
		$versions = array();
		foreach ($query->result() as $row) {
			$versions[] = $row;
		}

		return $versions;
	}

	public function insert(array $data) {
		if (empty($data['name']) || $this->_fetch_by_name($data['name']) !== FALSE) {
			return FALSE;
		}

		$rid = $this->db->select_max('rid')->get('cmd_release')->row()->rid;
		$rid++;

		$data['rid']     = $rid;
		$data['status']  = ESS_UNPUBLISHED;
		$data['version'] = 1;

		$this->db->insert('cmd_release', $data);

		return $this->db->affected_rows() != 1 ? FALSE : $this->fetch($rid);
	}

	public function clone_release($rid, array $data) {
		$release = $this->fetch($rid);

		if(empty($release)) {
			return FALSE;
		}

		foreach($data as $field => $value) {
			$release->{$field} = $value;
		}

		return $this->insert((array)$release);
	}

	public function edit($rid, array $data) {
		$release = $this->fetch($rid);
		if (empty($release) || (isset($data['name']) && (empty($data['name']) || $this->_fetch_by_name($data['name']) !== FALSE))) {
			return FALSE;
		}

		foreach($data as $field => $value) {
			$release->{$field} = $value;
		}
		$release->version = $this->_get_latest_version($rid);
		$this->db->insert('cmd_release', $release);

		return ($this->db->affected_rows() != 1) ? FALSE : $release;
	}

	public function update_status($rid, $status) {
		return $this->_change_status($rid, $status);
	}

	public function delete($rid) {
		return $this->_change_status($rid, ESS_DELETED);
	}

	public function mark_unpublished($rid) {
		return $this->_change_status($rid, ESS_UNPUBLISHED);
	}

	public function mark_published($rid) {
		return $this->_change_status($rid, ESS_PUBLISHED);
	}

	public function mark_promoted($rid) {
		return $this->_change_status($rid, ESS_PROMOTED);
	}

	public function mark_default($rid) {
		$current_default = $this->fetch_default();
		$this->_change_status($current_default->rid, ESS_PROMOTED);

		return $this->_change_status($rid, ESS_DEFAULT);
	}

	public function revert($rid, $version) {
		$release = $this->_fetch_version($rid, $version);
		if (empty($release)) {
			return FALSE;
		}

		return $this->edit($release->rid, (array)$release);
	}

	protected function _fetch_type(array $status) {
		$query        = $this->db->group_by('rid')
			->order_by('rid DESC, version DESC')
			->where_in('status', $status)
			->get('cmd_release');
		$release_list = array();
		foreach ($query->result() as $release) {
			$release_list[] = $release;
		}

		return $release_list;
	}

	protected function _fetch_by_name($name) {
		$query = $this->db->group_by('rid')
			->order_by('rid DESC, version DESC')
			->get_where('cmd_release', array('status <>' => ESS_DELETED));
		foreach ($query->result() as $release) {
			if (strcasecmp($release->name, $name) == 0) {
				return $release;
			}
		}

		return FALSE;
	}

	protected function _fetch_version($rid, $version) {
		$query = $this->db->where(array('rid' => $rid, 'version' => $version))
			->limit(1)
			->get('cmd_release');

		return $query->row();
	}

	protected function _change_status($rid, $status) {
		$release = $this->fetch($rid);
		if (empty($release)) {
			return FALSE;
		}

		// If the status is default, then we need to make sure it's the only one
		if($status == ESS_DEFAULT) {
			$current_default = $this->fetch_default();
			if(!empty($current_default) && $current_default->rid != $rid) {
				$this->_change_status($current_default->rid, ESS_PROMOTED);
			}
		}

		$this->db->where(array('rid' => $rid, 'version' => $release->version))
			->update('cmd_release', array('status' => $status));

		if ($this->db->affected_rows() == 1) {
			$release->status = $status;

			return $release;
		}

		return FALSE;
	}

	private function _get_latest_version($rid) {
		$query = $this->db->select_max('version')
			->get_where('cmd_release', array('rid' => $rid))
			->row();
		return $query->version + 1;
	}
}