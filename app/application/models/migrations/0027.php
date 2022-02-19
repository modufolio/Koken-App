<?php

	$t = new Tag;
	$c = new Category;

	$fields = $this->db->query("SHOW COLUMNS FROM {$t->table}");

	$run = true;

	if ($fields)
	{
		$result = $fields->result();
		if ($result && strtolower($result[0]->Type) === 'int(9)')
		{
			$run = false;
		}
	}

	if ($run)
	{
		$this->db->query("TRUNCATE TABLE {$t->table}");
		$this->db->query("ALTER TABLE {$t->table} DROP COLUMN count");
		$this->db->query("ALTER TABLE {$c->table} DROP COLUMN count");
		$this->db->query("ALTER TABLE {$t->table} DROP COLUMN id");
		$this->db->query("ALTER TABLE {$t->table} ADD id INT(9) AUTO_INCREMENT PRIMARY KEY FIRST");

		$a = new Album;
		$c = new Content;
		$t = new Text;

		$this->db->query("ALTER TABLE {$a->table} DROP COLUMN tags_migrated");
		$this->db->query("ALTER TABLE {$c->table} DROP COLUMN tags_migrated");
		$this->db->query("ALTER TABLE {$t->table} DROP COLUMN tags_migrated");

		$this->db->query("ALTER TABLE {$a->table} ADD tags_migrated TINYINT(1) DEFAULT 0");
		$this->db->query("ALTER TABLE {$c->table} ADD tags_migrated TINYINT(1) DEFAULT 0");
		$this->db->query("ALTER TABLE {$t->table} ADD tags_migrated TINYINT(1) DEFAULT 0");

		$this->db->query("ALTER TABLE {$a->table} CHANGE tags tags_old TEXT");
		$this->db->query("ALTER TABLE {$c->table} CHANGE tags tags_old TEXT");
		$this->db->query("ALTER TABLE {$t->table} CHANGE tags tags_old TEXT");

		$a->where('tags_old', null)->update(array('tags_migrated' => 1));
		$c->where('tags_old', null)->update(array('tags_migrated' => 1));
		$t->where('tags_old', null)->update(array('tags_migrated' => 1));
	}

	$done = true;