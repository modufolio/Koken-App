<?php

interface KokenDatabaseConfiguration
{
	public function get();
	public function write($configuration);
}