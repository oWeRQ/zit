<?php

namespace Zit;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Zit
{
	protected $store;
	protected $workCopy;
	protected $zip;

	public function __construct($store, $workCopy)
	{
		$this->store = $store;
		$this->workCopy = $workCopy;
		$this->zip = new ZipArchive;
		$this->zip->open($this->getStoreFile());
	}

	public function getStoreFile()
	{
		return $this->store->getStoreFile();
	}

	public function getWorkDir()
	{
		return $this->workCopy->getWorkDir();
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

	public function addFiles($paths)
	{
		foreach ($paths as $path) {
			if (is_dir($path)) {
				$this->addFiles($this->dir($path));
			} elseif (!$this->isIgnoreFile($path)) {
				echo "add '$path'\n";
				$this->zip->addFile($path);
			}
		}
	}

	public function deleteFiles($files)
	{
		foreach ($files as $file) {
			$this->zip->deleteName($file);
		}
	}

	public function restoreFiles($names)
	{
		$headTree = $this->headTree();
		foreach ($names as $name) {
			if (array_key_exists($name, $headTree)) {
				echo "restore $name\n";
				file_put_contents($name, $this->read($this->objectPath($headTree[$name])));
			}
		}
	}

	public function head()
	{
		$ref = $this->headRef();
		if (!str_starts_with($ref, 'refs/'))
			return $ref;

		return $this->readZit($ref) ?: sha1('');
	}

	public function headRef()
	{
		return $this->readZit('HEAD') ?: 'refs/heads/master';
	}

	public function headCommit()
	{
		return $this->readJson($this->head());
	}

	public function headTree()
	{
		$commit = $this->headCommit();
		return $commit ? $this->readJson($commit['tree']) : [];
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

	public function workTree()
	{
		$files = [];

		$trim = strlen($this->getWorkDir()) + 1;
		$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->getWorkDir()));
		foreach ($rii as $file) {
			$name = substr($file->getPathname(), $trim);
			if (!$file->isDir() && !$this->isIgnoreFile($name)) {
				$files[$name] = sha1_file($file->getPathname());
			}
		}

		ksort($files);
		return $files;
	}

	public function status()
	{
		$deleted = [];
		$staged = [];
		$changed = [];
		$untracked = [];

		$headTree = $this->headTree();
		$indexTree = $this->indexTree();
		$workTree = $this->workTree();

		foreach ($headTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree)) {
				$deleted[] = $name;
			}
		}

		foreach ($indexTree as $name => $hash) {
			if (!array_key_exists($name, $headTree) || $headTree[$name] !== $hash) {
				$staged[] = $name;
			}
		}

		foreach ($workTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree)) {
				$untracked[] = $name;
			} elseif ($indexTree[$name] !== $hash) {
				$changed[] = $name;
			}
		}

		return [
			'commit' => $this->head(),
			'ref' => $this->headRef(),
			'deleted' => $deleted,
			'staged' => $staged,
			'changed' => $changed,
			'untracked' => $untracked,
		];
	}

	public function log()
	{
		$commits = [];

		$current = $this->headCommit();
		while ($current) {
			$commits[] = $current;
			$current = $this->readJson($current['parents'][0]);
		}

		return $commits;
	}

	public function listBranches()
	{
		$branches = [];

		$dir = '.zit/refs/heads/';
		$trim = strlen($dir);
		for ($i = 0; $i < $this->zip->numFiles; $i++) {
			$name = $this->zip->getNameIndex($i);
			if (str_starts_with($name, $dir)) {
				$branches[substr($name, $trim)] = $this->read($name);
			}
		}

		return $branches;
	}

	public function commit($message)
	{
		$files = $this->indexTree();

		foreach ($files as $name => $hash) {
			$this->copy($name, $this->objectPath($hash));
		}

		$treeHash = $this->storeJson($files);
		$commitHash = $this->storeJson([
			'date' => date('c'),
			'author' => $this->author(),
			'message' => $message,
			'tree' => $treeHash,
			'parents' => [$this->head()],
		]);
		$this->storeHead($commitHash);
		echo "commit $commitHash\n";
	}

	public function branch($branch)
	{
		$hash = $this->head();
		$ref = "refs/heads/$branch";
		$this->storeZit($ref, $hash);
		$this->storeZit('HEAD', $ref);
		echo "ref $ref\n";
	}

	public function switch($branch)
	{
		$ref = "refs/heads/$branch";
		$hash = $this->readZit($ref);
		$commit = $this->readJson($hash);
		$commitTree = $this->readJson($commit['tree']);
		$workTree = $this->workTree();

		foreach ($commitTree as $name => $hash) {
			if (!array_key_exists($name, $workTree) || $workTree[$name] !== $hash) {
				echo "update '$name'\n";
				file_put_contents($name, $this->read($this->objectPath($hash)));
			}
		}

		$this->storeZit('HEAD', $ref);
	}

	public function reset()
	{
		$headTree = $this->headTree();
		$indexTree = $this->indexTree();

		foreach ($headTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree) || $indexTree[$name] !== $hash) {
				$this->copy($this->objectPath($hash), $name);
			}
		}

		foreach ($indexTree as $name => $hash) {
			if (!array_key_exists($name, $headTree)) {
				$this->zip->deleteName($name);
			}
		}
	}

	protected function storeHead($hash)
	{
		$ref = $this->headRef();
		$this->storeZit($ref, $hash);
	}

	protected function readJson($hash)
	{
		$json = $this->read($this->objectPath($hash)) ?: '{}';
		return json_decode($json, true);
	}

	protected function storeJson($data)
	{
		$json = json_encode($data);
		$hash = sha1($json);
		$this->store($this->objectPath($hash), $json);
		return $hash;
	}

	protected function copy($from, $to)
	{
		return $this->store($to, $this->read($from));
	}

	protected function read($name)
	{
		return $this->zip->getFromName($name);
	}

	protected function store($name, $content)
	{
		return $this->zip->addFromString($name, $content);
	}

	protected function readZit($name)
	{
		return $this->read(".zit/$name");
	}

	protected function storeZit($name, $content)
	{
		return $this->store(".zit/$name", $content);
	}

	protected function objectPath($hash)
	{
		return '.zit/objects/'.substr($hash, 0, 2).'/'.substr($hash, 2);
	}

	protected function isIgnoreFile($name)
	{
		if (str_starts_with($name, '.git/'))
			return true;

		if (str_ends_with($name, '.zip'))
			return true;

		return false;
	}

	protected function dir($path)
	{
		$path = rtrim($path, '/');
		return array_map(fn($name) => ($path === '.' ? $name : "$path/$name"), array_slice(scandir($path), 2));
	}

	protected function author()
	{
		return getenv('USER').'@'.gethostname();
	}
}