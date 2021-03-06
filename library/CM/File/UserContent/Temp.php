<?php

class CM_File_UserContent_Temp extends CM_File_UserContent {

	/**
	 * @var string
	 */
	private $_uniqid;

	/**
	 * @param string $uniqid
	 * @throws CM_Exception_Nonexistent
	 */
	public function __construct($uniqid) {
		$data = CM_Db_Db::select('cm_tmp_userfile', '*', array('uniqid' => $uniqid))->fetch();
		if (!$data) {
			throw new CM_Exception_Nonexistent('Uniqid for file does not exists: `' . $uniqid . '`');
		}
		$this->_uniqid = $data['uniqid'];
		parent::__construct('tmp', $data['filename']);
	}

	/**
	 * @param string      $filename
	 * @param string|null $content
	 * @return CM_File_UserContent_Temp
	 */
	public static function create($filename, $content = null) {
		$filename = (string) $filename;
		if (strlen($filename) > 100) {
			$filename = substr($filename, -100, 100);
		}
		$uniqid = md5(rand() . uniqid());
		CM_Db_Db::insert('cm_tmp_userfile', array('uniqid' => $uniqid, 'filename' => $filename, 'createStamp' => time()));

		$file = new self($uniqid);
		$file->mkDir();
		if (null !== $content) {
			$file->write($content);
		}
		return $file;
	}

	/**
	 * @return string
	 */
	public function getUniqid() {
		return $this->_uniqid;
	}

	/**
	 * @return string
	 */
	public function getPathRelative() {
		return $this->_getDir() . DIRECTORY_SEPARATOR . $this->getUniqid() . '.' . $this->getExtension();
	}

	public function delete() {
		CM_Db_Db::delete('cm_tmp_userfile', array('uniqid' => $this->getUniqid()));
		parent::delete();
	}

	/**
	 * @param int $age
	 */
	public static function deleteOlder($age) {
		$age = (int) $age;
		$result = CM_Db_Db::select('cm_tmp_userfile', 'uniqid', '`createStamp` < ' . (time() - $age));
		foreach ($result->fetchAllColumn() as $uniqid) {
			$tmpFile = new CM_File_UserContent_Temp($uniqid);
			$tmpFile->delete();
		}
	}
}
