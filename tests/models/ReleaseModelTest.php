<?php

/**
 * @group Model
 */

class ReleaseModelTest extends CIUnit_TestCase {
	private $_rm;
	private $_status_mapping;
	private $_num_releases;
	private $_max_rid = 0;

	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	public function setUp() {
		parent::tearDown();
		parent::setUp();

		/*
		* this is an example of how you would load a product model,
		* load fixture data into the test database (assuming you have the fixture yaml files filled with data for your tables),
		* and use the fixture instance variable

		$this->CI->load->model('Product_model', 'pm');
		$this->pm=$this->CI->pm;
		$this->dbfixt('users', 'products')

		the fixtures are now available in the database and so:
		$this->users_fixt;
		$this->products_fixt;

		*/

		$this->CI->load->model('Release_model');
		$this->_rm = $this->CI->Release_model;

		$this->dbfixt('cmd_release');

		$releases = array();
		// group releases together and sort by version
		foreach (array_reverse($this->cmd_release_fixt) as $release) {
			$releases[$release['rid']][] = (object)$release;
			$this->_max_rid              = ($release['rid'] > $this->_max_rid) ? $release['rid'] : $this->_max_rid;
		}

		$this->_num_releases = count($releases);

		$index                  = 0;
		$this->_status_mapping  = array();
		$this->cmd_release_fixt = array();
		foreach ($releases as $versions) {
			$this->cmd_release_fixt[]                                          = $versions;
			$this->_status_mapping[$this->cmd_release_fixt[$index][0]->status] = $index;
			$index++;
		}
	}

	private function _fetch_type($type, $version = NULL) {
		if (!empty($version)) {

			foreach ($this->cmd_release_fixt[$this->_status_mapping[$type]] as $release) {
				if ($release->version == $version) {
					return $release;
				}
			}
		}

		return $this->cmd_release_fixt[$this->_status_mapping[$type]][0];
	}

	private function _fetch_versions($type) {
		return $this->cmd_release_fixt[$this->_status_mapping[$type]];
	}

	public function testAbilityFetchReleaseWithValidID() {
		$expected = $this->_fetch_type(ESS_PUBLISHED);
		$this->assertEquals($expected, $this->_rm->fetch($expected->rid));
	}

	public function testInabilityFetchReleaseWithInvalidID() {
		$this->assertFalse($this->_rm->fetch(1000));
	}

	public function testAbilityFetchReleasePublishedStatus() {
		$expected = $this->_fetch_type(ESS_PUBLISHED);
		$this->assertEquals($expected, $this->_rm->fetch($expected->rid));
	}

	public function testAbilityFetchReleasePromotedStatus() {
		$expected = $this->_fetch_type(ESS_PROMOTED);
		$this->assertEquals($expected, $this->_rm->fetch($expected->rid));
	}

	public function testAbilityFetchReleaseDefaultStatus() {
		$expected = $this->_fetch_type(ESS_DEFAULT);
		$this->assertEquals($expected, $this->_rm->fetch($expected->rid));
	}

	public function testAbilityFetchReleaseUnpublishedStatus() {
		$expected = $this->_fetch_type(ESS_UNPUBLISHED);
		$this->assertEquals($expected, $this->_rm->fetch($expected->rid));
	}

	public function testInabilityFetchReleaseDeletedStatus() {
		$expected = $this->_fetch_type(ESS_DELETED);
		$this->assertFalse($this->_rm->fetch($expected->rid));
	}

	public function testAbilityFetchAllReleases() {
		$expected = array();
		foreach ($this->cmd_release_fixt as $release) {
			if ($release[0]->status != ESS_DELETED) {
				$expected[$release[0]->rid] = $release[0];
			}
		}

		usort($expected,
			function ($a, $b) {
				return ($a->rid > $b->rid) ? -1 : 1;
			});
		$this->assertEquals($expected, $this->_rm->fetch_all());
	}

	public function testAbilityFetchPublishedReleases() {
		$expected  = array();
		$published = array(ESS_PUBLISHED, ESS_PROMOTED, ESS_DEFAULT);
		foreach ($this->cmd_release_fixt as $release) {
			if (in_array($release[0]->status, $published)) {
				$expected[] = $release[0];
			}
		}

		$this->assertEquals($expected, $this->_rm->fetch_published());
	}

