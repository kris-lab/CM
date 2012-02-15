<?php
require_once __DIR__ . '/../../TestCase.php';

class CM_SessionTest extends TestCase {

	public static function setUpBeforeClass() {
	}

	public static function tearDownAfterClass() {
		TH::clearEnv();
	}

	public function testConstructor() {
		$session = new CM_Session();
		$this->assertTrue(true);

		try {
			new CM_Session('nonexistent');
			$this->fail('Can instantiate nonexistent session.');
		} catch (CM_Exception_Nonexistent $ex) {
			$this->assertTrue(true);
		}
	}

	public function testSetGetDelete() {
		$session = new CM_Session();
		$this->assertNull($session->get('foo'));

		$session->set('foo', 'bar');
		$this->assertSame('bar', $session->get('foo'));

		$session->delete('foo');
		$this->assertNull($session->get('foo'));

		$session->set('bar', array('foo', 'bar'));
		$this->assertEquals(array('foo', 'bar'), $session->get('bar'));
	}

	public function testPersistence() {
		$session = new CM_Session();
		$session->set('foo', 'bar');
		$session->set('bar', array('foo', 'bar'));
		$expiration = $session->getExpiration();
		$sessionId = $session->getId();
		unset($session);

		try {
			$session = new CM_Session($sessionId);
			$this->assertTrue(true);
		} catch (CM_Exception_Nonexistent $ex) {
			$this->fail('Session not persistent.');
		}
		$this->assertEquals('bar', $session->get('foo'));
		$this->assertEquals(array('foo', 'bar'), $session->get('bar'));
		$this->assertEquals($expiration, $session->getExpiration());

		//test that session is only persisted when data changed
		CM_Mysql::update(TBL_CM_SESSION, array('data' => serialize(array('foo' => 'foo'))), array('sessionId' => $session->getId()));
		$session->_change();
		unset($session);
		$session = new CM_Session($sessionId);
		$this->assertEquals('foo', $session->get('foo'));


		//caching
		$session->set('foo', 'foo');
		$sessionId = $session->getId();
		unset($session);

		$session = new CM_Session($sessionId);
		$this->assertEquals('foo', $session->get('foo'));

		$sessionId = $session->getId();
		$session->regenerateId();
		try {
			$session = new CM_Session($sessionId);
			$this->fail('Session not deleted.');
		} catch (CM_Exception_Nonexistent $ex) {
			$this->assertTrue(true);
		}
	}

	public function testGc() {
		$session = new CM_Session();
		$sessionId = $session->getId();
		unset($session);
		TH::timeForward(4000);
		CM_Session::gc();
		try {
			new CM_Session($sessionId);
			$this->fail('Expired Session was not deleted.');
		} catch (CM_Exception_Nonexistent $ex) {
			$this->assertTrue(true);
		}

	}

	public function testLogin() {
		$user = CM_Model_User::create();
		$session = CM_Session::getInstance(null, true);

		$session->setUser($user);
		$this->assertModelEquals($user, $session->getUser(true));
		$this->assertTrue($session->getUser(true)->getOnline());
	}

	public function testLogout() {
		$session = CM_Session::getInstance(null, true);
		$session->setUser(CM_Model_User::create());
		$user = $session->getUser(true);

		$session->deleteUser();
		$this->assertNull($session->getUser());
		$user->_change();
		$this->assertFalse($user->getOnline());
	}

	public function testGetViewer() {
		$session = CM_Session::getInstance(null, true);
		$this->assertNull($session->getUser());
		try {
			$session->getUser(true);
			$this->fail('Should throw exception');
		} catch (CM_Exception_AuthRequired $ex) {
			$this->assertTrue(true);
		}

		/** @var CM_Model_User $user */
		$user = CM_Model_User::create();
		$session->setUser($user);
		$this->assertModelEquals($user, $session->getUser(true));
	}

	public function testLatestactivity() {
		$this->markTestSkipped('test broken');
		/** @var CM_Model_User $user */
		$user = CM_Model_User::create();

		$activityStamp1 = time();
		$session = CM_Session::getInstance(null, true);
		$session->setUser($user);
		$this->assertEquals($activityStamp1, $session->getUser(true)->getLatestactivity(), null, 1);

		TH::timeForward(CM_Session::ACTIVITY_EXPIRATION / 10);
		$session = CM_Session::getInstance(null, true);
		$session->setUser($user);
		$this->assertEquals($activityStamp1, $session->getUser(true)->getLatestactivity(), null, 1);

		TH::timeForward(CM_Session::ACTIVITY_EXPIRATION / 2);
		$activityStamp2 = time();
		$session = CM_Session::getInstance(null, true);
		$session->setUser($user);
		$this->assertEquals($activityStamp2, $session->getUser(true)->getLatestactivity(), null, 1);
	}
}
