<?php

    $s = new Slug();
    $slug_count = $s->like('id', 'album.', 'after')->count();

    $c = new Album();
    $content_count = $c->count();

    if ($slug_count < $content_count) {
        $slugs = [];

        $c = new Album();
        foreach ($c->select('slug')->get_iterated() as $content) {
            $slugs[] = "('album." . $content->slug . "')";
        }

        $slugs = implode(', ', $slugs);
        $this->db->query("INSERT INTO {$s->table}(id) VALUES $slugs ON DUPLICATE KEY UPDATE id=id;");
    }

    $done = true;
