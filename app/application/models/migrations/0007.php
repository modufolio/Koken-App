<?php

	$path = FCPATH . '.htaccess';

	if (file_exists($path))
	{
		$htaccess = create_htaccess();
		file_put_contents($path, $htaccess);
	}

	$done = true;