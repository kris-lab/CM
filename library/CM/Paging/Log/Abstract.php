<?php

abstract class CM_Paging_Log_Abstract extends CM_Paging_Abstract implements CM_Typed {

	/**
	 * @param boolean $aggregate
	 * @param int     $ageMax
	 */
	public function __construct($aggregate = false, $ageMax = null) {
		$select = '`id`, `msg`, `timeStamp`, `metaInfo`';
		$where = '`type` = ' . $this->getType();
		$order = '`timeStamp` DESC';
		$group = null;
		if ($ageMax) {
			$where .= ' AND `timeStamp` > ' . (time() - (int) $ageMax);
		}
		if ($aggregate) {
			$select = '`msg`, COUNT(*) AS `count`';
			$group = '`msg`';
			$order = '`count` DESC';
		}
		$source = new CM_PagingSource_Sql_Deferred($select, 'cm_log', $where, $order, null, $group);
		parent::__construct($source);
	}

	public function flush() {
		CM_Db_Db::delete('cm_log', array('type' => $this->getType()));
	}

	/**
	 * @param int $age
	 */
	public static function deleteOlder($age) {
		$age = (int) $age;
		CM_Db_Db::delete('cm_log', '`timeStamp` < ' . (time() - $age));
	}

	/**
	 * @param int       $type
	 * @param bool|null $aggregate
	 * @param int|null  $ageMax
	 * @return CM_Paging_Log_Abstract
	 */
	final public static function factory($type, $aggregate = null, $ageMax = null) {
		$className = self::_getClassName($type);
		return new $className($aggregate, $ageMax);
	}

	/**
	 * @return array
	 */
	protected function _getMetafInfoFromRequest() {
		$metaInfo = array();
		if (CM_Request_Abstract::hasInstance()) {
			$request = CM_Request_Abstract::getInstance();
			$metaInfo['path'] = $request->getPath();
			if ($viewer = $request->getViewer()) {
				$metaInfo['userId'] = $viewer->getId();
			}
			if ($ip = $request->getIp()) {
				$metaInfo['ip'] = $ip;
			}
			if ($request->hasHeader('Referer')) {
				$metaInfo['referer'] = $request->getHeader('Referer');
			}
			if ($request->hasHeader('User-Agent')) {
				$metaInfo['useragent'] = $request->getHeader('User-Agent');
			}
		}
		return $metaInfo;
	}

	/**
	 * @param string $msg
	 * @param array  $metaInfo
	 */
	protected function _add($msg, array $metaInfo = null) {
		$msg = (string) $msg;
		$values = array('type' => $this->getType(), 'msg' => $msg, 'timeStamp' => time());
		if ($metaInfo) {
			$values['metaInfo'] = serialize($metaInfo);
		}
		CM_Db_Db::insertDelayed('cm_log', $values);
	}

	protected function _processItem($item) {
		if (!empty($item['metaInfo'])) {
			$metaInfo = @unserialize($item['metaInfo']);
			if (false === $metaInfo) {
				$metaInfo = null;
			}
			$item['metaInfo'] = $metaInfo;
		}
		return $item;
	}
}
