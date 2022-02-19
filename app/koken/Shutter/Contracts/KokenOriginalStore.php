<?php

interface KokenOriginalStore
{
	public function send($localFile, $key);
	public function delete($url);
}