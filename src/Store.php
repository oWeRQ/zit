<?php

namespace Zit;

class Store
{
	protected $storeFile;

	public function __construct($storeFile)
	{
		$this->storeFile = $storeFile;
	}

	public function getStoreFile()
	{
		return $this->storeFile;
	}
}