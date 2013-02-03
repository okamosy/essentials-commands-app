<?php

/**
 * @group Model
 */

class ControlModelTest extends CIUnit_TestCase
{
	private $_cm;
	private $_max_tid;
	private $_max_pid;
	private $_max_version;

	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	public function setUp() {
		parent::tearDown();
		parent::setUp();

		$this->CI->load->model('Command_model');
		$this->_cm = $this->CI->Command_model;
		$this->dbfixt('cmd_user', 'cmd_release', 'cmd_trigger', 'cmd_release_trigger_map',
		              'cmd_perm', 'cmd_trigger_perm_map');

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
			$this->CI->db->update('cmd_user', array('password' => md5($user['password'])), array('username' => $user['username']));
		}

		return $users;
	}

	private function _initialize_releases() {
		$releases = array();
		// group releases together and sort by version
		foreach (array_reverse($this->cmd_release_fixt) as $release) {
			$releases[$release['rid']][] = (object)$release;
		}

		$default                = NULL;
		$promoted               = array();
		$this->cmd_release_fixt = array();
		foreach ($releases as $release) {
			if ($release[0]->status == ESS_DEFAULT) {
				$default = $release;
			} elseif ($release[0]->status == ESS_PROMOTED) {
				$promoted[] = $release;
			} else {
				$this->cmd_release_fixt[] = $release;
			}
		}

		if (!empty($promoted)) {
			$this->cmd_release_fixt = array_merge($promoted, $this->cmd_release_fixt);
		}

		if (!empty($default)) {
			array_unshift($this->cmd_release_fixt, $default);
		}

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
		}

		return $triggers;
	}

	private function _initialize_perms() {
		$perms = array();
		foreach ($this->cmd_perm_fixt as $perm) {
			$perms[$perm['pid']][$perm['version']]   = (object)$perm;
			$this->_max_pid                          = ($perm['pid'] > $this->_max_pid) ? $perm['pid'] : $this->_max_pid;
			$this->_max_version['pid'][$perm['pid']] = (empty($this->_max_version['pid'][$perm['pid']]) || $perm['version'] > $this->_max_version['pid'][$perm['pid']]) ?
				$perm['version'] : $this->_max_version['pid'][$perm['pid']];
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
		}

		return $mapping;
	}

	private function _fetch_release($type) {
		return $this->cmd_release_fixt[$this->_release_status_mapping[$type]][0];
	}

	private function _fetch_triggers($release) {
		$triggers = array();
		$mapping  = $this->cmd_release_trigger_map_fixt[$release];
		foreach ($this->cmd_trigger_fixt as $trigger) {
			foreach ($trigger as $version) {
				$map = empty($mapping[$version->tid]) ? array() : $mapping[$version->tid];
				if (!empty($map) && $version->tid == $map->tid && $version->version == $map->version) {
					$triggers[] = $version;
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

	private function _fetch_permissions($trigger) {
		$perms = array();
		if (empty($this->cmd_trigger_perm_map_fixt[$trigger->tid][$trigger->version])) {
			return array();
		}

		$map = $this->cmd_trigger_perm_map_fixt[$trigger->tid][$trigger->version];
		foreach ($this->cmd_perm_fixt as $perm) {
			foreach ($perm as $version) {
				if (!empty($map[$version->pid]) && $version->pid == $map[$version->pid]->pid && $version->version == $map[$version->pid]->version) {
					$perms[] = $version;
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
				$perm->tid = $trigger->tid;

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
				$expected[] = $this->cmd_release_fixt[$index][0];
			}
		}
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_releases());
	}

	public function testAbilityFetchReleaseListAuthenticated() {
		$expected = array();
		foreach ($this->_release_status_mapping as $status => $index) {
			if ($status != ESS_DELETED) {
				$expected[] = $this->cmd_release_fixt[$index][0];
			}
		}

		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_releases());
	}

	public function testAbilityFetchPublishedReleaseAnonymouse() {
		$expected = $this->_fetch_release(ESS_PUBLISHED);
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_release($expected->rid));
	}

	public function testAbilityFetchpublishedReleaseAuthenticated() {
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
		$triggers = $this->_fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$expected = array(
			'trigger'     => $trigger,
			'permissions' => $this->_fetch_permissions($trigger),
		);

		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testAbilityFetchTriggerDetailsAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$expected = array(
			'trigger'     => $trigger,
			'permissions' => $this->_fetch_permissions($trigger),
		);

		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerDetailsUnpublishedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$triggers = $this->_fetch_triggers($release->rid);
		$trigger  = $triggers[0];

		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testAbilityFetchTriggerDetailsUnpublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$triggers = $this->_fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$expected = array(
			'trigger'     => $trigger,
			'permissions' => $this->_fetch_permissions($trigger),
		);

		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerDetailsDeletedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_DELETED);
		$triggers = $this->_fetch_triggers($release->rid);
		$trigger  = $triggers[0];

		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_trigger_details($release->rid, $trigger->tid));
	}

	public function testInabilityFetchTriggerDetailsDeletedReleaseAuthorized() {
		$release  = $this->_fetch_release(ESS_DELETED);
		$triggers = $this->_fetch_triggers($release->rid);
		$trigger  = $triggers[0];

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
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);

		$this->assertFalse($this->_cm->fetch_permissions($release->rid));
	}

	public function testAbilityFetchPermissionsListByReleaseUnpublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$expected = $this->_fetch_permissions_list($release->rid);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid));
	}

	public function testInabilityFetchPermissionsListByReleaseDeletedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_DELETED);

		$this->assertFalse($this->_cm->fetch_permissions($release->rid));
	}

	public function testInabilityFetchPermissionsListByReleaseDeletedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_DELETED);
		$this->_login();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid));
	}

	public function testAbilityFetchTriggerPermissionsPublishedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_fetch_triggers($release->rid);
		$expected = $this->_fetch_permissions($triggers[0]);
		$this->_cm->logout();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $triggers[0]->tid));
	}

	public function testAbilityFetchTriggerPermissionsPublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_fetch_triggers($release->rid);
		$expected = $this->_fetch_permissions($triggers[0]);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $triggers[0]->tid));
	}

	public function testInabilityFetchTriggerPermissionsUnpublishedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$triggers = $this->_fetch_triggers($release->rid);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid, $triggers[0]->tid));
	}

	public function testAbilityFetchTriggerPermissionsUnpublishedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$triggers = $this->_fetch_triggers($release->rid);
		$expected = $this->_fetch_permissions($triggers[0]);
		$this->_login();

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $triggers[0]->tid));
	}

	public function testInablityFetchTriggerPermissionsDeletedReleaseAnonymous() {
		$release  = $this->_fetch_release(ESS_DELETED);
		$triggers = $this->_fetch_triggers($release->rid);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid, $triggers[0]->tid));
	}

	public function testInabilityFetchTriggerPermissionsDeletedReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_DELETED);
		$triggers = $this->_fetch_triggers($release->rid);
		$this->_login();

		$this->assertFalse($this->_cm->fetch_permissions($release->rid, $triggers[0]->tid));
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
		$clone->rid     = count($this->cmd_release_fixt) + 1;
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
		$clone->rid     = count($this->cmd_release_fixt) + 1;
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
	}

	public function testInabilityEditReleaseAnonymous() {
		$release = $this->_fetch_release(ESS_PUBLISHED);
		$this->assertFalse($this->_cm->edit_release($release->rid, array('name' => 'New Name')));
	}

	public function testAbilityEditReleaseAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$expected = clone $release;
		$expected->version++;
		$expected->name = 'Edited Name';

		$this->_login();

		$this->assertEquals($expected, $this->_cm->edit_release($release->rid, array('name' => $expected->name)));
	}

	public function testAbilityEditUnpublishedRelease() {
		$release  = $this->_fetch_release(ESS_UNPUBLISHED);
		$expected = clone $release;
		$expected->version++;
		$expected->name = 'Edited Name';

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

		$this->assertTrue($this->_cm->update_release_status($release->rid, $expected->status));
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
		$versions = $this->cmd_release_fixt[$this->_release_status_mapping[ESS_PUBLISHED]];
		$this->_cm->logout();

		$this->assertFalse($this->_cm->revert_release($versions[0]->rid, $versions[0]->version));
	}

	public function testAbilityRevertReleaseAuthenticated() {
		$versions          = $this->cmd_release_fixt[$this->_release_status_mapping[ESS_PUBLISHED]];
		$expected          = clone $versions[1];
		$expected->version = $versions[0]->version + 1;

		$this->_login();

		$this->assertEquals($expected, $this->_cm->revert_release($versions[1]->rid, $versions[1]->version));
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
		$triggers          = $this->_cm->fetch_triggers($release->rid);
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
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$data     = array(
			'trigger' => 'New Value',
		);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->edit_trigger($release->rid, $triggers[0]->tid, $data));
	}

	public function testAbilityEditTriggerAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$data     = array(
			'trigger' => 'Edited Value',
		);
		$expected = clone $trigger;
		$expected->version++;
		$expected->trigger = 'Edited Value';

		$this->_login();

		$this->assertEquals($expected, $this->_cm->edit_trigger($release->rid, $trigger->tid, $data));
	}

	public function testEditingTriggerUpdatesMapping() {
		$release         = $this->_fetch_release(ESS_PUBLISHED);
		$triggers        = $this->_cm->fetch_triggers($release->rid);
		$trigger         = $triggers[0];
		$data            = array(
			'trigger' => 'Edited Value',
		);
		$updated_trigger = clone $trigger;
		$updated_trigger->version++;
		$expected = $this->_fetch_permissions($trigger);
		$this->_login();
		$this->_cm->edit_trigger($release->rid, $trigger->tid, $data);

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $updated_trigger->tid));
	}

	public function testInabilityDeleteTriggerAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->delete_trigger($release->rid, $triggers[0]->tid));
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

		$this->assertEmpty($this->_cm->fetch_permissions($release->rid, array('tid' => $deleted->tid, 't_version' => $deleted->version)));
	}

	public function testInabilityRevertTriggerAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$this->_cm->logout();

		$this->assertFalse($this->_cm->revert_trigger($release->rid, $trigger->tid, 1));
	}

	public function testAbilityRevertTriggerAuthenticated() {
		$release           = $this->_fetch_release(ESS_PUBLISHED);
		$triggers          = $this->_cm->fetch_triggers($release->rid);
		$trigger           = $triggers[0];
		$expected          = $this->_fetch_trigger_version($trigger->tid, $trigger->version - 1);
		$expected->version = $this->_max_version['tid'][$trigger->tid] + 1;
		$this->_login();

		$this->assertEquals($expected, $this->_cm->revert_trigger($release->rid, $trigger->tid, $trigger->version - 1));
	}

	public function testRevertingTriggerUpdatesMapping() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$expected = $this->_fetch_permissions($trigger);
		$this->_login();
		$reverted = $this->_cm->revert_trigger($release->rid, $trigger->tid, $trigger->version - 1);

		$this->assertEquals($expected, $this->_cm->fetch_permissions($release->rid, $reverted->tid));
	}

	public function testInabilityCreateNewPermissionAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$data     = array(
			'perm'  => 'perm',
			'pdesc' => 'Description',
		);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->create_permission($release->rid, $trigger->tid, $data));
	}

	public function testAbilityCreateNewPermissionAuthenticated() {
		$release           = $this->_fetch_release(ESS_PUBLISHED);
		$triggers          = $this->_cm->fetch_triggers($release->rid);
		$trigger           = $triggers[0];
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
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$perms    = $this->_fetch_permissions($trigger);
		$this->_cm->logout();

		$this->assertFalse($this->_cm->edit_permission($release->rid, $trigger->tid, $perms[0]->pid, array('perm' => 'editedPerm')));
	}

	public function testAbilityEditPermissionAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$perms    = $this->_fetch_permissions($trigger);
		$perm     = clone $perms[0];
		$perm->version++;
		$perm->perm = 'editedPerm';

		$this->_login();

		$this->assertEquals($perm, $this->_cm->edit_permission($release->rid, $trigger->tid, $perm->pid, array('perm' => $perm->perm)));
	}

	public function testEditingPermissionUpdatesMapping() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$perms    = $this->_fetch_permissions($trigger);
		$perms[0]->version++;
		$perms[0]->perm = 'editedPerm';

		$this->_login();

		$perm = $this->_cm->edit_permission($release->rid, $trigger->tid, $perms[0]->pid, array('perm' => $perms[0]->perm));
		$this->assertEquals($perms, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityDeletePermissionAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$perms    = $this->_fetch_permissions($trigger);
		$perm     = $perms[0];

		$this->_cm->logout();

		$this->assertFalse($this->_cm->delete_permission($release->rid, $trigger->tid, $perm->pid));
	}

	public function testAbilityDeletePermissionAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$perms    = $this->_fetch_permissions($trigger);
		$perm     = array_shift($perms);

		$this->_login();

		$this->assertEquals($perm, $this->_cm->delete_permission($release->rid, $trigger->tid, $perm->pid));
		$this->assertEquals($perms, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}

	public function testInabilityRevertPermissionAnonymous() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$perms    = $this->_fetch_permissions($trigger);
		$perm     = clone $perms[0];
		$perm->version++;
		$perm->perm = 'editedPerm';

		$this->_login();
		$this->_cm->edit_permission($release->rid, $trigger->tid, $perm->pid, array('perm' => $perm->perm));
		$this->_cm->logout();

		$this->assertFalse($this->_cm->revert_permission($release->rid, $trigger->tid, $perms[0]->pid, $perms[0]->version));
	}

	public function testAbilityRevertPermissionAuthenticated() {
		$release  = $this->_fetch_release(ESS_PUBLISHED);
		$triggers = $this->_cm->fetch_triggers($release->rid);
		$trigger  = $triggers[0];
		$perms    = $this->_fetch_permissions($trigger);
		$perm     = clone $perms[0];
		$perm->version++;
		$perm->perm = 'editedPerm';

		$expected          = clone $perms[0];
		$expected->version = $perm->version + 1;

		$this->_login();
		$this->_cm->edit_permission($release->rid, $trigger->tid, $perm->pid, array('perm' => $perm->perm));

		$this->assertEquals($expected, $this->_cm->revert_permission($release->rid, $trigger->tid, $perms[0]->pid, $perms[0]->version));
	}

	public function testRevertingPermissionUpdatesMapping() {
		$release       = $this->_fetch_release(ESS_PUBLISHED);
		$triggers      = $this->_cm->fetch_triggers($release->rid);
		$trigger       = $triggers[0];
		$perms         = $this->_fetch_permissions($trigger);
		$original_perm = array_shift($perms);

		$this->_login();
		$this->_cm->edit_permission($release->rid, $trigger->tid, $original_perm->pid, array('perm' => 'editedPerm'));

		$reverted_perm = $this->_cm->revert_permission($release->rid, $trigger->tid, $original_perm->pid, $original_perm->version);
		array_unshift($perms, $reverted_perm);

		$this->assertEquals($perms, $this->_cm->fetch_permissions($release->rid, $trigger->tid));
	}
}
