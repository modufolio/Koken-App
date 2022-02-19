<?php

	$c = new Category;
	$this->db->query("UPDATE {$c->table} SET count = album_count + content_count + text_count");

	$done = true;