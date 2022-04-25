<?php

namespace Zit;

use ZipArchive;

class Store
{
	protected $storeFile;
	protected $zip;

	public function getStoreFile()
	{
		return $this->storeFile;
	}

	public function getZip()
	{
		return $this->zip;
	}

	public function __construct($storeFile)
	{
		$this->storeFile = $storeFile;
		$this->zip = new ZipArchive;
		$this->zip->open($this->getStoreFile());
	}

	public function __destruct()
	{
		if ($this->zip->filename) {
			$this->zip->close();
		}
	}

	public function reload()
	{
		$this->zip->close();
		$this->zip->open($this->getStoreFile());
	}

	public function init()
	{
		$this->zip->open($this->getStoreFile(), ZipArchive::CREATE);
		$this->zip->addEmptyDir('.zit');
	}

	public function indexDelete($files)
	{
		foreach ($files as $file) {
			$this->zip->deleteName($file);
		}
	}

	public function indexReset($files)
	{
		foreach ($files as $name => $hash) {
			$this->writeIndex($name, $this->readObject($hash));
		}
	}

	public function indexTree()
	{
		$files = [];

		for ($i = 0; $i < $this->zip->numFiles; $i++) {
			$name = $this->zip->getNameIndex($i);
			if (substr($name, -1) !== '/' && !str_starts_with($name, '.zit/')) {
				$files[$name] = sha1($this->zip->getFromName($name));
			}
		}

		ksort($files);
		return $files;
	}

	public function read($name)
	{
		return $this->zip->getFromName($name);
	}

	public function write($name, $content)
	{
		return $this->zip->addFromString($name, $content);
	}

	public function readIndex($name)
	{
		return $this->read($name);
	}

	public function writeIndex($name, $content)
	{
		return $this->write($name, $content);
	}

	public function readZit($name)
	{
		return $this->read(".zit/$name");
	}

	public function writeZit($name, $content)
	{
		return $this->write(".zit/$name", $content);
	}

	public function readJson($hash)
	{
		$json = $this->readObject($hash) ?: '{}';
		return json_decode($json, true);
	}

	public function writeJson($data)
	{
		$json = json_encode($data);
		$hash = sha1($json);
		$this->writeObject($hash, $json);
		return $hash;
	}

	public function readObject($hash)
	{
		return $this->readZit($this->objectPath($hash));
	}

	public function writeObject($hash, $content)
	{
		return $this->writeZit($this->objectPath($hash), $content);
	}

	public function objectPath($hash)
	{
		return 'objects/'.substr($hash, 0, 2).'/'.substr($hash, 2);
	}
}