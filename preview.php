<?php 
	
	// Set this so the system will know they are using index.php?/this/that type URLs
	$rewrite = false;
	$draft = true;
	require 'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'site.php';