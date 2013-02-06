<?php
/**
 * @group Model
 */

class PermModelTest extends CIUnit_TestCase {
	private $_pm;
	private $_deleted_perm;
	private $_max_pid = 0;

	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	public function setUp() {
		parent::setUp();

		$this->CI->load->model('Perm_model');
		$this->_pm = $this->CI->Perm_model;
		$this->dbfixt('cmd_perm');

		$perms = array();
		foreach ($this->cmd_perm_fixt as $perm) {
			$perms[$perm['pid']][] = (object)$perm;
			$this->_max_pid        = ($perm['pid'] > $this->_max_pid) ? $perm['pid'] : $this->_max_pid;
		}

		$this->cmd_perm_fixt = array();
		$index               = 0;
		foreach ($perms as $versions) {
			$this->cmd_perm_fixt[$index] = array_reverse($versions);
			if ($this->cmd_perm_fixt[$index][0]->status == 0) {
				$this->_deleted_perm = $this->cmd_perm_fixt[$index][0];
			}

			$index++;
		}
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function testAbilityFetchPermLatestRevision() {
		$expected = $this->cmd_perm_fixt[0][0];
		$this->assertEquals($expected, $this->_pm->fetch($expected->pid));
	}

	public function testAbilityFetchPermSpecificVersion() {
		$expected = $this->cmd_perm_fixt[0][1];
		$this->assertEquals($expected, $this->_pm->fetch($expected->pid, $expected->version));
	}

	public function testInabilityFetchPermInvalidPID() {
		$this->assertEmpty($this->_pm->fetch(1000));
	}

	public function testInabilityFetchDeletedPerm() {
		$this->assertEmpty($this->_pm->fetch($this->_deleted_perm->pid));
	}

	public function testAbilityCreatePerm() {
		$expected = (object)array(
			'pid'     => count($this->cmd_perm_fixt) + 1,
			'version' => 1,
			'perm'    => 'new perm',
			'pdesc'   => 'New Perm Description',
			'status'  => 1,
		);

		$this->assertEquals($expected,
		                    $this->_pm->insert(array('perm' => $expected->perm, 'pdesc' => $expected->pdesc)));
	}

	public function testCreatePermCannotBeEmpty() {
		$this->assertFalse($this->_pm->insert(array('perm' => '', 'pdesc' => 'Some Description')));
	}

	public function testAbilityClonePermValid() {
		$original          = $this->cmd_perm_fixt[0][0];
		$expected          = clone $original;
		$expected->pid     = $this->_max_pid + 1;
		$expected->version = 1;

		$this->assertEquals($expected, $this->_pm->clone_perm($original->pid, $original->version));
	}

	public function testInabilityClonePermInvalidPID() {
		$original = $this->cmd_perm_fixt[0][0];

		$this->assertFalse($this->_pm->clone_perm(1000, $original->version));
	}

	public function testInabilityClonePermInvalidVersion() {
		$original = $this->cmd_perm_fixt[0][0];

		$this->assertFalse($this->_pm->clone_perm($original->pid, 1000));
	}

	public function testAbilityEditPermValidPID() {
		$expected       = $this->cmd_perm_fixt[0][0];
		$expected->perm = 'editted Perm';
		$data           = array(
			'perm' => $expected->perm,
		);

		$result = $this->_pm->edit($expected->pid, $expected->version, $data);
		$this->assertEquals($expected->perm, $result->perm);
	}

	public function testEdditedPermCannotBeEmpty() {
		$editted = $this->cmd_perm_fixt[0][0];
		$data    = array(
			'pid'     => $editted->pid,
			'version' => $editted->version,
			'perm'    => '',
		);
		$this->assertFalse($this->_pm->edit($editted->pid, $editted->version, $data));
	}

	public function testInabilityEditDeletedPerm() {
		$data = array(
			'perm' => 'edit',
		);

		$this->assertFalse($this->_pm->edit($this->_deleted_perm->pid, $this->_deleted_perm->version, $data));
	}

	public function testVersionOfEdditedPermIncrements() {
		$expected = $this->cmd_perm_fixt[0][0];
		$data     = array(
			'perm' => 'editted',
		);

		$result = $this->_pm->edit($expected->pid, $expected->version, $data);
		$this->assertEquals($expected->version + 1, $result->version);
	}

	public function testAbilityEditDescription() {
		$expected = $this->cmd_perm_fixt[0][0];
		$data     = array(
			'pdesc' => 'editted',
		);

		$result = $this->_pm->edit($expected->pid, $expected->version, $data);
		$this->assertEquals($data['pdesc'], $result->pdesc);
	}

	public function testInabilityEditInvalidField() {
		$expected = $this->cmd_perm_fixt[0][0];
		$data     = array(
			'invalid' => 'editted',
		);

		$this->assertFalse($this->_pm->edit($expected->pid, $expected->version, $data));
	}

	public function testInabilityEditStatusFieldDirectly() {
		$expected = $this->cmd_perm_fixt[0][0];
		$data     = array(
			'status' => 0,
		);

		$this->assertFalse($this->_pm->edit($expected->pid, $expected->version, $data));
	}

	public function testAbilityDeletePermValidPID() {
		$deleted         = $this->cmd_perm_fixt[0][0];
		$deleted->status = 0;

		$this->assertEquals($deleted, $this->_pm->delete($deleted->pid, $deleted->version));
		$this->assertEmpty($this->_pm->fetch($deleted->pid, $deleted->version));
	}

	public function testAbilityRestoreDeletedPerm() {
		$expected         = clone $this->_deleted_perm;
		$expected->status = 1;
		$expected->version++;

		$this->assertEquals($expected, $this->_pm->restore($this->_deleted_perm->pid, $this->_deleted_perm->version));
	}

	public function testVersionOfRestoredPermIncrements() {
		$expected = clone $this->_deleted_perm;

		$result = $this->_pm->restore($this->_deleted_perm->pid, $this->_deleted_perm->version);
		$this->assertEquals($expected->version + 1, $result->version);
	}

	public function testAbilityRevertPermToPreviousVersion() {
		foreach ($this->cmd_perm_fixt as $versions) {
			if (count($versions) > 1) {
				$expected          = clone $versions[0];
				$expected->version = count($versions) + 1;
				$revert_to         = $versions[0]->version;
				break;
			}
		}

		$this->assertEquals($expected, $this->_pm->revert($expected->pid, $revert_to));
	}

	public function testVersionOfRevertedPermIncrements() {
		foreach ($this->cmd_perm_fixt as $versions) {
			if (count($versions) > 1) {
				$expected          = clone $versions[0];
				$expected->version = count($versions) + 1;
				$revert_to         = $versions[0]->version;
				break;
			}
		}

		$result = $this->_pm->revert($expected->pid, $revert_to);
		$this->assertEquals($expected->version, $result->version);
	}
}
