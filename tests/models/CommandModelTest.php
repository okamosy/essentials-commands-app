<?php

/**
 * @group Model
 */

class ControlModelTest extends CIUnit_TestCase {
	private $_cm;
	private $_max_rid;
	private $_max_tid;
	private $_max_pid;
	private $_max_version;
	private $_has_versions;

	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	public function setUp() {
		parent::tearDown();
		parent::setUp();

		$this->CI->load->model('Command_model');
		$this->_cm = $this->CI->Command_model;
		$this->dbfixt('cmd_user',
		              'cmd_release',
		              'cmd_trigger',
		              'cmd_release_trigger_map',
		              'cmd_perm',
		              'cmd_trigger_perm_map');

		$this->cmd_user_fixt                = $this->_initialize_users($this->cmd_user_fixt);
		$this->_release_status_mapping      = $this->_initialize_releases();
		$this->cmd_trigger_fixt             = $this->_initialize_triggers();
		$this->cmd_release_trigger_map_fixt = $this->_initialize_rt_mapping();
		$this->cmd_perm_fixt                = $this->_initialize_perms();
		$this->cmd_trigger_perm_map_fixt    = $this->_initialize_tp_mapping();
	}

	public function tearDown() {
		$this->_cm->logout();
	}

	private function _objectify_fixture($fixture_list) {
		$list = array();
		foreach ($fixture_list as $fixture) {
			$list[] = (object)$fixture;
		}

		return $list;
	}

	private function _initialize_users($user_list) {
		$users = array();
		foreach ($user_list as $user) {
			$users[] = (object)$user;
			$this->CI->db->update('cmd_user',
			                      array('password' => md5($user['password'])),
			                      array('username' => $user['username']));
		}

		return $users;
	}

	private function _initialize_releases() {
		$releases = array();
		// Group the releases by rid
		foreach ($this->cmd_release_fixt as $release) {
			$releases[$release['rid']][] = (object)$release;
			if (empty($this->_has_versions['rid']) && count($releases[$release['rid']]) > 1) {
				$this->_has_versions['rid'] = $release['status'];
			}
			$this->_max_rid                             = ($release['rid'] > $this->_max_rid) ? $release['rid'] : $this->_max_rid;
			$this->_max_version['rid'][$release['rid']] = (empty($this->_max_version['rid'][$release['rid']]) || $release['version'] > $this->_max_version['rid'][$release['rid']]) ?
				$release['version'] : $this->_max_version['rid'][$release['rid']];
		}

		$this->_num_releases = count($releases);

		// Now re-order them
		// They should be in this order
		// - Default
		// - Promoted (newest first)
		// - Everything else (newest first)
		$result    = array();
		$promoted  = array();
		$remainder = array();
		foreach ($releases as $release) {
			// Flip the release to put the latest revision first
			$release = array_reverse($release);
			switch ($release[0]->status) {
				case ESS_DEFAULT:
					$result[] = $release;
					break;
				case ESS_PROMOTED:
					$promoted[(int)$release[0]->rid] = $release;
					break;
				default:
					$remainder[(int)$release[0]->rid] = $release;
			}
		}
		$this->cmd_release_fixt = array_merge($result, array_reverse($promoted), array_reverse($remainder));

		// Set the mapping for easy access to releases by type
		$status_mapping = array();
		foreach ($this->cmd_release_fixt as $index => $release) {
			$status_mapping[$release[0]->status] = $index;
		}

		return $status_mapping;
	}

	private function _initialize_triggers() {
		$triggers       = array();
		$this->_max_tid = 0;
		foreach ($this->cmd_trigger_fixt as $trigger) {
			$triggers[$trigger['tid']][$trigger['version']] = (object)$trigger;
			$this->_max_tid                                 = ($trigger['tid'] > $this->_max_tid) ? $trigger['tid'] : $this->_max_tid;
			$this->_max_version['tid'][$trigger['tid']]     = (empty($this->_max_version['tid'][$trigger['tid']]) || $trigger['version'] > $this->_max_version['tid'][$trigger['tid']]) ?
				$trigger['version'] : $this->_max_version['tid'][$trigger['tid']];

			if (empty($this->_has_versions['tid']) && count($triggers[$trigger['tid']]) > 1) {
				$this->_has_versions['tid'] = (object)array(
					'tid' => $trigger['tid']
				);
			}
		}

		$result = array();
		foreach ($triggers as $tid => $trigger) {
			$result[$tid] = array_reverse($trigger);
		}

		return $result;
	}