	public function testAbilityFetchDefaultRelease() {
		$expected = $this->_fetch_type(ESS_DEFAULT);

		$this->assertEquals($expected, $this->_rm->fetch_default());
	}

	public function testAbilityFetchReleaseVersions() {
		$expected = $this->_fetch_versions(ESS_PUBLISHED);
		$this->assertEquals($expected, $this->_rm->fetch_versions($expected[0]->rid));
	}

	public function testAbilityCreateRelease() {
		$expected = array(
			'rid'        => $this->_num_releases + 1,
			'version'    => 1,
			'name'       => 'New Release',
			'bukkit'     => '1',
			'change_log' => 'http://log.goes.here.com',
			'notes'      => 'nothing to report',
			'status'     => ESS_UNPUBLISHED,
		);

		$this->assertEquals((object)$expected, $this->_rm->insert($expected));
	}

	public function testVersionOfNewReleaseIsValid() {
		$expected = array(
			'rid'        => $this->_num_releases + 1,
			'version'    => 1,
			'name'       => 'New Release',
			'bukkit'     => '1',
			'change_log' => 'http://log.goes.here.com',
			'notes'      => 'nothing to report',
			'status'     => ESS_UNPUBLISHED,
		);
		$result   = $this->_rm->insert($expected);

		$this->assertEquals($expected['version'], $result->version);
	}

	public function testCreateVersionStatusIsUnpublished() {
		$expected = array(
			'rid'        => $this->_num_releases + 1,
			'version'    => 1,
			'name'       => 'New Release',
			'bukkit'     => '1',
			'change_log' => 'http://log.goes.here.com',
			'notes'      => 'nothing to report',
			'status'     => ESS_UNPUBLISHED,
		);
		$result   = $this->_rm->insert($expected);

		$this->assertEquals(ESS_UNPUBLISHED, $result->status);
	}

	public function testCreatedVersionNameMustBeUnique() {
		$expected = array(
			'rid'        => $this->_num_releases + 1,
			'version'    => 1,
			'name'       => $this->cmd_release_fixt[$this->_status_mapping[ESS_PUBLISHED]][0]->name,
			'bukkit'     => '1',
			'change_log' => 'http://log.goes.here.com',
			'notes'      => 'nothing to report',
			'status'     => ESS_UNPUBLISHED,
		);

		$this->assertFalse($this->_rm->insert($expected));
	}

	public function testCreatedVersionNameCannotBeEmpty() {
		$expected = array(
			'rid'        => $this->_num_releases + 1,
			'version'    => 1,
			'name'       => '',
			'bukkit'     => '1',
			'change_log' => 'http://log.goes.here.com',
			'notes'      => 'nothing to report',
			'status'     => ESS_UNPUBLISHED,
		);

		$this->assertFalse($this->_rm->insert($expected));
	}

	public function testAbilityCloneReleaseValid() {
		$original          = $this->_fetch_type(ESS_PUBLISHED);
		$expected          = clone $original;
		$expected->name    = 'new Release';
		$expected->rid     = $this->_max_rid + 1;
		$expected->version = 1;
		$expected->status  = ESS_UNPUBLISHED;

		$this->assertEquals($expected, $this->_rm->clone_release($original->rid, array('name' => $expected->name)));
	}

	public function testInabilityCloneReleaseInvalidRID() {
		$original = $this->_fetch_type(ESS_PUBLISHED);

		$this->assertFalse($this->_rm->clone_release(1000, array('name' => 'New Release')));
	}

	public function testAbilityEditReleaseWithValidID() {
		$expected       = $this->_fetch_type(ESS_PUBLISHED);
		$expected->name = 'Edited version';
		$expected->version++;

		$this->assertEquals($expected, $this->_rm->edit($expected->rid, array('name' => $expected->name)));
	}

	public function testInabilityEditRelaseWithInvalidID() {
		$this->assertFalse($this->_rm->edit(1000, array('name' => 'Invalid ID')));
	}

