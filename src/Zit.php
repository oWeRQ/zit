<?php

namespace Zit;

class Zit
{
	protected $store;
	protected $workCopy;

	public function __construct($store, $workCopy)
	{
		$this->store = $store;
		$this->workCopy = $workCopy;
	}

	public function init()
	{
		$this->store->init();
	}

	public function addFiles($paths)
	{
		$indexTree = $this->store->indexTree();
		foreach ($paths as $path) {
			$path = $this->workCopy->normalizePath($path);
			foreach ($this->workCopy->workTree($path) as $name => $hash) {
				if (!array_key_exists($name, $indexTree) || $indexTree[$name] !== $hash) {
					echo "add '$name'\n";
					$this->store->writeIndex($name, $this->workCopy->read($name));
				}
			}
		}
	}

	public function deleteFiles($names)
	{
		foreach ($names as $name) {
			echo "delete '$name'\n";
			$this->store->indexDelete($name);
		}
	}

	public function restoreFiles($names)
	{
		$headTree = $this->store->headTree();
		foreach ($names as $name) {
			if (array_key_exists($name, $headTree)) {
				echo "restore $name\n";
				$this->workCopy->write($name, $this->store->readObject($headTree[$name]));
			}
		}
	}

	public function status()
	{
		$deleted = [];
		$staged = [];
		$changed = [];
		$untracked = [];

		$headTree = $this->store->headTree();
		$indexTree = $this->store->indexTree();
		$workTree = $this->workCopy->workTree();

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
			'commit' => $this->store->readHeadHash(),
			'ref' => $this->store->readHeadRef(),
			'deleted' => $deleted,
			'staged' => $staged,
			'changed' => $changed,
			'untracked' => $untracked,
		];
	}

	public function log()
	{
		$commits = [];

		$current = $this->store->readJson($this->store->readHeadHash());
		while ($current) {
			$commits[] = $current;
			$current = $this->store->readJson($current['parents'][0]);
		}

		return $commits;
	}

	public function listBranches()
	{
		return $this->store->listBranches();
	}

	public function commit($message, $author)
	{
		$files = $this->store->indexTree();

		foreach ($files as $name => $hash) {
			$this->store->writeObject($hash, $this->store->readIndex($name));
		}

		$treeHash = $this->store->writeJson($files);
		$commitHash = $this->store->writeJson([
			'date' => date('c'),
			'author' => $author,
			'message' => $message,
			'tree' => $treeHash,
			'parents' => [$this->store->readHeadHash()],
		]);
		$this->store->writeHeadHash($commitHash);
		echo "commit $commitHash\n";
	}

	public function branch($branch)
	{
		$this->store->writeBranch($branch, $this->store->readHeadHash());
		$this->store->writeHeadBranch($branch);
	}

	public function switch($branch)
	{
		$commitHash = $this->store->readBranch($branch);
		$commit = $this->store->readJson($commitHash);
		$commitTree = $this->store->readJson($commit['tree']);
		$workTree = $this->workCopy->workTree();

		foreach ($commitTree as $name => $hash) {
			if (!array_key_exists($name, $workTree) || $workTree[$name] !== $hash) {
				echo "update '$name'\n";
				$this->workCopy->write($name, $this->store->readObject($hash));
			}
		}

		$this->store->writeHeadBranch($branch);
	}

	public function reset()
	{
		$headTree = $this->store->headTree();
		$indexTree = $this->store->indexTree();

		foreach ($headTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree) || $indexTree[$name] !== $hash) {
				$this->store->resetIndex($name, $hash);
			}
		}

		foreach ($indexTree as $name => $hash) {
			if (!array_key_exists($name, $headTree)) {
				$this->store->deleteIndex($name);
			}
		}
	}
}