	private function _initialize_perms() {
		$perms = array();
		foreach ($this->cmd_perm_fixt as $perm) {
			$perms[$perm['pid']][$perm['version']]   = (object)$perm;
			$this->_max_pid                          = ($perm['pid'] > $this->_max_pid) ? $perm['pid'] : $this->_max_pid;
			$this->_max_version['pid'][$perm['pid']] = (empty($this->_max_version['pid'][$perm['pid']]) || $perm['version'] > $this->_max_version['pid'][$perm['pid']]) ?
				$perm['version'] : $this->_max_version['pid'][$perm['pid']];

			if (empty($this->_has_versions['pid']) && count($perms[$perm['pid']]) > 1) {
				$this->_has_versions['pid'] = (object)array(
					'pid' => $perm['pid']
				);
			}
		}

		return $perms;
	}

	private function _initialize_rt_mapping() {
		$mapping = array();
		foreach ($this->cmd_release_trigger_map_fixt as $map) {
			$mapping[$map['rid']][$map['tid']] = (object)array(
				'tid'     => $map['tid'],
				'version' => $map['t_version'],
			);

			if ($this->_has_versions['tid']->tid == $map['tid']) {
				$this->_has_versions['tid']->rid = $map['rid'];
			}
		}

		return $mapping;
	}

	private function _initialize_tp_mapping() {
		$mapping = array();
		foreach ($this->cmd_trigger_perm_map_fixt as $map) {
			$mapping[$map['tid']][$map['t_version']][$map['pid']] = (object)array(
				'pid'     => $map['pid'],
				'version' => $map['p_version'],
			);

			if ($this->_has_versions['pid']->pid == $map['pid']) {
				$this->_has_versions['pid']->tid = $map['tid'];
			}
		}

		return $mapping;
	}

	private function _fetch_release($type) {
		return $this->cmd_release_fixt[$this->_release_status_mapping[$type]][0];
	}

	private function _fetch_release_by_rid($rid) {
		foreach ($this->cmd_release_fixt as $release) {
			$version = reset($release);
			if ($version->rid == $rid) {
				return $version;
			}
		}

		return FALSE;
	}

	private function _fetch_release_from_trigger($tid) {
		foreach ($this->cmd_release_trigger_map_fixt as $rid => $mapping) {
			foreach ($mapping as $map) {
				if ($map->tid == $tid) {
					return $this->_fetch_release_by_rid($rid);
				}
			}
		}

		return FALSE;
	}

	private function _fetch_triggers($rid, $include_deleted = FALSE) {
		$triggers = array();
		$mapping  = $this->cmd_release_trigger_map_fixt[$rid];
		foreach ($mapping as $tid => $map) {
			foreach ($this->cmd_trigger_fixt[$tid] as $trigger_version) {
				if (($include_deleted || $trigger_version->status) && $trigger_version->version == $map->version) {
					$triggers[] = $trigger_version;
				}
			}
		}

		return $triggers;
	}

	private function _fetch_trigger_version($tid, $version) {
		foreach ($this->cmd_trigger_fixt as $trigger_versions) {
			foreach ($trigger_versions as $trigger) {
				if ($trigger->tid == $tid && $trigger->version == $version) {
					return $trigger;
				}
			}
		}

		return FALSE;
	}

	private function _fetch_trigger_by_tid($tid) {
		foreach ($this->cmd_trigger_fixt as $trigger) {
			$version = reset($trigger);
			if ($version->tid == $tid) {
				return $version;
			}
		}

		return FALSE;
	}

	private function _fetch_permissions($trigger) {
		$perms = array();
		if (empty($this->cmd_trigger_perm_map_fixt[$trigger->tid][$trigger->version])) {
			return array();
		}

		$mapping = $this->cmd_trigger_perm_map_fixt[$trigger->tid][$trigger->version];
		foreach ($mapping as $pid => $map) {
			foreach ($this->cmd_perm_fixt[$pid] as $perm_version) {
				if ($perm_version->status && $perm_version->version == $map->version) {
					$perms[] = $perm_version;
				}
			}
		}

		return $perms;
	}

	private function _fetch_permissions_list($rid) {
		$triggers = $this->_fetch_triggers($rid);
		$perms    = array();

		foreach ($triggers as $trigger) {
			$trigger_perms = $this->_fetch_permissions($trigger);
			foreach ($trigger_perms as $perm) {
				$perm->cat     = $trigger->cat;
				$perm->trigger = $trigger->trigger;
				$perm->alias   = $trigger->alias;
				$perm->desc    = $trigger->desc;
				$perm->instr   = $trigger->instr;
				$perm->syntax  = $trigger->syntax;
				$perm->tid     = $trigger->tid;

				$perms[] = $perm;
			}
		}

		return $perms;
	}

