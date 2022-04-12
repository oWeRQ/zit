<?php

namespace Zit;

class Console
{
	protected $zit;

	public function __construct(Zit $zit)
	{
		$this->zit = $zit;
	}

	protected function print($data)
	{
		echo json_encode($data,  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
	}

	/**
	 * Show usage
	 */
	public function usage()
	{
		echo "Usage: zit <command> [args...]\n";
		echo "Commands:\n";

		$methods = (new \ReflectionClass($this))->getMethods(\ReflectionMethod::IS_PUBLIC);
		foreach ($methods as $method) {
			if (str_starts_with($method->name, '__'))
				continue;

			preg_match('/ \* (.*)/u', $method->getDocComment(), $match);
			$comment = $match ? $match[1] : '';
			$command = $method->name.implode(array_map(function($param) {
				return ($param->isDefaultValueAvailable() ? " [{$param->name}]" : " <{$param->name}>").($param->isVariadic() ? '...' : '');
			}, $method->getParameters()));
			echo "  ".str_pad($command, 20)."  $comment\n";
		}
	}

	/**
	 * Create an empty repository
	 */
	public function init()
	{
		$this->zit->init();
	}

	/**
	 * Add file contents to the index
	 */
	public function add(...$files)
	{
		$this->zit->addFiles($files);
	}

	/**
	 * Remove files from the working tree and from the index
	 */
	public function rm(...$files)
	{
		$this->zit->deleteFiles($files);
	}

	/**
	 * Restore working tree files
	 */
	public function restore(...$files)
	{
		$this->zit->restoreFiles($files);
	}

	/**
	 * Show the working tree status
	 */
	public function status()
	{
		foreach ($this->zit->status() as $name => $values) {
			if ($values) {
				echo "$name:\n";
				foreach ((array)$values as $value) {
					echo "  $value\n";
				}
			}
		}
	}

	/**
	 * Show commit logs
	 */
	public function log()
	{
		$this->print($this->zit->log());
	}

	/**
	 * Record changes to the repository
	 */
	public function commit($message)
	{
		$this->zit->commit($message);
	}

	/**
	 * List, create, or delete branches
	 */
	public function branch($branch = null)
	{
		if ($branch)
			$this->zit->branch($branch);
		else
			$this->print($this->zit->listBranches());
	}

	/**
	 * Switch branches
	 */
	public function switch($branch)
	{
		$this->zit->switch($branch);
	}

	/**
	 * Reset current HEAD to the specified state
	 */
	public function reset()
	{
		$this->zit->reset();
	}
}
