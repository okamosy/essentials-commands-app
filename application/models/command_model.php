<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Command_model extends CI_Model {
	private $_ci;

	public function __construct() {
		parent::__construct();
		$this->_ci =& get_instance();
	}

	/**
	 * Returns a boolean indicating if the user is currently logged in
	 *
	 * @return boolean true if the user is logged in, false otherwise
	 */
	public function is_authenticated() {
		$user = $this->_fetch_user();

		// If the user is not logged in...make sure there's no session
		if(empty($user)) {
			if(isset($this->_ci->session)) {
				$this->_ci->session->sess_destroy();
			}
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Attempts to authenticate the user based on the provided credentials
	 *
	 * @param  string $username
	 * @param  string $password
	 * @return bool
	 */
	public function authenticate($username, $password) {
		if(!isset($this->_ci->User_model)) {
			$this->_ci->load->model('User_model');
		}

		$user = $this->_ci->User_model->fetch($username, $password);

		if(empty($user)) {
			$this->_log_event(Log_model::EVENT_INVALID_LOGIN, 'Invalid login attempt: '.$username);
			return FALSE;
		}

		if(!isset($this->_ci->session)) {
			$this->_ci->load->library('session');
		}

		$this->_ci->session->set_userdata('user', $user);
		$this->_log_event(Log_model::EVENT_LOGIN);

		return TRUE;
	}

	/**
	 * Logs out the user
	 *
	 * @return null
	 */
	public function logout() {
		if(!isset($this->_ci->session)) {
			$this->_ci->load->library('session');
		}

		$user = $this->_fetch_user();
		if(!empty($user)) {
			$this->_log_event(Log_model::EVENT_LOGOUT);
		}

        $this->_ci->session->unset_userdata('user');
		$this->_ci->session->sess_destroy();

	}

	/**
	 * Returns a list of releases.
	 *
	 * If the user is authenticated, this will include unpublished releases
	 *
	 * @return array
	 */
	public function fetch_releases() {
		$releases = $this->_ci->Release_model->fetch_all();
		$is_authorized = $this->is_authenticated();

		$return = array();
		$default = null;
		$promoted = array();
		foreach($releases as $release) {
			if($release->status == ESS_DEFAULT) {
				// Save this off so it can be the first item in the list
				$default = $release;
			}
			elseif($release->status == ESS_PROMOTED) {
				$promoted[] = $release;
			}
			elseif($release->status != ESS_UNPUBLISHED || $is_authorized) {
				$return[] = $release;
			}
		}

		if(!empty($promoted)) {
			$return = array_merge($promoted, $return);
		}

		if(!empty($default)) {
			array_unshift($return, $default);
		}

		return $return;
	}

	/**
	 * Returns details of the specified release
	 *
	 * Returns false if the release doesn't exist, or is unpublished and the user is not logged in.
	 * Otherwise, returns an object representing the release
	 *
	 * @param  int $rid
	 * @return bool|object
	 */
	public function fetch_release($rid = 0) {
		if(empty($rid)) {
			$release_list = $this->fetch_releases();

			if(empty($release_list)) {
				return FALSE;
			}
			$release = $release_list[0];
		}
		else {
			$release = $this->_ci->Release_model->fetch($rid);
		}

		if(empty($release) ||
			$release->status == ESS_DELETED ||
			($release->status == ESS_UNPUBLISHED && !$this->is_authenticated())) {
			return FALSE;
		}

		return $release;
	}

	/**
	 * Returns a list of triggers for the specified release
	 *
	 * Returns false if the release doesn't exist, or is unpublished and the user is not logged in.
	 * Otherwise, returns a list of triggers.
	 *
	 * @param  int $rid
	 * @return bool|array
	 */
	public function fetch_triggers($rid) {
		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		return $this->_fetch_trigger_list(array('rid' => $release->rid));
	}

	/**
	 * Returns an array containing the specified trigger details along with associated permissions.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @return bool|array
	 */
	public function fetch_trigger_details($rid, $tid) {
		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		$trigger_version = $this->_fetch_release_trigger_version($rid, $tid);
		if(empty($trigger_version)) {
			return FALSE;
		}

		return array(
			'trigger' => $this->_ci->Trigger_model->fetch($trigger_version->tid, $trigger_version->version),
			'permissions' => $this->fetch_permissions($rid, $tid),
		);
	}

	/**
	 * Returns a list of permissions for the specified release and trigger
	 *
	 * Returns false if the release is invalid, or unpublished and the user is anonymous.
	 * Otherwise returns an array of permissions for the associated filter.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @return false|array
	 */
	public function fetch_permissions($rid, $tid = 0) {
		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		if(empty($tid)) {
			return $this->_fetch_release_permissions($rid);
		}

		$trigger = $this->_fetch_release_trigger_version($rid, $tid);
		if(empty($trigger)) {
			return FALSE;
		}

		$perm_list = array();
		$filter = array(
			'tid'                         => $trigger->tid,
			't_version'                   => $trigger->version,
			'cmd_trigger_perm_map.status' => 1,
		);
		$query = $this->db->select('cmd_perm.*')
						->join('cmd_perm', 'cmd_perm.pid=cmd_trigger_perm_map.pid AND cmd_perm.version=cmd_trigger_perm_map.p_version', 'left')
						->where($filter)
						->get('cmd_trigger_perm_map');

		foreach($query->result() as $row) {
			$perm_list[] = $row;
		}

		return $perm_list;
	}

	/**
	 * Returns a cloned version of the specified release
	 *
	 * Returns false if the user is not authenticated, or the specified source release is invalid.
	 * Otherwise, it returns the newly cloned release.
	 *
	 * @param  int $rid
	 * @param  string $name
	 * @return false|object
	 */
	public function clone_release($rid, array $data) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

        try {
            $map_list = $this->_ci->Release_Trigger_Map_model->fetch($rid);

            // Start a transaction since there are several db actions that have to occur
            $this->db->trans_begin();

            $new_release = $this->_ci->Release_model->clone_release($rid, $data);
            if(empty($new_release)) {
                throw new Exception('release clone failed');
            }

	        $this->_log_event(Log_model::EVENT_INSERT, sprintf("Release '%s' cloned to '%s'", $release->name, $new_release->name));

            foreach($map_list as $map) {
                $new_trigger = $this->_ci->Trigger_model->clone_trigger($map->tid, $map->t_version);
                if(empty($new_trigger) ||
                    !$this->_ci->Release_Trigger_Map_model->insert($new_release->rid, $new_trigger->tid, $new_trigger->version)) {
                    throw new Exception('trigger clone failed');
                }

                $perm_map = $this->_ci->Trigger_Perm_Map_model->fetch(array('tid' => $map->tid, 't_version' => $map->t_version));
                foreach($perm_map as $perm) {
                    $new_perm = $this->_ci->Perm_model->clone_perm($perm->pid, $perm->p_version);
                    if(empty($new_perm)) {
                    	throw new Exception("Permission clone failed", 1);
                    }
                    elseif(!$this->_ci->Trigger_Perm_Map_model->insert(array('tid' => $new_trigger->tid, 't_version' => $new_trigger->version, 'pid' => $new_perm->pid, 'p_version' => $new_perm->version))) {
                        throw new Exception('Permission mapping failed');
                    }
                }
            }
        }
        catch(Exception $e) {
            $this->db->trans_rollback();
            return $e->getMessage();
        }

		$this->db->trans_commit();
		return $new_release;
	}

	/**
	 * Attempts to edit the name of the specified release and returns the result.
	 *
	 * @param  int $rid
	 * @param  array $data
	 * @return bool
	 */
	public function edit_release($rid, array $data) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		if($result =  $this->_ci->Release_model->edit($rid, $data)) {
			$changes = array();
			foreach($data as $key => $value) {
				$changes[] = sprintf("%s '%s' => '%s'", $key, $release->{$key}, $value);
			}
			$this->_log_event(Log_model::EVENT_EDIT, sprintf("Release %d edited: %s", $release->rid, implode(', ', $changes)));
		}
		return !empty($result) ? $result : $release;
	}

	/**
	 * Updates the status of the specified release.
	 *
	 * @param  int $rid
	 * @param  string $status
	 * @return bool|string
	 */
	public function update_release_status($rid, $status) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		if($result = $this->_ci->Release_model->update_status($rid, $status)) {
			$this->_log_event(Log_model::EVENT_EDIT, sprintf("Release %d status changed: '%s' => '%s'", $release->rid, $release->status, $result->status));
		}
		return !empty($result) ? $result : $release;
	}

	/**
	 * Deletes the specified release.
	 *
	 * Returns false or the deleted release.
	 *
	 * @param  int $rid
	 * @return bool|object
	 */
	public function delete_release($rid) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		if($release = $this->_ci->Release_model->delete($rid)) {
			$this->_log_event(Log_model::EVENT_DELETE, sprintf("Release %d deleted", $release->rid));
		}

		return $release;
	}

	/**
	 * Returns a list of versions for the specified release.
	 *
	 * @param  int $rid
	 * @return bool|array
	 */
	public function fetch_release_versions($rid) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$versions = $this->_ci->Release_model->fetch_versions($rid);
		return empty($versions) ? FALSE : $versions;
	}

	/**
	 * Reverts to the specified version and returns a copy.
	 *
	 * @param   $rid
	 * @param   $version
	 * @return  bool|object
	 */
	public function revert_release($rid, $version) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid, $version);
		if(empty($release)) {
			return FALSE;
		}

		if($result = $this->_ci->Release_model->revert($rid, $version)) {
			$this->_log_event(Log_model::EVENT_RESTORE, sprintf("Release %d restored from version %d to %d", $release->rid, $release->version, $version));
		}

		return $result;
	}

	/**
	 * Creates a new trigger and maps it to the specified release
	 *
	 * @param  int $rid
	 * @param  array $data
	 * @return  bool|object
	 */
	public function create_trigger($rid, $data) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		try {
			$this->db->trans_start();

			$trigger = $this->_ci->Trigger_model->insert($data);
			if(empty($trigger)) {
				throw new Exception('Failed to create new trigger');
			}

			if(!$this->_ci->Release_Trigger_Map_model->insert($rid, $trigger->tid, $trigger->version)) {
				throw new Exception('Failed to create new RT Mapping');
			}

			$this->_log_event(Log_model::EVENT_INSERT, sprintf("New Trigger '%s' added to Release '%s' (rid %d)", $trigger->trigger, $release->name, $release->rid));
		}
		catch(Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $trigger;
	}

	/**
	 * Edits the specified trigger and returns the new trigger object.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @param  array $data
	 * @return bool|object
	 */
	public function edit_trigger($rid, $tid, $data) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		$trigger_list = $this->_fetch_trigger_list(array('rid' => $rid, 'cmd_trigger.tid' => $tid));
		if(empty($trigger_list)) {
			return FALSE;
		}
		$old_trigger = reset($trigger_list);

		try {
			$this->db->trans_begin();

			$updated_trigger = $this->_ci->Trigger_model->edit($old_trigger->tid, $old_trigger->version, $data);
			if(empty($updated_trigger)) {
				throw new Exception();
			}

			if(!$this->_ci->Release_Trigger_Map_model->edit($rid, $tid, $updated_trigger->version)) {
				throw new Exception();

			}

			$mapping = $this->_ci->Trigger_Perm_Map_model->fetch(array('tid' => $old_trigger->tid, 't_version' => $old_trigger->version));
			if(!empty($mapping)) {
				foreach($mapping as $map) {
					$new_map = clone $map;
					$new_map->t_version = $updated_trigger->version;
					if(!$this->_ci->Trigger_Perm_Map_model->edit($new_map, (array)$map)) {
						throw new Exception();
					}
				}
			}

			$message = array();
			foreach($data as $key => $value) {
				$message[] = sprintf("%s: '%s' => '%s'", $key, $old_trigger->{$key}, $value);
			}
			$this->_log_event(Log_model::EVENT_EDIT, sprintf("Trigger %d for Release '%s' (rid: %d): %s", $old_trigger->tid, $release->name, $release->rid, implode('; ', $message)));
		}
		catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $updated_trigger;
	}

	/**
	 * Deletes the trigger and returns it.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @return bool|object
	 */
	public function delete_trigger($rid, $tid) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		$trigger_list = $this->_fetch_trigger_list(array('rid' => $rid, 'cmd_trigger.tid' => $tid));
		if(empty($trigger_list)) {
			return FALSE;
		}
		$trigger = reset($trigger_list);

        $this->db->trans_begin();
		try {
			if(!$this->_ci->Trigger_model->delete($trigger->tid)) {
				throw new Exception('Failed deleting the trigger');
			}

			if(!$this->_ci->Release_Trigger_Map_model->delete(array('rid' => $rid, 'tid' => $tid))) {
				throw new Exception();
			}

			$mapping = $this->_ci->Trigger_Perm_Map_model->fetch(array('tid' => $trigger->tid, 't_version' => $trigger->version));
			foreach($mapping as $map) {
				if(!$this->_ci->Trigger_Perm_Map_model->delete((array)$map)) {
					throw new Exception();
				}
			}

			$this->_log_event(Log_model::EVENT_DELETE, sprintf("Trigger '%s' (tid: %d) from Release '%s' (rid: %d)", $trigger->trigger, $trigger->tid, $release->name, $release->rid));
		}
		catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $trigger;
	}

	/**
	 * Reverts the trigger for this release to the specified version.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @param  int $version
	 * @return bool|object
	 */
	public function revert_trigger($rid, $tid, $version) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		try {
			$this->db->trans_begin();

			$current_trigger = $this->_fetch_release_trigger_version($rid, $tid);

			$trigger = $this->_ci->Trigger_model->revert($tid, $version);
			if(empty($trigger)) {
				throw new Exception('failed to revert trigger');
			}

			if(!$this->_ci->Release_Trigger_Map_model->edit($rid, $trigger->tid, $trigger->version)) {
				throw new Exception('failed to update rtm');
			}

			$mapping = $this->_ci->Trigger_Perm_Map_model->fetch(array('tid' => $tid, 't_version' => $current_trigger->version));
			foreach($mapping as $map) {
				$new_map = clone $map;
				$new_map->t_version = $trigger->version;
				if(!$this->_ci->Trigger_Perm_Map_model->edit($new_map, (array)$map)) {
					throw new Exception('failed to update tpm');
				}
			}

			$this->_log_event(Log_model::EVENT_RESTORE,
			                  sprintf("Trigger %d from Release '%s' (rid: %d) reverted from version %d to %d",
			                          $trigger->tid, $release->name, $release->rid, $current_trigger->version, $version));
		}
		catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $trigger;
	}

	/**
	 * Creates a new permission and associates it with the specified release and trigger.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @param  array $data
	 * @return bool|object
	 */
	public function create_permission($rid, $tid, $data) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		$trigger_list = $this->_fetch_trigger_list(array('rid' => $rid, 'cmd_trigger.tid' => $tid));
		if(empty($trigger_list)) {
			return FALSE;
		}
		$trigger = reset($trigger_list);

		try {
			$this->db->trans_begin();

			$perm = $this->_ci->Perm_model->insert($data);
			if(empty($perm)) {
				throw new Exception();
			}

			$map = array(
				'tid'       => $trigger->tid,
				't_version' => $trigger->version,
				'pid'       => $perm->pid,
				'p_version' => $perm->version,
			);

			if(!$this->_ci->Trigger_Perm_Map_model->insert($map)) {
				throw new Exception();
			}

			$this->_log_event(Log_model::EVENT_INSERT,
			                  sprintf("Permission '%s' added to Release '%s' (rid: %d), Trigger '%s' (tid: %d)",
			                          $data['perm'], $release->name, $release->rid, $trigger->trigger, $trigger->tid));
		}
		catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $perm;
	}

	/**
	 * Edits the permission for the specified release and trigger, and returns the updated permission.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @param  int $pid
	 * @param  array $data
	 * @return bool|object
	 */
	public function edit_permission($rid, $tid, $pid, $data) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		$trigger_list = $this->_fetch_trigger_list(array('rid' => $rid, 'cmd_trigger.tid' => $tid));
		if(empty($trigger_list)) {
			return FALSE;
		}
		$trigger = reset($trigger_list);

		$mapping = $this->_ci->Trigger_Perm_Map_model->fetch(array('tid' => $trigger->tid, 't_version' => $trigger->version));
		if(empty($mapping) || empty($mapping[$pid])) {
			return FALSE;
		}
		$map = $mapping[$pid];

		$perm = $this->_ci->Perm_model->fetch($pid, $map->p_version);
		if(empty($perm)) {
			return FALSE;
		}

		try {
			$this->db->trans_begin();

			$updated_perm = $this->_ci->Perm_model->edit($perm->pid, $perm->version, $data);
			if(empty($updated_perm)) {
				throw new Exception();
			}

			$updated_map = clone $map;
			$updated_map->p_version = $updated_perm->version;
			if(!$this->_ci->Trigger_Perm_Map_model->edit($updated_map, (array)$map)) {
				throw new Exception();
			}

			$message = array();
			foreach($data as $key => $value) {
				$message[] = sprintf("%s: '%s' => '%s'", $key, $perm->{$key}, $value);
			}
			$this->_log_event(Log_model::EVENT_EDIT, sprintf("Permission %d for Release '%s' (rid: %d), Trigger '%s' (tid: %d): %s",
			                                                 $perm->pid, $release->name, $release->rid, $trigger->trigger, $trigger->tid, implode(', ', $message)));
		}
		catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $updated_perm;
	}

	/**
	 * Deletes the permission from the specified release and trigger.
	 * Returns a copy of the deleted permission.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @param  int $pid
	 * @return bool|object
	 */
	public function delete_permission($rid, $tid, $pid) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		$trigger = $this->_fetch_release_trigger_version($rid, $tid);
		if(empty($trigger)) {
			return FALSE;
		}

		$mapping = $this->_ci->Trigger_Perm_Map_model->fetch(array('tid' => $trigger->tid, 't_version' => $trigger->version));
		if(empty($mapping[$pid])) {
			return FALSE;
		}
		$map = $mapping[$pid];

		$perm = $this->_ci->Perm_model->fetch($pid, $map->p_version);

		try {
			$this->db->trans_begin();

			if(!$this->_ci->Perm_model->delete($perm->pid, $perm->version)) {
				throw new Exception('Failed to delete the specified permission');
			}

			if(!$this->_ci->Trigger_Perm_Map_model->delete((array)$map)) {
				throw new Exception('Filed to update TPM');
			}

			$this->_log_event(Log_model::EVENT_DELETE,
			                  sprintf("Permission '%s' (pid: %d) deleted from Release '%s' (rid: %d), Trigger '%s' (tid: %d)",
			                          $perm->perm, $perm->pid, $release->name, $release->rid, $trigger->trigger, $trigger->tid));
		}
		catch (Exception $e) {
			$this->db->trans_rollback();
			echo $e->getMessage();
			return FALSE;
		}

        $this->db->trans_commit();
		return $perm;
	}

	/**
	 * Reverts the permission to the specified version for the provided release and trigger.
	 * Returns false on error, or a copy of the reverted permission.
	 *
	 * @param  int $rid
	 * @param  int $tid
	 * @param  int $pid
	 * @param  int $version
	 * @return bool|object
	 */
	public function revert_permission($rid, $tid, $pid, $version) {
		if(!$this->is_authenticated()) {
			return FALSE;
		}

		$release = $this->fetch_release($rid);
		if(empty($release)) {
			return FALSE;
		}

		$trigger = $this->_fetch_release_trigger_version($rid, $tid);
		if(empty($trigger)) {
			return FALSE;
		}

		$current_perm = $this->_ci->Perm_model->fetch($pid);
		if(empty($current_perm)) {
			return FALSE;
		}

		try {
			$this->db->trans_begin();

			$perm = $this->_ci->Perm_model->revert($pid, $version);
			if(empty($perm)) {
				throw new Exception('Failed reverting permission');
			}

			$updated_map = array(
				'tid'       => $trigger->tid,
				't_version' => $trigger->version,
				'pid'       => $perm->pid,
				'p_version' => $perm->version,
			);

			$current_map = $this->_ci->Trigger_Perm_Map_model->fetch(array('tid' => $trigger->tid, 't_version' => $trigger->version, 'pid' => $pid));
			if(empty($current_map[$pid])) {
				if(!$this->_ci->Trigger_Perm_Map_model->insert($updated_map)) {
					throw new Exception('Failed updating empty TPM');
				}
			}
			elseif(!$this->_ci->Trigger_Perm_Map_model->edit($updated_map, (array)$current_map[$pid])) {
				throw new Exception('Failed updating TPM');
			}

			$this->_log_event(Log_model::EVENT_RESTORE,
			                  sprintf("Permission %d for Release '%s' (rid: %d), Trigger '%s' (tid: %d) restored from version %d to %d",
			                          $perm->pid, $release->name, $release->rid, $trigger->trigger, $trigger->tid, $current_perm->version, $version));
		}
		catch(Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $perm;
	}

	public function search($text, $release = '', $type = 'trigger') {
		if(empty($text)) {
			return FALSE;
		}

		$search_string = $this->db->escape_like_str($text);
		$release_filter = empty($release) ?
			sprintf("release_status = '%s'", ESS_DEFAULT) :
			sprintf("release_status IN ('%s') AND release_name LIKE '%s'",
			        implode("','", array(ESS_DEFAULT, ESS_PUBLISHED, ESS_PROMOTED)),
			        $this->db->escape_like_str($release));

		switch(strtolower($type)) {
			case 'trigger':
				return $this->_search_trigger($search_string, $release_filter);
			case 'perm':
				return $this->_search_perm($search_string, $release_filter);
			default:
				return FALSE;
		}
	}

	public function fetch_log() {
		return $this->_ci->Log_model->fetch();
	}

	private function _fetch_user() {
		if(!isset($this->_ci->session)) {
			if(!$this->input->cookie('cmd_session')) {
				return FALSE;
			}
			$this->_ci->load->library('session');
		}

		return $this->_ci->session->userdata('user');
	}

	private function _log_event($event, $data = '') {
		$user = $this->_fetch_user();
		$uid = empty($user) ? 0 : $user->uid;

		$this->_ci->Log_model->insert(array('uid' => $uid, 'event' => $event, 'data' => $data));
	}

	private function _search_trigger($text, $release) {
		$base_sql = "
			SELECT r.rid, r.status as release_status, r.name as release_name, t.*
			FROM cmd_release r
				JOIN cmd_release_trigger_map rtm ON ( r.rid = rtm.rid )
				JOIN cmd_trigger t ON ( t.tid = rtm.tid AND t.version = rtm.t_version )
			WHERE rtm.status =1
				AND t.status =1
				AND (%s)
			GROUP BY t.tid
			ORDER BY r.version DESC, t.version DESC";
		$permutations = array(
			"t.trigger LIKE '%s' OR t.alias LIKE '%s'",
			"t.trigger LIKE '%%%s%%' OR t.alias LIKE '%%%s%%'",
			"t.instr LIKE '%%%s%%' OR t.desc LIKE '%%%s%%'",
		);

		$sql = '';
		foreach($permutations as $permute) {
			$sql .= sprintf("(%s) UNION ", sprintf($base_sql, sprintf($permute, $text, $text)));
		}
		$sql = sprintf("
			SELECT *
			FROM (
				%s
			) triggers
			WHERE {$release}
			LIMIT %s
			", rtrim($sql, ' UNION'), MAX_SEARCH_RESULTS);

		$query = $this->db->query($sql);

		$results = array();
		foreach($query->result() as $row) {
			$perm_list = $this->fetch_permissions($row->rid, $row->tid);
			$perms = array();
			foreach($perm_list as $perm) {
				$perms[] = (object)array(
					'perm' => $perm->perm,
					'pdesc' => $perm->pdesc,
				);
			}

			$results[] = (object)array(
				'trigger' => $row->trigger,
				'alias' => $row->alias,
				'desc' => $row->desc,
				'instr' => $row->instr,
				'syntax' => $row->syntax,
				'perms' => $perms,
			);

			if(strcasecmp($row->trigger, $text) === 0 ||
			   in_array($text, explode(', ', $row->alias))
			) {
				break;
			}
		}

		return $results;
	}

	private function _search_perm($text, $release) {
		$base_sql = "
			SELECT r.status as release_status, r.name as release_name, p.perm, p.pdesc, t.trigger, t.alias, t.desc, t.instr, t.syntax
			FROM cmd_perm p
			JOIN cmd_trigger_perm_map tpm ON (p.pid=tpm.pid AND p.version=tpm.p_version)
			JOIN cmd_trigger t ON (t.tid=tpm.tid AND t.version=tpm.t_version)
			JOIN cmd_release_trigger_map rtm ON (t.tid=rtm.tid AND t.version=rtm.t_version)
			JOIN cmd_release r ON (r.rid=rtm.rid)
			WHERE rtm.status =1
				AND tpm.status =1
				AND t.status =1
				AND p.status =1
				AND (%s)
			GROUP BY r.rid, t.tid, p.pid
			ORDER BY r.version DESC, t.version DESC, p.version DESC
		";
		$permutations = array(
			"p.perm LIKE '%s' OR p.pdesc LIKE '%s'",
			"p.perm LIKE '%%%s%%' OR p.pdesc LIKE '%%%s%%'",
		);

		$sql = '';
		foreach($permutations as $permute) {
			$sql .= sprintf("(%s) UNION ", sprintf($base_sql, sprintf($permute, $text, $text)));
		}
		$sql = sprintf("
		SELECT `perm`, `pdesc`, `trigger`, `alias`, `desc`, `instr`, `syntax`
		FROM (
			%s
		) perms
		WHERE {$release}
		LIMIT %s", rtrim($sql, ' UNION'), MAX_SEARCH_RESULTS);

		$query = $this->db->query($sql);

		$results = array();
		$num_results = 0;
		foreach($query->result() as $row) {
			$results[] = $row;
			if(++$num_results >= MAX_SEARCH_RESULTS || strcasecmp($row->perm, $text) === 0) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Returns an array of trigger objects matching the specified criteria.
	 *
	 * @param  array $where
	 * @return array
	 */
	private function _fetch_trigger_list($where) {
        $where['cmd_release_trigger_map.status'] = 1;
		$query = $this->db->select('cmd_trigger.*')
						->join('cmd_trigger', 'cmd_trigger.tid=cmd_release_trigger_map.tid AND cmd_trigger.version=cmd_release_trigger_map.t_version')
						->where($where)
						->get('cmd_release_trigger_map');

		$trigger_list = array();
		foreach($query->result() as $row) {
			$trigger_list[] = $row;
		}

		return $trigger_list;
	}

	private function _fetch_release_trigger_version($rid, $tid) {
		$mapping = $this->_ci->Release_Trigger_Map_model->fetch($rid);
		foreach($mapping as $trigger) {
			if($trigger->tid == $tid) {
				return $this->_ci->Trigger_model->fetch($trigger->tid, $trigger->t_version);
			}
		}

		return FALSE;
	}

	private function _fetch_release_permissions($rid) {
		$query = $this->db->select('p.*, t.tid, t.cat, t.trigger, t.alias, t.desc, t.instr, t.syntax')
						->join('cmd_release_trigger_map rtm', 'rtm.tid=tpm.tid AND rtm.t_version=tpm.t_version')
						->join('cmd_perm p', 'p.pid=tpm.pid AND p.version=tpm.p_version', 'left')
						->join('cmd_trigger t', 't.tid=rtm.tid AND t.version=rtm.t_version', 'left')
						->get_where('cmd_trigger_perm_map AS tpm', array('rtm.status' => 1, 'tpm.status' => 1, 'rtm.rid' => $rid));

		$permissions = array();
		foreach($query->result() as $row) {
			$permissions[] = $row;
		}

		return $permissions;
	}
}