	private function _login() {
		$user = $this->cmd_user_fixt[0];
		$this->_cm->authenticate($user->username, $user->password);
		$_COOKIE['cmd_session'] = $user;
	}

	private function _build_search_result($trigger) {
		$perms   = $this->_fetch_permissions($trigger);
		$trigger = (object)array(
			'trigger' => $trigger->trigger,
			'alias'   => $trigger->alias,
			'desc'    => $trigger->desc,
			'instr'   => $trigger->instr,
			'syntax'  => $trigger->syntax,
			'perms'   => array(),
		);

		foreach ($perms as $perm) {
			$trigger->perms[] = (object)array(
				'perm'  => $perm->perm,
				'pdesc' => $perm->pdesc,
			);
		}

		return array($trigger);
	}

	private function _build_perm_search_result($perm) {
		$result = (object)array(
			'perm'    => $perm->perm,
			'pdesc'   => $perm->pdesc,
			'trigger' => $perm->trigger,
			'alias'   => $perm->alias,
			'desc'    => $perm->desc,
			'instr'   => $perm->instr,
			'syntax'  => $perm->syntax,
		);

		return array(
			$result,
		);
	}

	public function testAbilityLoginValidCredentials() {
		$user = $this->cmd_user_fixt[0];
		$this->assertTrue($this->_cm->authenticate($user->username, $user->password));
	}

	public function testInabilityLoginInvalidCredentials() {
		$this->assertFalse($this->_cm->authenticate('invalid', 'user'));
	}

	public function testLoginSetsSession() {
		$user = $this->cmd_user_fixt[0];
		$this->_cm->authenticate($user->username, $user->password);

		$expected = (object)array(
			'uid'      => $user->uid,
			'username' => $user->username,
			'email'    => $user->email,
		);

		$this->assertEquals($expected, $this->CI->session->userdata('user'));
	}

	public function testAbilityLogoutLoggedIn() {
		$this->_login();

		$this->_cm->logout();
		$this->assertFalse($this->CI->session->userdata('user'));
	}

	public function testAbilityLogoutNotLoggedIn() {
		$this->_cm->logout();

		$this->assertFalse($this->CI->session->userdata('user'));
	}

	public function testIsAuthenticatedLoggedIn() {
		$this->_login();

		$this->assertTrue($this->_cm->is_authenticated());
	}

	public function testIsAuthenticatedLoggedOut() {
		$this->_cm->logout();
		$this->assertFalse($this->_cm->is_authenticated());
	}

