<?php

namespace Model\Mapper;

use \Model\Domain\Image as Domain,
	Model\Exception,
     Monolog,
	\Upload;

/**
 * Class Image
 *
 * Mapper object for Images
 *
 * @package Model\Mapper
 */
class Image {

	/**
	 * @var Domain
	 */
	private $_domain;

	/**
	 * @var PDO
	 */
	private $_db;

	/**
	 * @var Monolog\Logger
	 */
	private $_logger;

	/**
	 * @var \Upload\File
	 */
	private $_file;

	/**
	 * @return PDO
	 * @throws \Exception
	 */
	public function getDb()
	{
		if (!$this->_db instanceof \PDO) {
			throw new \Exception('invalid database connection');
		}
		return $this->_db;
	}

	/**
	 * @param \PDO $db
	 */
	public function setDb(\PDO $db = NULL)
	{
		$this->_db = $db;
	}

	/**
	 * @return \Model\Domain\Image
	 */
	public function getDomain()
	{
		$domain = $this->_domain;
		if (!$domain instanceof Domain) {
			$domain = new Domain();
		}
		return $domain;
	}

	/**
	 * @param \Model\Domain\Image $domain
	 */
	public function setDomain(Domain $domain = NULL)
	{
		$this->_domain = $domain;
	}

	/**
	 * @return Monolog\Logger
	 */
	public function getLogger()
	{
		return $this->_logger;
	}

	/**
	 * @param Monolog\Logger $logger
	 */
	public function setLogger(Monolog\Logger $logger = NULL)
	{
		$this->_logger = $logger;
	}

	/**
	 * @return \Upload\File
	 */
	public function getFile()
	{
		return $this->_file;
	}

	/**
	 * @param \Upload\File $file
	 */
	public function setFile($file)
	{
		$this->_file = $file;
	}

	/**
	 * @param Domain $domain
	 */
	public function __construct(\PDO $db = NULL, Monolog\Logger $logger = NULL, Domain $domain = NULL) {
		$this->setDb($db);
		$this->setLogger($logger);
		$this->setDomain($domain);
	}


	/**
	 * Find the image
	 *
	 * @param $id
	 *
	 * @return \Model\Domain\Image
	 * @throws \Exception
	 */
	public function find($id) {
		//validate the id
		if (filter_var($id, FILTER_VALIDATE_INT) === false) {
			throw new Exception\InvalidArgumentException('invalid image id');
		}

		$domain = new Domain;
		try {
			$stmt = $this->getDb()->prepare("SELECT * FROM images WHERE id = :id LIMIT 1");
			$stmt->bindParam(':id', $id);
			if ($stmt->execute()) {
				while ($row = $stmt->fetch()) {
					$domain->setId($row['id']);
					$domain->setTitle($row['title']);
					$domain->setPath($row['path']);
				}
			}
		} catch (\Exception $e) {
			// logging
			$this->getLogger()->info("error fetching image ID . " . $id);
		}

		return $domain;
	}

	/**
	 * Fetch all images
	 *
	 * @return \Model\Domain\Image[]
	 */
	public function fetchAll() {
		$images = [];
		try {
			$images_db = $this->getDb()->query('SELECT * FROM images ORDER BY id DESC')->fetchAll();
			foreach($images_db as $image) {
				$images[] = new Domain($image['id'], $image['title'], $image['path']);
			}
		} catch (\Exception $e) {
			// logging
			$this->getLogger()->info("error fetching all images");
		}

		return $images;
	}


	/**
	 * Save image on database
	 *
	 * @param Domain $image
	 *
	 * @throws \Exception
	 */
	public function save(Domain $image) {
		try {
			//validate the image path
			if (empty($image->getPath())) {
				throw new Exception\InvalidArgumentException('invalid image path');
			}

			// insert into table
			$statement = $this->getDb()->prepare('INSERT INTO images (title, path) VALUES (?, ?)');
			$statement->execute([$image->getTitle(), $image->getPath()]);

			// logging
			$this->getLogger()->info("inserted on database with the ID: ".$this->getDb()->lastInsertId());

			//@todo commit changes on database

		} catch ( Exception\InvalidArgumentException $e) {
			// logging
			$this->getLogger()->critical("invalid domain: " . $e->getMessage());
		} catch ( \PDOException $e ) {
			// logging
			$this->getLogger()->info("something wrong with the query or database:" . $e->getMessage());
			// @todo rollback changes on database
		} catch (\Exception $e) {
			// logging
			$this->getLogger()->info("error saving image:" . $e->getMessage());
			throw $e;
		} finally {
			// @todo: close connection?
		}
	}

	/**
	 * Upload the image
	 *
	 * @param $field_name
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function upload($field_name) {
		// upload class
		$storage = new Upload\Storage\FileSystem('uploads');
		$file = new Upload\File($field_name, $storage);

		// set the file to be used outside of mapper
		$this->setFile($file);

		// create a unique name for the file
		$new_filename = uniqid();
		$file->setName($new_filename);

		// Validation
		$file->addValidations([
			new Upload\Validation\Mimetype('image/jpeg'),
			new Upload\Validation\Size('2M'),

		]);

		// all data from the file
		$data = [
			'name'       => $file->getNameWithExtension(),
			'extension'  => $file->getExtension(),
			'mime'       => $file->getMimetype(),
			'size'       => $file->getSize(),
			'md5'        => $file->getMd5(),
			'dimensions' => $file->getDimensions()
		];

		try {

			// upload
			$file->upload();

			// validate the image dimension
			if ($file->getDimensions()['width'] > 1920 || $file->getDimensions()['height'] > 1080) {
				$file->addError('image dimensions invalid');
				$this->getLogger()->info("invalid image dimensions");
				throw new \Exception;
			}

			// logging
			$this->getLogger()->info("success uploaded file: ".$data['name']);

			// create the thumb
			$thumb_path = "uploads/thumbs/".$data['name'];
			$thumb_width = 300;
			$thumb_height = (int) (($thumb_width / $data['dimensions']['width']) * $data['dimensions']['height']);
			$image_truecolor = imagecreatetruecolor($thumb_width, $thumb_height);


			$thumb_jpg = imageCreateFromJpeg("uploads/".$data['name']);

			imagecopyresampled($image_truecolor, $thumb_jpg, 0, 0, 0, 0, $thumb_width, $thumb_height, $data['dimensions']['width'], $data['dimensions']['height']);
			imageJpeg($image_truecolor, $thumb_path, 100);

			// logging
			$this->getLogger()->info("thumbnail created: ".$thumb_path);

			return TRUE;
		} catch ( Upload\Exception\UploadException $e) {
			$this->getLogger()->critical("validation error: " . $e->getMessage());
		} catch (\Exception $e) {
			$this->getLogger()->critical("yikes! upload error: " . $e->getMessage());
		} finally {
			// @todo: do something here. Maybe logging something more.
		}
		return FALSE;
	}
}