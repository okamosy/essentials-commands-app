<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Release_Trigger_Map_model extends CI_Model {
	public function fetch($rid) {
        $where = array(
            'rid'       => $rid,
            'status'    => 1,
        );
		$query = $this->db->get_where('cmd_release_trigger_map', $where);
		$map = array();
		foreach($query->result() as $row) {
			$map[$row->tid] = $row;
		}

		return $map;
	}

	public function insert($rid, $tid, $t_version) {
		$data = array(
			'rid'       => $rid,
			'tid'       => $tid,
			't_version' => $t_version,
		);

		// Check if this already exists
		$mapping = $this->db->get_where('cmd_release_trigger_map', $data);
		if($mapping->num_rows() > 0) {
			return FALSE;
		}

		$this->db->insert('cmd_release_trigger_map', $data);
		return ($this->db->affected_rows() == 1);
	}

	public function edit($rid, $tid, $new_t_version) {
		$this->db->where(array('rid' => $rid, 'tid' => $tid))
				->update('cmd_release_trigger_map', array('t_version' => $new_t_version));
		return ($this->db->affected_rows() == 1);
	}

    /**
     * Deletes the mapping for the specified release/trigger mapping
     *
     * @param array $map
     * @return bool
     */
    public function delete(array $map) {
        $this->db->update('cmd_release_trigger_map', array('status' => 0), $map);
        return ($this->db->affected_rows() > 0);
	}
}