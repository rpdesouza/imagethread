<?php

namespace Model\Domain;

/**
 * Domain object for Image
 *
 * @package Model\Domain
 */
class Image {

	/**
	 * ID
	 *
	 * @var integer
	 */
	private $_id;

	/**
	 * Title
	 *
	 * @var string
	 */
	private $_title;

	/**
	 * Path
	 *
	 * @var string
	 */
	private $_path;


	/**
	 * @param $id
	 * @param $title
	 * @param $path
	 */
	public function __construct($id = NULL, $title = NULL, $path = NULL) {

		// setting the variables
		$this->setId($id);
		$this->setTitle($title);
		$this->setPath($path);
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->_id;
	}

	/**
	 * @param int $id
	 */
	public function setId($id)
	{
		$this->_id = $id;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->_title;
	}

	/**
	 * @param string $title
	 */
	public function setTitle($title)
	{
		$this->_title = $title;
	}

	/**
	 * @return string
	 */
	public function getPath()
	{
		return $this->_path;
	}

	/**
	 * @param string $path
	 */
	public function setPath($path)
	{
		$this->_path = $path;
	}

}