<?php

$d = new Draft;

$this->db->query("ALTER TABLE {$d->table} MODIFY live_data MEDIUMTEXT NULL");

$done = true;