	public function testInabilityEditReleaseDeletedStatus() {
		$expected = $this->_fetch_type(ESS_DELETED);

		$this->assertFalse($this->_rm->edit($expected->rid, array('name' => 'Deleted Version')));
	}

	public function testEditReleaseNameMustBeUnique() {
		$published = $this->_fetch_type(ESS_PUBLISHED);
		$promoted  = $this->_fetch_type(ESS_PROMOTED);
		$this->assertFalse($this->_rm->edit($published->rid, array('name' => $promoted->name)));
	}

	public function testEditReleaseNameCannotBeEmpty() {
		$expected = $this->_fetch_type(ESS_PUBLISHED);

		$this->assertFalse($this->_rm->edit($expected->rid, array('name' => '')));
	}

	public function testVersionOfEdittedReleaseIncrements() {
		$expected       = $this->_fetch_type(ESS_PUBLISHED);
		$expected->name = 'Edited release';
		$expected->version++;

		$new_version = $this->_rm->edit($expected->rid, array('name' => $expected->name));
		$this->assertEquals($expected->version, $new_version->version);
	}

	public function testInabilityUnpublishDeletedRelease() {
		$deleted = $this->_fetch_type(ESS_DELETED);
		$this->assertFalse($this->_rm->mark_unpublished($deleted->rid));
	}

	public function testInabilityPublishDeletedRelease() {
		$deleted = $this->_fetch_type(ESS_DELETED);
		$this->assertFalse($this->_rm->mark_published($deleted->rid));
	}

	public function testInabilityPromoteDeletedRelease() {
		$deleted = $this->_fetch_type(ESS_DELETED);
		$this->assertFalse($this->_rm->mark_promoted($deleted->rid));
	}

	public function testInabilityDefaultDeletedRelease() {
		$deleted = $this->_fetch_type(ESS_DELETED);
		$this->assertFalse($this->_rm->mark_default($deleted->rid));
	}

	public function testAbilityPublishUnpublishedRelease() {
		$expected         = $this->_fetch_type(ESS_UNPUBLISHED);
		$expected->status = ESS_PUBLISHED;

		$this->assertEquals($expected, $this->_rm->mark_published($expected->rid));
	}

	public function testAbilityPromoteUnpublishedRelease() {
		$expected         = $this->_fetch_type(ESS_UNPUBLISHED);
		$expected->status = ESS_PROMOTED;

		$this->assertEquals($expected, $this->_rm->mark_promoted($expected->rid));
	}

	public function testAbilityDefaultUnpublishedRelease() {
		$expected         = $this->_fetch_type(ESS_UNPUBLISHED);
		$expected->status = ESS_DEFAULT;

		$this->assertEquals($expected, $this->_rm->mark_default($expected->rid));
	}

	public function testAbilityPromotePublishedRelease() {
		$expected         = $this->_fetch_type(ESS_PUBLISHED);
		$expected->status = ESS_PROMOTED;

		$this->assertEquals($expected, $this->_rm->mark_promoted($expected->rid));
	}

	public function testAbilityDefaultPublishedRelease() {
		$expected         = $this->_fetch_type(ESS_PUBLISHED);
		$expected->status = ESS_DEFAULT;

		$this->assertEquals($expected, $this->_rm->mark_default($expected->rid));
	}

	public function testAbilityDefaultPromotedRelease() {
		$expected         = $this->_fetch_type(ESS_PROMOTED);
		$expected->status = ESS_DEFAULT;

		$this->assertEquals($expected, $this->_rm->mark_default($expected->rid));
	}

	public function testOnlyOneReleaseCanBeDefault() {
		$expected         = $this->_fetch_type(ESS_PUBLISHED);
		$expected->status = ESS_DEFAULT;
		$this->_rm->mark_default($expected->rid);

		$this->assertEquals($expected, $this->_rm->fetch_default());
	}

	public function testDefaultingReleasePromotesOriginalDefault() {
		$expected         = $this->_fetch_type(ESS_DEFAULT);
		$expected->status = ESS_PROMOTED;
		$new_default      = $this->_fetch_type(ESS_PUBLISHED);
		$this->_rm->mark_default($new_default->rid);

		$this->assertEquals($expected, $this->_rm->fetch($expected->rid));
	}

