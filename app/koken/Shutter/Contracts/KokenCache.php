<?php

interface KokenCache
{
	public function get($key, $lastModified);
	public function write($key, $content);
	public function clear($key);
}