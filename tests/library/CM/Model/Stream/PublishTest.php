<?php

class CM_Model_Stream_PublishTest extends CMTest_TestCase {

	public function testConstructor() {
		$videoStreamPublish = CMTest_TH::createStreamPublish();
		$this->assertGreaterThan(0, $videoStreamPublish->getId());
		try {
			new CM_Model_Stream_Publish(22123);
			$this->fail('Can instantiate nonexistent VideoStream_Publish');
		} catch (CM_Exception_Nonexistent $ex) {
			$this->assertTrue(true);
		}
	}

	public function testDuplicateKeys() {
		$data = array('user' => CMTest_TH::createUser(), 'start' => time(), 'streamChannel' => CMTest_TH::createStreamChannel(),
					  'key'  => '13215231_1');
		CM_Model_Stream_Publish::createStatic($data);
		try {
			CM_Model_Stream_Publish::createStatic($data);
			$this->fail('Should not be able to create duplicate key instance');
		} catch (CM_Exception $e) {
			$this->assertContains('Duplicate entry', $e->getMessage());
		}
		$data['streamChannel'] = CMTest_TH::createStreamChannel();
		CM_Model_Stream_Publish::createStatic($data);
	}

	public function testSetAllowedUntil() {
		$videoStreamPublish = CMTest_TH::createStreamPublish();
		$videoStreamPublish->setAllowedUntil(234234);
		$this->assertSame(234234, $videoStreamPublish->getAllowedUntil());
		$videoStreamPublish->setAllowedUntil(2342367);
		$this->assertSame(2342367, $videoStreamPublish->getAllowedUntil());
	}

	public function testCreate() {
		$user = CMTest_TH::createUser();
		$streamChannel = CMTest_TH::createStreamChannel();
		$this->assertEquals(0, $streamChannel->getStreamPublishs()->getCount());
		$videoStream = CM_Model_Stream_Publish::createStatic(array('user'          => $user, 'start' => 123123, 'key' => '123123_2',
																   'streamChannel' => $streamChannel));
		$this->assertRow('cm_stream_publish', array('userId'    => $user->getId(), 'start' => 123123, 'key' => '123123_2',
													'channelId' => $streamChannel->getId()));
		$this->assertEquals(1, $streamChannel->getStreamPublishs()->getCount());
	}

	public function testDelete() {
		$streamChannel = CMTest_TH::createStreamChannel();
		$videoStreamPublish = CMTest_TH::createStreamPublish(null, $streamChannel);
		$this->assertEquals(1, $streamChannel->getStreamPublishs()->getCount());
		$videoStreamPublish->delete();
		try {
			new CM_Model_Stream_Publish($videoStreamPublish->getId());
			$this->fail('videoStream_publish not deleted.');
		} catch (CM_Exception_Nonexistent $ex) {
			$this->assertTrue(true);
		}
		$this->assertEquals(0, $streamChannel->getStreamPublishs()->getCount());
	}

	public function testFindKey() {
		$videoStreamPublishOrig = CMTest_TH::createStreamPublish();
		$videoStreamPublish = CM_Model_Stream_Publish::findByKeyAndChannel($videoStreamPublishOrig->getKey(), $videoStreamPublishOrig->getStreamChannel());
		$this->assertEquals($videoStreamPublish, $videoStreamPublishOrig);
	}

	public function testFindKeyNonexistent() {
		$streamChannel = CMTest_TH::createStreamChannel();
		$videoStreamPublish = CM_Model_Stream_Publish::findByKeyAndChannel('doesnotexist', $streamChannel);
		$this->assertNull($videoStreamPublish);
	}