	public function testAbilityFetchReleaseListAnonymous() {
		$expected         = array();
		$published_status = array(
			ESS_PUBLISHED,
			ESS_PROMOTED,
			ESS_DEFAULT,
		);
		foreach ($this->_release_status_mapping as $status => $index) {
			if (in_array($status, $published_status)) {
				$expected[] = reset($this->cmd_release_fixt[$index]);
			}
		}
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_releases());
	}

	public function testAbilityFetchReleaseListAuthenticated() {
		$expected = array();
		foreach ($this->_release_status_mapping as $status => $index) {
			if ($status != ESS_DELETED) {
				$expected[] = reset($this->cmd_release_fixt[$index]);
			}
		}

		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_releases());
	}

	public function testAbilityFetchPublishedReleaseAnonymous() {
		$expected = $this->_fetch_release(ESS_PUBLISHED);
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_release($expected->rid));
	}

	public function testAbilityFetchPublishedReleaseAuthenticated() {
		$expected = $this->_fetch_release(ESS_PUBLISHED);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_release($expected->rid));
	}

	public function testInabilityFetchUnpublishedReleaseAnonymous() {
		$expected = $this->_fetch_release(ESS_UNPUBLISHED);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_release($expected->rid));
	}

	public function testAbilityFetchUnpublishedReleaseAuthenticated() {
		$expected = $this->_fetch_release(ESS_UNPUBLISHED);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_release($expected->rid));
	}

	public function testInabilityFetchDeletedReleaseAnonymous() {
		$expected = $this->_fetch_release(ESS_DELETED);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_release($expected->rid));
	}

	public function testInabilityFetchDeletedReleaseAuthenticated() {
		$expected = $this->_fetch_release(ESS_DELETED);
		$this->_login();

		$this->assertFalse($this->_cm->fetch_release($expected->rid));
	}

	public function testAbilityFetchTriggerListPublishedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$expected = $this->_fetch_triggers($release->rid);
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_triggers($release->rid));
	}

	public function testAbilityFetchTriggerListPublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$expected = $this->_fetch_triggers($release->rid);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_triggers($release->rid));
	}

	public function testInabilityFetchTriggerListUnpublishedAnonymous() {
		$release = $this->_fetch_release(ESS_UNPUBLISHED);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_triggers($release->rid));
	}

	public function testAbilityFetchTriggerListUnpublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$expected = $this->_fetch_triggers($release->rid);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_triggers($release->rid));
	}

	public function testInabilityFetchTriggerListDeletedAnonymous() {
		$release = $this->_fetch_release(ESS_DELETED);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_triggers($release->rid));
	}

	public function testInabilityFetchTriggerListDeletedAuthenticated() {
		$release = $this->_fetch_release(ESS_DELETED);
		$this->_login();

		$this->assertFalse($this->_cm->fetch_triggers($release->rid));
	}

	public function testAbilityFetchTriggerDetailsAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = array(
			'trigger'     => $trigger,
			'permissions' => $this->_fetch_permissions($trigger),
		);

		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testAbilityFetchTriggerDetailsAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = array(
			'trigger'     => $trigger,
			'permissions' => $this->_fetch_permissions($trigger),
		);

		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerDetailsUnpublishedReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_UNPUBLISHED);
		$trigger = reset($this->_fetch_triggers($release->rid));

		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testAbilityFetchTriggerDetailsUnpublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = array(
			'trigger'     => $trigger,
			'permissions' => $this->_fetch_permissions($trigger),
		);

		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerDetailsDeletedReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_DELETED);
		$trigger = reset($this->_fetch_triggers($release->rid, TRUE));

		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerDetailsDeletedReleaseAuthorized() {
		$release = $this->_fetch_release(ESS_DELETED);
		$trigger = reset($this->_fetch_triggers($release->rid, TRUE));

		$this->_login();

		$this->assertFalse($this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testAbilityFetchPermissionsListByReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$expected = $this->_fetch_permissions_list($release->rid);
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid));
	}

	public function testAbilityFetchPermissionsListByReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$expected = $this->_fetch_permissions_list($release->rid);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid));
	}

	public function testInabilityFetchPermissionsListByReleaseUnpublishedReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_UNPUBLISHED);

		$this->assertFalse($this->_cm->fetch_permissions($release->rid));
	}

	public function testAbilityFetchPermissionsListByReleaseUnpublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$expected = $this->_fetch_permissions_list($release->rid);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid));
	}

	public function testInabilityFetchPermissionsListByReleaseDeletedReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_DELETED);

		$this->assertFalse($this->_cm->fetch_permissions($release->rid));
	}

	public function testInabilityFetchPermissionsListByReleaseDeletedReleaseAuthenticated() {
		$release = $this->_fetch_release(ESS_DELETED);
		$this->_login();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid));
	}

	public function testAbilityFetchTriggerPermissionsPublishedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = $this->_fetch_permissions($trigger);
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testAbilityFetchTriggerPermissionsPublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = $this->_fetch_permissions($trigger);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerPermissionsUnpublishedReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_UNPUBLISHED);
		$trigger = reset($this->_fetch_triggers($release->rid));
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testAbilityFetchTriggerPermissionsUnpublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = $this->_fetch_permissions($trigger);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerPermissionsDeletedReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_DELETED);
		$trigger = reset($this->_fetch_triggers($release->rid, TRUE));
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerPermissionsDeletedReleaseAuthenticated() {
		$release = $this->_fetch_release(ESS_DELETED);
		$trigger = reset($this->_fetch_triggers($release->rid, TRUE));
		$this->_login();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityCloneReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$new_name = 'New Release';
		$this->_cm->logout();

		$this->assertFalse($this->_cm->clone_release($release->rid, array('name' => $new_name)));
	}

	public function testAbilityCloneReleaseAuthenticated() {
		$release        = $this->_fetch_release(ESS_PUBLISHED);
		$clone          = clone $release;
		$clone->rid     = $this->_max_rid + 1;
		$clone->name    = 'New Release';
		$clone->version = 1;
		$clone->status  = ESS_UNPUBLISHED;

		$triggers = $this->_fetch_triggers($release->rid);

		$this->_login();

		$this->assertEquals($clone, $this->_cm->clone_release($release->rid, array('name' => $clone->name)));
		$this->assertEquals(count($triggers), count($this->_cm->fetch_triggers($clone->rid)));
	}

	public function testAbilityCloneUnpublishedReleaseAuthenticated() {
		$release        = $this->_fetch_release(ESS_UNPUBLISHED);
		$clone          = clone $release;
		$clone->rid     = $this->_max_rid + 1;
		$clone->name    = 'New Release';
		$clone->version = 1;
		$clone->status  = ESS_UNPUBLISHED;

		$triggers = $this->_fetch_triggers($release->rid);

		$this->_login();

		$this->assertEquals($clone, $this->_cm->clone_release($release->rid, array('name' => $clone->name)));
		$this->assertEquals(count($triggers), count($this->_cm->fetch_triggers($clone->rid)));
	}

	public function testInabilityCloneDeletedRelease() {
		$release = $this->_fetch_release(ESS_DELETED);
		$this->_login();

		$this->assertFalse($this->_cm->clone_release($release->rid, array('name' => 'New Name')));
		$this->assertFalse($this->_cm->fetch_release($this->_max_rid + 1));
	}

	public function testInabilityEditReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$this->assertFalse($this->_cm->edit_release($release->rid, array('name' => 'New Name')));
	}

	public function testAbilityEditReleaseAuthenticated() {
		$release           = $this->_fetch_release(ESS_PUBLISHED);
		$expected          = clone $release;
		$expected->version = $this->_max_version['rid'][$release->rid] + 1;
		$expected->name    = 'Edited Name';

		$this->_login();

		$this->assertEquals($expected, $this->_cm->edit_release($release->rid, array('name' => $expected->name)));
	}

	public function testAbilityEditUnpublishedRelease() {
		$release           = $this->_fetch_release(ESS_UNPUBLISHED);
		$expected          = clone $release;
		$expected->version = $this->_max_version['rid'][$release->rid] + 1;
		$expected->name    = 'Edited Name';

		$this->_login();

		$this->assertEquals($expected, $this->_cm->edit_release($release->rid, array('name' => $expected->name)));
	}

	public function testInabilityEditDeletedRelease() {
		$release = $this->_fetch_release(ESS_DELETED);
		$this->_login();

		$this->assertFalse($this->_cm->edit_release($release->rid, array('name' => 'Edited Name')));
	}

	public function testInabilityChangeReleaseStatusAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->update_release_status($release->rid, ESS_PROMOTED));
	}

	public function testAbilityChangeReleaseStatusAuthenticated() {
		$release          = $this->_fetch_release(ESS_PUBLISHED);
		$expected         = clone $release;
		$expected->status = ESS_PROMOTED;

		$this->_login();

		$this->assertEquals($expected, $this->_cm->update_release_status($release->rid, $expected->status));
		$this->assertEquals($expected, $this->_cm->fetch_release($expected->rid));
	}

	public function testInabilityChangeReleaseStatusDeletedRelease() {
		$release = $this->_fetch_release(ESS_DELETED);
		$this->_login();

		$this->assertFalse($this->_cm->update_release_status($release->rid, ESS_PUBLISHED));
	}

	public function testInabilityDeleteReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->delete_release($release->rid));
	}

	public function testAbilityDeleteReleaseAuthenticated() {
		$release         = $this->_fetch_release(ESS_PUBLISHED);
		$release->status = ESS_DELETED;

		$this->_login();

		$this->assertEquals($release, $this->_cm->delete_release($release->rid));
		$this->assertFalse($this->_cm->fetch_release($release->rid));
	}

	public function testInabilityFetchReleaseVersionsAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_release_versions($release->rid));
	}

	public function testAbilityFetchReleaseVersionsAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$versions = $this->cmd_release_fixt[$this->_release_status_mapping[ESS_PUBLISHED]];
		$expected = array();
		foreach ($versions as $version) {
			$expected[] = $version;
		}
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_release_versions($release->rid));
	}

	public function testInabilityRevertReleaseAnonymous() {
		$version = reset($this->cmd_release_fixt[$this->_release_status_mapping[ESS_PUBLISHED]]);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->revert_release($version->rid, $version->version));
	}

	public function testAbilityRevertReleaseAuthenticated() {
		$version           = end($this->cmd_release_fixt[$this->_release_status_mapping[$this->_has_versions['rid']]]);
		$expected          = clone $version;
		$expected->version = $this->_max_version['rid'][$version->rid] + 1;

		$this->_login();

		$this->assertEquals($expected, $this->_cm->revert_release($version->rid, $version->version));
	}

	public function testInabilityCreateNewTriggerAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = array(
			'cat'     => 'General',
			'trigger' => 'trigger',
			'alias'   => 'alias',
			'desc'    => 'Some description',
			'instr'   => 'Some instructions',
			'syntax'  => 'Some syntax',
		);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->create_trigger($release->rid, $trigger));
	}

	public function testAbilityCreateNewTriggerAuthenticated() {
		$release           = $this->_fetch_release(ESS_PUBLISHED);
		$trigger           = array(
			'cat'     => 'General',
			'trigger' => 'trigger',
			'alias'   => 'alias',
			'desc'    => 'Some description',
			'instr'   => 'Some instructions',
			'syntax'  => 'Some syntax',
		);
		$expected          = (object)$trigger;
		$expected->tid     = $this->_max_tid + 1;
		$expected->version = 1;
		$this->_login();

		$this->assertEquals($expected, $this->_cm->create_trigger($release->rid, $trigger));
	}

	public function testInabilityEditTriggerAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = reset($this->_cm->fetch_triggers($release->rid));
		$data    = array(
			'trigger' => 'New Value',
		);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->edit_trigger($release->rid, $trigger->tid, $data));
	}

	public function testAbilityEditTriggerAuthenticated() {
		$release           = $this->_fetch_release(ESS_PUBLISHED);
		$trigger           = reset($this->_cm->fetch_triggers($release->rid));
		$data              = array(
			'trigger' => 'Edited Value',
		);
		$expected          = clone $trigger;
		$expected->version = $this->_max_version['tid'][$trigger->tid] + 1;
		$expected->trigger = 'Edited Value';

		$this->_login();

		$this->assertEquals($expected, $this->_cm->edit_trigger($release->rid, $trigger->tid, $data));
		$trigger_details = $this->_cm->fetch_trigger_details($release->rid, $trigger->tid);
		$this->assertEquals($expected, $trigger_details['trigger']);
	}

	public function testEditingTriggerUpdatesMapping() {
		$release                  = $this->_fetch_release(ESS_PUBLISHED);
		$trigger                  = reset($this->_cm->fetch_triggers($release->rid));
		$data                     = array(
			'trigger' => 'Edited Value',
		);
		$updated_trigger          = clone $trigger;
		$updated_trigger->version = $this->_max_version['tid'][$trigger->tid] + 1;
		$expected                 = $this->_fetch_permissions($trigger);
		$this->_login();
		$this->_cm->edit_trigger($release->rid, $trigger->tid, $data);

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityDeleteTriggerAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = reset($this->_cm->fetch_triggers($release->rid));
		$this->_cm->logout();

		$this->assertFalse($this->_cm->delete_trigger($release->rid, $trigger->tid));
	}

	public function testAbilityDeleteTriggerAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$deleted  = array_shift($triggers);
		$this->_login();

		$this->assertEquals($deleted, $this->_cm->delete_trigger($release->rid, $deleted->tid));
		$this->assertEquals($triggers, $this->_cm->fetch_triggers($release->rid));
	}

	public function testDeletingTriggerUpdatesMapping() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$deleted  = array_shift($triggers);
		$this->_login();
		$this->_cm->delete_trigger($release->rid, $deleted->tid);

		$this->assertEmpty($this->_cm->fetch_permissions($release->rid,
		                                                 array('tid'       => $deleted->tid,
		                                                       't_version' => $deleted->version)));
	}

	public function testInabilityRevertTriggerAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = reset($this->_cm->fetch_triggers($release->rid));
		$this->_cm->logout();

		$this->assertFalse($this->_cm->revert_trigger($release->rid, $trigger->tid, 1));
	}

	public function testAbilityRevertTriggerAuthenticated() {
		$trigger           = end($this->cmd_trigger_fixt[$this->_has_versions['tid']->tid]);
		$release           = $this->_fetch_release_by_rid($this->_has_versions['tid']->rid);
		$expected          = clone $trigger;
		$expected->version = $this->_max_version['tid'][$trigger->tid] + 1;
		$this->_login();

		$this->assertEquals($expected, $this->_cm->revert_trigger($release->rid, $trigger->tid, $trigger->version));
	}

	public function testRevertingTriggerUpdatesMapping() {
		$trigger  = end($this->cmd_trigger_fixt[$this->_has_versions['tid']->tid]);
		$original = reset($this->cmd_trigger_fixt[$this->_has_versions['tid']->tid]);
		$release  = $this->_fetch_release_by_rid($this->_has_versions['tid']->rid);
		$expected = $this->_fetch_permissions($original);
		$this->_login();
		$reverted = $this->_cm->revert_trigger($release->rid, $trigger->tid, $trigger->version);

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $reverted->tid));
	}

	public function testInabilityCreateNewPermissionAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = reset($this->_cm->fetch_triggers($release->rid));
		$data    = array(
			'perm'  => 'perm',
			'pdesc' => 'Description',
		);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->create_permission($release->rid, $trigger->tid, $data));
	}

	public function testAbilityCreateNewPermissionAuthenticated() {
		$release           = $this->_fetch_release(ESS_PUBLISHED);
		$trigger           = reset($this->_cm->fetch_triggers($release->rid));
		$data              = array(
			'perm'  => 'perm',
			'pdesc' => 'Description',
		);
		$expected          = (object)$data;
		$expected->pid     = $this->_max_pid + 1;
		$expected->version = 1;
		$expected->status  = 1;

		$this->_login();

		$this->assertEquals($expected, $this->_cm->create_permission($release->rid, $trigger->tid, $data));
	}

	public function testInabilityEditPermissionAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = reset($this->_cm->fetch_triggers($release->rid));
		$perm    = reset($this->_fetch_permissions($trigger));
		$this->_cm->logout();

		$this->assertFalse($this->_cm->edit_permission($release->rid,
		                                               $trigger->tid,
		                                               $perm->pid,
		                                               array('perm' => 'editedPerm')));
	}

	public function testAbilityEditPermissionAuthenticated() {
		$release       = $this->_fetch_release(ESS_PUBLISHED);
		$trigger       = reset($this->_cm->fetch_triggers($release->rid));
		$perm          = reset($this->_fetch_permissions($trigger));
		$perm->version = $this->_max_version['pid'][$perm->pid] + 1;
		$perm->perm    = 'editedPerm';

		$this->_login();

		$this->assertEquals($perm,
		                    $this->_cm->edit_permission($release->rid,
		                                                $trigger->tid,
		                                                $perm->pid,
		                                                array('perm' => $perm->perm)));
	}

	public function testEditingPermissionUpdatesMapping() {
		$release       = $this->_fetch_release(ESS_PUBLISHED);
		$trigger       = reset($this->_cm->fetch_triggers($release->rid));
		$perms         = $this->_fetch_permissions($trigger);
		$perm          = reset($perms);
		$perm->version = $this->_max_version['pid'][$perm->pid] + 1;
		$perm->perm    = 'editedPerm';

		$this->_login();

		$this->_cm->edit_permission($release->rid,
		                            $trigger->tid,
		                            $perm->pid,
		                            array('perm' => $perm->perm));
		$this->assertEquals($perms, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityDeletePermissionAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = reset($this->_cm->fetch_triggers($release->rid));
		$perm    = reset($this->_fetch_permissions($trigger));

		$this->_cm->logout();

		$this->assertFalse($this->_cm->delete_permission($release->rid, $trigger->tid, $perm->pid));
	}

	public function testAbilityDeletePermissionAuthenticated() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$trigger = reset($this->_cm->fetch_triggers($release->rid));
		$perms   = $this->_fetch_permissions($trigger);
		$perm    = array_shift($perms);

		$this->_login();

		$this->assertEquals($perm, $this->_cm->delete_permission($release->rid, $trigger->tid, $perm->pid));
		$this->assertEquals($perms, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityRevertPermissionAnonymous() {
		$perm    = end($this->cmd_perm_fixt[$this->_has_versions['pid']->pid]);
		$trigger = $this->_fetch_trigger_by_tid($this->_has_versions['pid']->tid);
		$release = $this->_fetch_release_from_trigger($trigger->tid);

		$expected          = clone $perm;
		$expected->version = $this->_max_version['pid'][$perm->pid] + 1;
		$this->_cm->logout();

		$this->assertFalse($this->_cm->revert_permission($release->rid, $trigger->tid, $perm->pid, $perm->version));
	}

	public function testAbilityRevertPermissionAuthenticated() {
		$perm    = end($this->cmd_perm_fixt[$this->_has_versions['pid']->pid]);
		$trigger = $this->_fetch_trigger_by_tid($this->_has_versions['pid']->tid);
		$release = $this->_fetch_release_from_trigger($trigger->tid);

		$expected          = clone $perm;
		$expected->version = $this->_max_version['pid'][$perm->pid] + 1;
		$this->_login();

		$this->assertEquals($expected,
		                    $this->_cm->revert_permission($release->rid, $trigger->tid, $perm->pid, $perm->version));
	}

	public function testRevertingPermissionUpdatesMapping() {
		$perm     = end($this->cmd_perm_fixt[$this->_has_versions['pid']->pid]);
		$trigger  = $this->_fetch_trigger_by_tid($this->_has_versions['pid']->tid);
		$release  = $this->_fetch_release_from_trigger($trigger->tid);
		$expected = $this->_fetch_permissions($trigger);

		$this->_login();

		$result = $this->_cm->revert_permission($release->rid, $trigger->tid, $perm->pid, $perm->version);

		foreach ($expected as $index => $perm) {
			if ($perm->pid == $result->pid) {
				$expected[$index] = $result;
				break;
			}
		}

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testSearchReturnsMatchingTrigger() {
		$release  = $this->_fetch_release(ESS_DEFAULT);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = $this->_build_search_result($trigger);

		$this->assertEquals($expected, $this->_cm->search($trigger->trigger));
	}

	public function testSearchReturnsMatchingAlias() {
		$release  = $this->_fetch_release(ESS_DEFAULT);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = $this->_build_search_result($trigger);

		$this->assertEquals($expected, $this->_cm->search($trigger->alias));
	}

	public function testSearchReturnsMatchingInstrAndDesc() {
		$release  = $this->_fetch_release(ESS_DEFAULT);
		$source_trigger  = reset($this->_fetch_triggers($release->rid));
		$instr_expected = array();
		$syntax_expected = array();

		foreach($this->_fetch_triggers($release->rid) as $trigger) {

			if(stripos($trigger->instr, $source_trigger->instr) !== FALSE) {
				$instr_expected += $this->_build_search_result($trigger);
			}

			if(stripos($trigger->desc, $source_trigger->desc) !== FALSE) {
				$syntax_expected += $this->_build_search_result($trigger);
			}
		}

		var_dump($source_trigger);
		$this->assertEquals($instr_expected, $this->_cm->search($source_trigger->instr));
		$this->assertEquals($syntax_expected, $this->_cm->search($source_trigger->desc));
	}

	public function testSearchPartialReturnsMultipleResults() {
		$release     = $this->_fetch_release(ESS_DEFAULT);
		$triggers    = $this->_fetch_triggers($release->rid);
		$trigger     = reset($triggers);
		$search_term = substr($trigger->trigger, 0, 3);
		$expected    = array();
		foreach ($triggers as $trigger) {
			if (count($expected) == MAX_SEARCH_RESULTS) {
				break;
			}

			if (strpos($trigger->trigger, $search_term) !== FALSE
			    || strpos($trigger->alias, $search_term) !== FALSE
			    || strpos($trigger->instr, $search_term) !== FALSE
			    || strpos($trigger->syntax, $search_term) !== FALSE
			) {
				$expected += $this->_build_search_result($trigger);
			}
		}

		$this->assertEquals($expected, $this->_cm->search($search_term));
	}

	public function testSearchPermissionMatchingPerm() {
		$release  = $this->_fetch_release(ESS_DEFAULT);
		$perm     = reset($this->_fetch_permissions_list($release->rid));
		$expected = $this->_build_perm_search_result($perm);

		$this->assertEquals($expected, $this->_cm->search($perm->perm, '', 'perm'));
	}

	public function testSearchPermissionPartialReturnsMultipleResults() {
		$release     = $this->_fetch_release(ESS_DEFAULT);
		$perms       = $this->_fetch_permissions_list($release->rid);
		$perm        = reset($perms);
		$search_term = substr($perm->perm, 0, 3);
		$expected    = array();

		foreach ($perms as $perm) {
			if (count($expected) == MAX_SEARCH_RESULTS) {
				break;
			}

			if (strpos($perm->perm, $search_term) !== FALSE
			    || strpos($perm->pdesc, $search_term) !== FALSE
			) {
				$expected = array_merge($expected, $this->_build_perm_search_result($perm));
			}
		}

		$this->assertEquals($expected, $this->_cm->search($search_term, '', 'perm'));
	}

	public function testSearchTriggerSpecificReleaseValid() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$trigger  = reset($this->_fetch_triggers($release->rid));
		$expected = $this->_build_search_result($trigger);

		$this->assertEquals($expected, $this->_cm->search($trigger->trigger, $release->name));
	}

	public function testSearchTriggerSpecificReleaseInvalidReturnsEmptyResult() {
		$release = $this->_fetch_release(ESS_DEFAULT);
		$trigger = reset($this->_fetch_triggers($release->rid));

		$this->assertEmpty($this->_cm->search($trigger->trigger, 'Unknown Release'));
	}

	public function testSearchPermSpecificReleaseValid() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$perm     = reset($this->_fetch_permissions_list($release->rid));
		$expected = $this->_build_perm_search_result($perm);

		$this->assertEquals($expected, $this->_cm->search($perm->perm, $release->name, 'perm'));
	}

	public function testSearchPermSpecificReleaseInvalidReturnsEmptyResult() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$perm    = reset($this->_fetch_permissions_list($release->rid));

		$this->assertEmpty($this->_cm->search($perm->perm, 'Unknown Release', 'perm'));
	}

	public function testSearchTriggerSpecificReleaseUnpublishedReturnsEmptyResult() {
		$release = $this->_fetch_release(ESS_UNPUBLISHED);
		$trigger = reset($this->_fetch_triggers($release->rid));

		$this->assertEmpty($this->_cm->search($trigger->trigger, $release->name));
	}

	public function testInabilityPerformBlankSearch() {
		$this->assertFalse($this->_cm->search(''));
	}
}
