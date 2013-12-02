<?php
/**
 * Media Application - Front Model - /apps/media/front/model.php
 */

defined('WITYCMS_VERSION') or die('Access denied');

/**
 * MediaModel is the Front Model of the Media Application
 *
 * @package Apps
 * @author Johan Dufau <johan.dufau@creatiwity.net>
 * @author Julien Blatecky <julien.blatecky@creatiwity.net>
 * @version 0.3-19-04-2013
 */
class MediaModel {
	protected $db;

	public function __construct() {
		$this->db = WSystem::getDB();

		// Declare table
		$this->db->declareTable('media_access_history');
		$this->db->declareTable('media_filetag_rel');
		$this->db->declareTable('media_list');
		$this->db->declareTable('media_tags');
	}

	public function fileIDExists($fileID) {
		$prep = $this->db->prepare('
			SELECT COUNT(id)
			FROM media_list
			WHERE fileID = :fileID
		');
		$prep->bindParam(':fileID', $fileID);
		$prep->execute();

		return $prep->fetchColumn() > 0;
	}

	public function generateFileID($hash, $length = 8) {
		while(!isset($id) || $this->fileIDExists($id)) {
			$id = $this->generateAnID($hash, $length);
		}

		return $id;
	}

	/**
	 * Returns a new ID for the file based on the hash file
	 *
	 * BSD license http://stackoverflow.com/a/1516430/2650468
	 *
	 * @param int $length is the length of the ID returned, 8 by default
	 * @return string The generated file ID
	 */
	private function generateAnID($hash, $length = 8) {
		$hex = md5($hash.'?*'.uniqid("", true));

		$pack = pack('H*', $hex);

		// max 22 chars
		$uid = base64_encode($pack);

		// mixed case
		$uid = preg_replace("#[^A-Za-z0-9]+#", "", $uid);

		if ($length < 4) {
			$length = 4;
		}

		if ($length > 128) {
			// prevent silliness, can remove
			$length = 128;
		}

		while (strlen($uid) < $length) {
			// append until length achieved
			$uid = $uid.$this->generateFileID($hash, 22);
		}

		return substr($uid, 0, $length);
	}

	/**
	 * Creates the media instance in db
	 *
	 * @param array $params
	 * @return boolean True if insertion worked
	 */
	public function createNewMedia($params) {
		// Insert data in table
		$prep = $this->db->prepare('
			INSERT INTO media_list(fileID, hash, filename, mime, extension, state)
			VALUES(:fileID, :hash, :filename, :mime, :extension, :state)
		');

		$prep->bindParam(':fileID', $params['fileID']);
		$prep->bindParam(':hash', $params['hash']);
		$prep->bindParam(':filename', $params['filename']);
		$prep->bindParam(':mime', $params['mime']);
		$prep->bindParam(':extension', $params['extension']);
		$prep->bindParam(':state', $params['state']);

		return $prep->execute();
	}

	public function getMediaData($fileID, $onlyOnline = true) {
		// Insert data in table
		$prep = $this->db->prepare('
			SELECT fileID, hash, filename, mime, extension, state
			INTO media_list
			WHERE fileID = :fileID'.($onlyOnline ? ' AND state = "ONLINE"':'')
		);

		$prep->bindParam(':fileID', $fileID);
		$prep->execute();

		return $prep->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * Returns the complete filename if it exists, access is allowed and compare sha1 if needed
	 *
	 * @param array $params
	 * @return string|boolean Returns false if there is no file corresponding,
	 * 						'corrupted' if file is corrupted or the complete filename
	 */
	public function getFile($params) {
		// Build complete filename (private and public)
		$filename = $params[0];
		$dotPosition = strrpos($filename, '.');

		if ($dotPosition == false) {
			return false;
		}

		$filename = substr_replace($filename, '.'.$params['fileID'], $dotPosition, 0);

		$isPrivate = false;

		// Do file_exists
		if (is_dir(UPLOAD_DIR.'media'.DS.'private') && file_exists(UPLOAD_DIR.'media'.DS.'private'.DS.$filename)) {
			$fullFilename = UPLOAD_DIR.'media'.DS.'private'.DS.$filename;
			$isPrivate = true;
		} else if (is_dir(UPLOAD_DIR.'media'.DS.'public') && file_exists(UPLOAD_DIR.'media'.DS.'public'.DS.$filename)) {
			$fullFilename = UPLOAD_DIR.'media'.DS.'public'.DS.$filename;
		} else {
			return false;
		}

		// Test access for this user (only if private)
		if ($isPrivate && file_exists(UPLOAD_DIR.'media'.DS.'private'.DS.$params['fileID'].'.perm')) {

		}

		// If needed test sha1 with the db one and/or the one in $params
		if (!empty($params['hash'])) {
			$realHash = sha1_file($fullFilename);

			if ($realHash != $params['hash']) {
				return 'corrupted';
			}
		}

		// Returns complete filename
		return $fullFilename;
	}
}