	public function testAbilityPromoteDefaultedRelease() {
		$expected         = $this->_fetch_type(ESS_DEFAULT);
		$expected->status = ESS_PROMOTED;

		$this->assertEquals($expected, $this->_rm->mark_promoted($expected->rid));
	}

	public function testAbilityPublishDefaultedRelease() {
		$expected         = $this->_fetch_type(ESS_DEFAULT);
		$expected->status = ESS_PUBLISHED;

		$this->assertEquals($expected, $this->_rm->mark_published($expected->rid));
	}

	public function testAbilityUnpublishDefaultedRelease() {
		$expected         = $this->_fetch_type(ESS_DEFAULT);
		$expected->status = ESS_UNPUBLISHED;

		$this->assertEquals($expected, $this->_rm->mark_unpublished($expected->rid));
	}

	public function testAbilityDeleteDefaultedRelease() {
		$expected         = $this->_fetch_type(ESS_DEFAULT);
		$expected->status = ESS_DELETED;

		$this->assertEquals($expected, $this->_rm->delete($expected->rid));
		$this->assertEmpty($this->_rm->fetch($expected->rid));
	}

	public function testAbilityPublishPromotedRelease() {
		$expected         = $this->_fetch_type(ESS_PROMOTED);
		$expected->status = ESS_PUBLISHED;

		$this->assertEquals($expected, $this->_rm->mark_published($expected->rid));
	}

	public function testAbilityUnpublishPromotedRelease() {
		$expected         = $this->_fetch_type(ESS_PROMOTED);
		$expected->status = ESS_UNPUBLISHED;

		$this->assertEquals($expected, $this->_rm->mark_unpublished($expected->rid));
	}

	public function testAbilityDeletePromotedRelease() {
		$expected         = $this->_fetch_type(ESS_PROMOTED);
		$expected->status = ESS_DELETED;

		$this->assertEquals($expected, $this->_rm->delete($expected->rid));
		$this->assertEmpty($this->_rm->fetch($expected->rid));
	}

	public function testAbilityUnpublishPublishedRelease() {
		$expected         = $this->_fetch_type(ESS_PUBLISHED);
		$expected->status = ESS_UNPUBLISHED;

		$this->assertEquals($expected, $this->_rm->mark_unpublished($expected->rid));
	}

	public function testAbilityDeletePublishedRelease() {
		$expected         = $this->_fetch_type(ESS_PUBLISHED);
		$expected->status = ESS_DELETED;

		$this->assertEquals($expected, $this->_rm->delete($expected->rid));
		$this->assertEmpty($this->_rm->fetch($expected->rid));
	}

	public function testAbilityDeleteUnpublishedRelease() {
		$expected         = $this->_fetch_type(ESS_UNPUBLISHED);
		$expected->status = ESS_DELETED;

		$this->assertEquals($expected, $this->_rm->delete($expected->rid));
		$this->assertEmpty($this->_rm->fetch($expected->rid));
	}

	public function testAbilityRevertToEarlierVersionValidID() {
		$revert_to   = 1;
		$expected    = $this->_fetch_type(ESS_PUBLISHED, $revert_to);
		$new_version = $this->_rm->revert($expected->rid, $revert_to);

		$this->assertEquals($expected->name, $new_version->name);
	}

	public function testInabilityRevertToEarlierVersionInvalidID() {
		$this->assertFalse($this->_rm->revert(1000, 1));
	}

	public function testInabilityRevertToCurrentVersion() {
		$expected = $this->_fetch_type(ESS_PUBLISHED);
		$this->assertFalse($this->_rm->revert($expected->rid, $expected->version));
	}

	public function testInablityRevertToInvalidVersion() {
		$expected = $this->_fetch_type(ESS_PUBLISHED);
		$this->assertFalse($this->_rm->revert($expected->rid, $expected->version + 1));
	}

	public function testRevertingVersionIncrementsVersion() {
		$revert_to = 1;
		$current   = $this->_fetch_type(ESS_PUBLISHED);

		$new_version = $this->_rm->revert($current->rid, $revert_to);
		$this->assertEquals($current->version + 1, $new_version->version);
	}
}
