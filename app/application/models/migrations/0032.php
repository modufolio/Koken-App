<?php

	$a = new Album;
	$c = new Content;
	$t = new Text;

	$this->db->query("ALTER TABLE {$a->table} DROP COLUMN tags_migrated");
	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN tags_migrated");
	$this->db->query("ALTER TABLE {$t->table} DROP COLUMN tags_migrated");

	$done = true;