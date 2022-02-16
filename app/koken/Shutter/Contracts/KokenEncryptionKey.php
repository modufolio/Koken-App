<?php

interface KokenEncryptionKey
{
	public function get();
	public function write($key);
}