<?php

    $s = new Setting();
    $s->where('name', 'use_default_labels_links')->get();

    if (!$s->exists()) {
        $u = new Setting();
        $u->name = 'use_default_labels_links';
        $u->value = 'false';
        $u->save();

        $urls = [['type' => 'content', 'data' => ['singular' => 'Content', 'plural' => 'Content', 'order' => 'captured_on DESC', 'url' => 'id']], ['type' => 'favorite', 'data' => ['singular' => 'Favorite', 'plural' => 'Favorites', 'order' => 'manual ASC']], ['type' => 'album', 'data' => ['singular' => 'Album', 'plural' => 'Albums', 'order' => 'manual ASC', 'url' => 'id']], ['type' => 'set', 'data' => ['singular' => 'Set', 'plural' => 'Sets']], ['type' => 'essay', 'data' => ['singular' => 'Essay', 'plural' => 'Essays', 'order' => 'published_on DESC', 'url' => 'id']], ['type' => 'page', 'data' => ['singular' => 'Page', 'plural' => 'Pages', 'url' => 'id']], ['type' => 'tag', 'data' => ['singular' => 'Tag', 'plural' => 'Tags']], ['type' => 'category', 'data' => ['singular' => 'Category', 'plural' => 'Categories']], ['type' => 'archive', 'data' => ['singular' => 'Archive', 'plural' => 'Archives']]];

        $u = new Url();
        $u->data = serialize($urls);
        $u->save();
    }

    $done = true;