	public function testGetKey() {
		$user = CMTest_TH::createUser();
		$streamChannel = CMTest_TH::createStreamChannel();
		/** @var CM_Model_Stream_Publish $streamPublish */
		$streamPublish = CM_Model_Stream_Publish::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(),
																	 'key'           => 'foo'));
		$this->assertSame('foo', $streamPublish->getKey());
	}

	public function testGetKeyMaxLength() {
		$user = CMTest_TH::createUser();
		$streamChannel = CMTest_TH::createStreamChannel();
		/** @var CM_Model_Stream_Publish $streamPublish */
		$streamPublish = CM_Model_Stream_Publish::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(),
																	 'key'           => str_repeat('a', 100)));
		$this->assertSame(str_repeat('a', 36), $streamPublish->getKey());
	}

	public function testGetChannel() {
		$streamChannel = CMTest_TH::createStreamChannel();
		$streamPublish = CMTest_TH::createStreamPublish(null, $streamChannel);
		$this->assertEquals($streamChannel, $streamPublish->getStreamChannel());
	}

	public function testUnsetUser() {
		$user = CMTest_TH::createUser();
		$streamChannel = CMTest_TH::createStreamChannel();
		/** @var CM_Model_Stream_Publish $streamPublish */
		$streamPublish = CM_Model_Stream_Publish::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(),
																	 'key'           => str_repeat('a', 100)));
		$this->assertEquals($user, $streamPublish->getUser());

		$streamPublish->unsetUser();
		$this->assertNull($streamPublish->getUser());
	}

	/**
	 * @expectedException CM_Exception_Invalid
	 * @expectedExceptionMessage not valid
	 */
	public function testCreateInvalidStreamChannel() {
		$user = CMTest_TH::createUser();
		$streamChannel = $this->getMockBuilder('CM_Model_StreamChannel_Video')->setMethods(array('isValid'))->getMock();
		$streamChannel->expects($this->any())->method('isValid')->will($this->returnValue(false));
		/** @var CM_Model_StreamChannel_Video $streamChannel */

		CM_Model_Stream_Publish::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(), 'key' => 'foo'));
	}

	public function testDeleteOnUnpublish() {
		$streamPublish = $this->getMockBuilder('CM_Model_Stream_Publish')
			->setMethods(array('getStreamChannel', 'getId'))->getMock();

		$streamChannel = $this->getMockBuilder('CM_Model_StreamChannel_Video')
			->setMethods(array('isValid', 'onUnpublish'))->getMock();

		$streamPublish->expects($this->any())->method('getStreamChannel')->will($this->returnValue($streamChannel));

		$streamChannel->expects($this->any())->method('isValid')->will($this->returnValue(true));
		$streamChannel->expects($this->once())->method('onUnpublish')->with($streamPublish);

		/** @var CM_Model_StreamChannel_Video $streamChannel */
		/** @var CM_Model_Stream_Publish $streamPublish */

		$onDeleteBefore = CMTest_TH::getProtectedMethod('CM_Model_Stream_Publish', '_onDeleteBefore');
		$onDeleteBefore->invoke($streamPublish);
		$onDelete = CMTest_TH::getProtectedMethod('CM_Model_Stream_Publish', '_onDelete');
		$onDelete->invoke($streamPublish);
	}

	public function testDeleteOnUnpublishInvalid() {
		$streamPublish = $this->getMockBuilder('CM_Model_Stream_Publish')
			->setMethods(array('getStreamChannel', 'getId'))->getMock();

		$streamChannel = $this->getMockBuilder('CM_Model_StreamChannel_Video')
			->setMethods(array('isValid', 'onUnpublish'))->getMock();

		$streamPublish->expects($this->any())->method('getStreamChannel')->will($this->returnValue($streamChannel));

		$streamChannel->expects($this->any())->method('isValid')->will($this->returnValue(false));
		$streamChannel->expects($this->never())->method('onUnpublish');

		/** @var CM_Model_StreamChannel_Video $streamChannel */
		/** @var CM_Model_Stream_Publish $streamPublish */

		$onDelete = CMTest_TH::getProtectedMethod('CM_Model_Stream_Publish', '_onDelete');
		$onDelete->invoke($streamPublish);
	}
}
