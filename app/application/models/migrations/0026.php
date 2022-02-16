<?php

	$c = new Content;
	$this->db->query("UPDATE {$c->table} SET has_iptc = 1 WHERE iptc IS NOT NULL");
	$this->db->query("UPDATE {$c->table} SET has_exif = 1 WHERE exif IS NOT NULL");

	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN exif");
	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN exif_make");
	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN exif_model");
	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN exif_iso");
	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN exif_camera_serial");
	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN exif_camera_lens");
	$this->db->query("ALTER TABLE {$c->table} DROP COLUMN iptc");

	$done = true;