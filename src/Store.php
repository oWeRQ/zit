<?php

namespace Zit;

use ZipArchive;

class Store
{
	protected $storeFile;
	protected $zip;

	public function __construct($storeFile)
	{
		$this->storeFile = $storeFile;
		$this->zip = new ZipArchive;
		$this->zip->open($this->storeFile);
	}

	public function __destruct()
	{
		if ($this->isExist()) {
			$this->zip->close();
		}
	}

	public function isExist()
	{
		return (bool)$this->zip->filename;
	}

	public function reload()
	{
		$this->zip->close();
		$this->zip->open($this->storeFile);
	}

	public function init()
	{
		$this->zip->open($this->storeFile, ZipArchive::CREATE);
		$this->zip->addEmptyDir($this->zitPath());
	}

	protected function zitPath($name = '')
	{
		return ".zit/$name";
	}

	public function indexTree()
	{
		$files = [];

		$zitPath = $this->zitPath();
		for ($i = 0; $i < $this->zip->numFiles; $i++) {
			$name = $this->zip->getNameIndex($i);
			if (substr($name, -1) !== '/' && !str_starts_with($name, $zitPath)) {
				$files[$name] = sha1($this->zip->getFromName($name));
			}
		}

		ksort($files);
		return $files;
	}

	public function headTree()
	{
		$commit = $this->readJson($this->readHeadHash());
		return $commit ? $this->readJson($commit['tree']) : [];
	}

	protected function readHeadRef()
	{
		return $this->readZit('HEAD') ?: $this->branchPath('master');
	}

	protected function writeHeadRef($ref)
	{
		return $this->writeZit('HEAD', $ref);
	}

	public function readHeadBranch()
	{
		return $this->branchName($this->readHeadRef());
	}

	public function writeHeadBranch($branch)
	{
		return $this->writeHeadRef($this->branchPath($branch));
	}

	public function readHeadHash()
	{
		$ref = $this->readHeadRef();
		if (!str_starts_with($ref, 'refs/'))
			return $ref;

		return $this->readZit($ref);
	}

	public function writeHeadHash($hash)
	{
		$ref = $this->readHeadRef();
		$this->writeZit($ref, $hash);
	}

	public function listBranches()
	{
		$branches = [];

		$dir = $this->zitPath('refs/heads/');
		$trim = strlen($dir);
		for ($i = 0; $i < $this->zip->numFiles; $i++) {
			$name = $this->zip->getNameIndex($i);
			if (str_starts_with($name, $dir)) {
				$branches[substr($name, $trim)] = $this->read($name);
			}
		}

		return $branches;
	}

	protected function read($name)
	{
		if (!$this->isExist()) {
			throw new \Exception('Store not found');
		}

		return $this->zip->getFromName($name);
	}

	protected function write($name, $content)
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

	public function deleteIndex($name)
	{
		return $this->zip->deleteName($name);
	}

	public function resetIndex($name, $hash)
	{
		return $this->writeIndex($name, $this->readObject($hash));
	}

	protected function readZit($name)
	{
		return $this->read($this->zitPath($name));
	}

	protected function writeZit($name, $content)
	{
		return $this->write($this->zitPath($name), $content);
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

	protected function objectPath($hash)
	{
		return 'objects/'.substr($hash, 0, 2).'/'.substr($hash, 2);
	}

	public function readBranch($branch)
	{
		return $this->readZit($this->branchPath($branch));
	}

	public function writeBranch($branch, $hash)
	{
		return $this->writeZit($this->branchPath($branch), $hash);
	}

	protected function branchPath($branch)
	{
		return "refs/heads/$branch";
	}

	protected function branchName($ref)
	{
		return preg_replace('/^refs\/heads\//', '', $ref);
	}
}