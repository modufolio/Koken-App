<?php

	$c = new Category;

	foreach($c->get_iterated() as $category)
	{
		$category->update_counts();
	}

	$done = true;
