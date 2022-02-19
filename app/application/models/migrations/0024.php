<?php

	$c = new Content;
	$this->db->query("UPDATE {$c->table} SET published_on = uploaded_on");

	$a = new Album;
	$this->db->query("UPDATE {$a->table} SET published_on = created_on");

	$done = true;