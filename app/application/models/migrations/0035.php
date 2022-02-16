<?php

$c = new Content;
$this->db->query("UPDATE {$c->table} SET old_slug = NULL WHERE old_slug = slug");
$this->db->query("UPDATE {$c->table} SET old_slug = CONCAT(',', old_slug, ',') WHERE old_slug IS NOT NULL");

$done = true;
