<?php

abstract class CM_Stream_Adapter_Video_Abstract extends CM_Stream_Adapter_Abstract {

    /** @var array */
    protected $_servers;

    /** @var array */
    protected $_config;

    abstract public function synchronize();

    /**
     * @param CM_Http_Request_Abstract $request
     * @throws CM_Exception_Invalid
     * return int
     */
    abstract public function getServerId(CM_Http_Request_Abstract $request);

    /**
     * @param CM_Model_Stream_Abstract $stream
     */
    abstract protected function _stopStream(CM_Model_Stream_Abstract $stream);

    /**
     * @param array|null $servers
     * @param array|null $config
     */
    public function __construct(array $servers = null, array $config = null) {
        $this->_servers = (array) $servers;
        $this->_config = (array) $config;
    }

    public function checkStreams() {
        /** @var CM_Model_StreamChannel_Video $streamChannel */
        foreach ($this->_getStreamChannels() as $streamChannel) {
            $streamChannelIsValid = $streamChannel->isValid();
            if ($streamChannel->hasStreamPublish()) {
                /** @var CM_Model_Stream_Publish $streamPublish */
                $streamPublish = $streamChannel->getStreamPublish();
                if (!$streamChannelIsValid) {
                    $this->stopStream($streamPublish);
                } else {
                    if ($streamPublish->getAllowedUntil() < time()) {
                        $streamPublish->setAllowedUntil($streamChannel->canPublish($streamPublish->getUser(), $streamPublish->getAllowedUntil()));
                        if ($streamPublish->getAllowedUntil() < time()) {
                            $this->stopStream($streamPublish);
                        }
                    }
                }
            }
            /** @var CM_Model_Stream_Subscribe $streamSubscribe */
            foreach ($streamChannel->getStreamSubscribes() as $streamSubscribe) {
                if (!$streamChannelIsValid) {
                    $this->stopStream($streamSubscribe);
                } else {
                    if ($streamSubscribe->getAllowedUntil() < time()) {
                        $streamSubscribe->setAllowedUntil($streamChannel->canSubscribe($streamSubscribe->getUser(), $streamSubscribe->getAllowedUntil()));
                        if ($streamSubscribe->getAllowedUntil() < time()) {
                            $this->stopStream($streamSubscribe);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param CM_Model_Stream_Abstract $stream
     * @throws CM_Exception_Invalid
     */
    public function stopStream(CM_Model_Stream_Abstract $stream) {
        /** @var CM_Model_StreamChannel_Video $streamChannel */
        $streamChannel = $stream->getStreamChannel();
        if (!$streamChannel instanceof CM_Model_StreamChannel_Video) {
            throw new CM_Exception_Invalid('Cannot stop stream of non-video channel');
        }
        $this->_stopStream($stream);
    }

    /**
     * @param string $streamName
     * @param string $clientKey
     * @param int    $start
     * @param int    $width
     * @param int    $height
     * @param int    $serverId
     * @param string $data
     * @throws CM_Exception
     * @throws CM_Exception_NotAllowed
     * @return int
     */
    public function publish($streamName, $clientKey, $start, $width, $height, $serverId, $data) {
        $streamName = (string) $streamName;
        $clientKey = (string) $clientKey;
        $start = (int) $start;
        $width = (int) $width;
        $height = (int) $height;
        $serverId = (int) $serverId;
        $data = (string) $data;
        $params = CM_Params::factory(CM_Params::jsonDecode($data), true);
        $streamChannelType = $params->getInt('streamChannelType');
        $session = new CM_Session($params->getString('sessionId'));
        $user = $session->getUser(true);
        /** @var CM_Model_StreamChannel_Abstract $streamChannel */
        $streamChannel = CM_Model_StreamChannel_Abstract::createType($streamChannelType, array(
            'key'            => $streamName,
            'adapterType'    => $this->getType(),
            'params'         => $params,
            'width'          => $width,
            'height'         => $height,
            'serverId'       => $serverId,
            'thumbnailCount' => 0,
        ));
        try {
            CM_Model_Stream_Publish::createStatic(array(
                'streamChannel' => $streamChannel,
                'user'          => $user,
                'start'         => $start,
                'key'           => $clientKey,
            ));
        } catch (CM_Exception $ex) {
            $streamChannel->delete();
            throw new CM_Exception_NotAllowed('Cannot publish: ' . $ex->getMessage());
        }
        return $streamChannel->getId();
    }

    /**
     * @param string $streamName
     * @return null
     */
    public function unpublish($streamName) {
        $streamName = (string) $streamName;
        /** @var CM_Model_StreamChannel_Abstract $streamChannel */
        $streamChannel = CM_Model_StreamChannel_Abstract::findByKeyAndAdapter($streamName, $this->getType());
        if (!$streamChannel) {
            return;
        }

        $streamChannel->getStreamPublish()->delete();
        if (!$streamChannel->hasStreams()) {
            $streamChannel->delete();
        }
    }

    /**
     * @param string $streamName
     * @param string $clientKey
     * @param int    $start
     * @param string $data
     * @throws CM_Exception_NotAllowed
     */
    public function subscribe($streamName, $clientKey, $start, $data) {
        $streamName = (string) $streamName;
        $clientKey = (string) $clientKey;
        $start = (int) $start;
        $data = (string) $data;
        $user = null;
        $params = CM_Params::factory(CM_Params::jsonDecode($data), true);
        if ($params->has('sessionId')) {
            if ($session = CM_Session::findById($params->getString('sessionId'))) {
                $user = $session->getUser(false);
            }
        }
        /** @var CM_Model_StreamChannel_Abstract $streamChannel */
        $streamChannel = CM_Model_StreamChannel_Abstract::findByKeyAndAdapter($streamName, $this->getType());
        if (!$streamChannel) {
            throw new CM_Exception_NotAllowed();
        }

        try {
            CM_Model_Stream_Subscribe::createStatic(array(
                'streamChannel' => $streamChannel,
                'user'          => $user,
                'start'         => $start,
                'key'           => $clientKey,
            ));
        } catch (CM_Exception $ex) {
            throw new CM_Exception_NotAllowed('Cannot subscribe: ' . $ex->getMessage());
        }
    }

    /**
     * @param string $streamName
     * @param string $clientKey
     */
    public function unsubscribe($streamName, $clientKey) {
        $streamName = (string) $streamName;
        $clientKey = (string) $clientKey;
        /** @var CM_Model_StreamChannel_Abstract $streamChannel */
        $streamChannel = CM_Model_StreamChannel_Abstract::findByKeyAndAdapter($streamName, $this->getType());
        if (!$streamChannel) {
            return;
        }
        $streamSubscribe = $streamChannel->getStreamSubscribes()->findKey($clientKey);
        if ($streamSubscribe) {
            $streamSubscribe->delete();
        }
        if (!$streamChannel->hasStreams()) {
            $streamChannel->delete();
        }
    }

    /**
     * @param int|null $serverId
     * @throws CM_Exception_Invalid
     * @return array
     */
    public function getServer($serverId = null) {
        $servers = $this->_servers;
        if (null === $serverId) {
            $serverId = array_rand($servers);
        }

        $serverId = (int) $serverId;
        if (!array_key_exists($serverId, $servers)) {
            throw new CM_Exception_Invalid("No video server with id `$serverId` found");
        }
        return $servers[$serverId];
    }

    /**
     * @param int $serverId
     * @return string
     * @throws CM_Exception_Invalid
     */
    public function getPublicHost($serverId) {
        $serverId = (int) $serverId;
        $server = $this->getServer($serverId);
        return $server['publicHost'];
    }

    /**
     * @param int $serverId
     * @return string
     * @throws CM_Exception_Invalid
     */
    public function getPrivateHost($serverId) {
        $serverId = (int) $serverId;
        $server = $this->getServer($serverId);
        return $server['privateIp'];
    }

    /**
     * @return CM_Paging_StreamChannel_AdapterType
     */
    protected function _getStreamChannels() {
        return new CM_Paging_StreamChannel_AdapterType($this->getType());
    }
}
