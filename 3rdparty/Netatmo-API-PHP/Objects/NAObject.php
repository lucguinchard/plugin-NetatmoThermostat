<?php
if (!class_exists('NARestErrorCode')) {
	require_once dirname(__FILE__) . "/../Constants/AppliCommonPublic.php";
}

/**
 * NAObject Class
 * Abstact class, parent of every objects
 */
abstract class NAObject {
	protected $object = array();

	public function __construct($array) {
		$this->object = $array;
	}

	/**
	 * @param string field : array key
	 * @param $default : default value in case field is not set
	 * @return object field or default if field is not set
	 * @brief returns an object's field
	 */
	public function getVar($field, $default = NULL) {
		if (isset($this->object[$field])) {
			return $this->object[$field];
		} else {
			return $default;
		}

	}

	/**
	 * @param string $field : field to be set
	 * @param $value value to set to field
	 * @brief set an object's field
	 */
	public function setVar($field, $value) {
		$this->object[$field] = $value;
	}

	/**
	 * @return id
	 * @btief returns object id
	 */
	public function getId() {
		return $this->getVar("id");
	}

	/**
	 * @return array $object
	 * @brief return this object as an array
	 */
	public function toArray() {
		return $this->object;
	}

	/**
	 * @return JSON document
	 * @brief returns object as a JSON document
	 */
	public function toJson() {
		return json_encode($this->toArray());
	}

	/**
	 * @return string
	 * @brief return string representation of object : JSON doc
	 */
	public function __toString() {
		return $this->toJson();
	}

}

abstract class NAObjectWithPicture extends NAObject {
	public function getPictureURL($picture, $baseURI = 'https://api.netatmo.com/api/getcamerapicture') {
		if (isset($picture[NACameraImageInfo::CII_ID]) && isset($picture[NACameraImageInfo::CII_KEY])) {
			return $baseURI . '?image_id=' . $picture[NACameraImageInfo::CII_ID] . '&key=' . $picture[NACameraImageInfo::CII_KEY];
		} else {
			return NULL;
		}

	}

}
?>
