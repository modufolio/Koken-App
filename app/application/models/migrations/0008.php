<?php
	$a = new Application();
	$a->where('token', '69ad71aa4e07e9338ac49d33d041941b')->get();

	if ($a->exists())
	{
		$a->delete();
	}

	$done = true;