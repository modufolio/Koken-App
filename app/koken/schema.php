<?php

return  [

        // ================
        // = APPLICATIONS =
        // ================
        'applications' => [
            'fields' => [
                'user_id' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'nonce' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32
                ],
                'token' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32
                ],
                'role' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'default' => 'read'
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 11
                ],
                'single_use' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ]
            ],
            'keys' => ['user_id', 'nonce', 'token']
        ],

        // ===========
        // = PLUGINS =
        // ===========
        'plugins' => [
            'fields' => [
                'path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'setup' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1
                ],
                'data' => [
                    'type' => 'LONGTEXT'
                ]
            ],
            'keys' => ['path']
        ],

        // ==========
        // = ALBUMS =
        // ==========
        'albums' => [
            'fields' => [
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'old_slug' => [
                    'type' => 'TEXT'
                ],
                'summary' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'description' => [
                    'type' => 'TEXT'
                ],
                'sort' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'default' => 'manual ASC'
                ],
                'visibility' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'level' => [
                    'type' => 'INT',
                    'default' => 1
                ],
                'left_id' => [
                    'type' => 'INT'
                ],
                'right_id' => [
                    'type' => 'INT'
                ],
                'deleted' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'featured' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'featured_on' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'featured_order' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'total_count' => [
                    'type' => 'INT',
                    'default' => 0
                ],
                'video_count' => [
                    'type' => 'INT',
                    'default' => 0
                ],
                'published_on' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'modified_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'album_type' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'internal_id' => [
                    'type' => 'CHAR',
                    'constraint' => 32
                ]
            ],
            'keys' => [
                'deleted',
                'level',
                'left_id',
                'right_id',
                'total_count',
                'video_count',
                'created_on',
                'published_on',
                'modified_on',
                'album_type',
                'internal_id',
                ['featured', 'featured_order'],
                'slug'
            ]
        ],

        // ==============================
        // = ALBUMS > TEXT (TOPICS] JOIN TABLE =
        // ==============================
        'join_albums_text' => [
            'fields' => [
                'album_id' => [
                    'type' => 'INT'
                ],
                'text_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'album_id', 'text_id'
            ]
        ],

        // ===========
        // = CONTENT =
        // ===========
        'content' => [
            'fields' => [
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'old_slug' => [
                    'type' => 'TEXT'
                ],
                'filename' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'caption' => [
                    'type' => 'TEXT'
                ],
                'visibility' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'max_download' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'license' => [
                    'type' => 'CHAR',
                    'constraint' => 3,
                    'default' => 'all'
                ],
                'deleted' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'featured' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'featured_order' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'favorite_order' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'favorite' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'favorited_on' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'featured_on' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'uploaded_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'captured_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'published_on' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'modified_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'file_modified_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'focal_point' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'filesize' => [
                    'type' => 'INT'
                ],
                'width' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'height' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'aspect_ratio' => [
                    'type' => 'DECIMAL(5,3)',
                    'null' => true
                ],
                'duration' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'file_type' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'lg_preview' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'internal_id' => [
                    'type' => 'CHAR',
                    'constraint' => 32
                ],
                'has_exif' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'has_iptc' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'source' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'source_url' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'html' => [
                    'type' => 'TEXT'
                ],
                'storage_url' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'storage_url_midsize' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ]
            ],
            'keys' => [
                'filename',
                'title',
                'deleted',
                'uploaded_on',
                'captured_on',
                'modified_on',
                'published_on',
                'filesize',
                'file_type',
                'has_iptc',
                'has_exif',
                'width',
                'height',
                'aspect_ratio',
                ['featured', 'featured_order'],
                ['favorite', 'favorite_order'],
                'slug',
                // TODO: How to key the caption field
            ]
        ],

        // ==============================
        // = CONTENT > ALBUMS JOIN TABLE =
        // ==============================
        'join_albums_content' => [
            'fields' => [
                'album_id' => [
                    'type' => 'INT'
                ],
                'content_id' => [
                    'type' => 'INT'
                ],
                'order' => [
                    'type' => 'INT',
                    'null' => true
                ]
            ],
            'keys' => [
                'album_id', 'content_id', 'order'
            ]
        ],

        // ==============================
        // = COVERS > ALBUMS JOIN TABLE =
        // ==============================
        'join_albums_covers' => [
            'fields' => [
                'album_id' => [
                    'type' => 'INT'
                ],
                'cover_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'album_id', 'cover_id'
            ]
        ],

        // =========
        // = USERS =
        // =========
        'users' => [
            'fields' => [
                'password' => [
                    'type' => 'varchar',
                    'constraint' => 60
                ],
                'email' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'modified_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'first_name' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'last_name' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'public_first_name' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'public_last_name' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'public_display' => [
                    'type' => 'varchar',
                    'constraint' => 255,
                    'default' => 'both'
                ],
                'public_email' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'twitter' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'facebook' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'google' => [
                    'type' => 'varchar',
                    'constraint' => 255
                ],
                'internal_id' => [
                    'type' => 'CHAR',
                    'constraint' => 32
                ],
                'remember_me' => [
                    'type' => 'CHAR',
                    'constraint' => 32,
                    'null' => true
                ]
            ],
            'keys' => [
                'password',
                'email',
                'internal_id'
            ]
        ],

        // ===========
        // = HISTORY =
        // ===========
        'history' => [
            'fields' => [
                'user_id' => [
                    'type' => 'INT'
                ],
                'message' => [
                    'type' => 'TEXT'
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
            ],
            'keys' => [
                'user_id', 'created_on'
            ]
        ],

        // =========
        // = TRASH =
        // =========
        'trash' => [
            'fields' => [
                'id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'data' => [
                    'type' => 'TEXT'
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ]
            ],
            'keys' => [
                'id', 'created_on'
            ],
            'no_id' => true
        ],

        // ===========
        // TAG CACHE =
        // ===========
        'tags' => [
            'fields' => [
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'modified_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'last_used' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'album_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'text_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'content_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ]
            ],
            'keys' => [
                'name'
            ]
        ],

        // ============
        // CATEGORIES =
        // ============
        'categories' => [
            'fields' => [
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'album_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'text_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ],
                'content_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0
                ]
            ],
            'keys' => [
                'album_count',
                'content_count',
                'text_count',
                'slug'
            ]
        ],

        // ==================================
        // = CATEGORIES > ALBUMS JOIN TABLE =
        // ==================================
        'join_albums_categories' => [
            'fields' => [
                'album_id' => [
                    'type' => 'INT'
                ],
                'category_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'album_id', 'category_id'
            ]
        ],

        // ===================================
        // = CATEGORIES > CONTENT JOIN TABLE =
        // ===================================
        'join_categories_content' => [
            'fields' => [
                'content_id' => [
                    'type' => 'INT'
                ],
                'category_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'content_id', 'category_id'
            ]
        ],

        // ============
        // SITE =
        // ============
        'settings' => [
            'fields' => [
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'value' => [
                    'type' => 'TEXT'
                ]
            ],
            'keys' => [ 'name' ]
        ],

        // ============
        // URLS =
        // ============
        'urls' => [
            'fields' => [
                'data' => [
                    'type' => 'TEXT'
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ]
            ]
        ],

        // ============
        // SLUGS =
        // ============
        'slugs' => [
            'fields' => [
                'id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ]
            ],
            'keys' => [
                'id'
            ],
            'no_id' => true
        ],

        // ============
        // DRAFTS =
        // ============
        'drafts' => [
            'fields' => [
                'path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'live_data' => [
                    'type' => 'MEDIUMTEXT',
                    'null' => true
                ],
                'data' => [
                    'type' => 'MEDIUMTEXT'
                ],
                'current' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'draft' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'modified_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ]
            ],
            'keys' => [
                'path', 'current', 'draft', 'created_on', 'modified_on'
            ]
        ],

        // ============
        // PAGES =
        // ============
        'text' => [
            'fields' => [
                'title' => [
                    'type' => 'TEXT'
                ],
                'draft_title' => [
                    'type' => 'TEXT'
                ],
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'old_slug' => [
                    'type' => 'TEXT'
                ],
                'featured_image_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'featured' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'featured_on' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'featured_order' => [
                    'type' => 'INT',
                    'null' => true
                ],
                'custom_featured_image' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'content' => [
                    'type' => 'LONGTEXT'
                ],
                'draft' => [
                    'type' => 'LONGTEXT'
                ],
                'excerpt' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255
                ],
                'published' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'page_type' => [
                    'type' => 'INT',
                    'constraint' => 1,
                    'default' => 0
                ],
                'published_on' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'null' => true
                ],
                'created_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'modified_on' => [
                    'type' => 'INT',
                    'constraint' => 10
                ],
                'internal_id' => [
                    'type' => 'CHAR',
                    'constraint' => 32
                ]
            ],
            'keys' => [
                'published',
                'created_on',
                'modified_on',
                'published_on',
                'page_type',
                'internal_id',
                'featured_image_id',
                'slug',
                ['featured', 'featured_order']
            ]
        ],

        // ===================================
        // = CATEGORIES > PAGES JOIN TABLE =
        // ===================================
        'join_categories_text' => [
            'fields' => [
                'text_id' => [
                    'type' => 'INT'
                ],
                'category_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'text_id', 'category_id'
            ]
        ],

        // ===================================
        // = CATEGORIES > PAGES JOIN TABLE =
        // ===================================
        'join_tags_text' => [
            'fields' => [
                'tag_id' => [
                    'type' => 'INT'
                ],
                'text_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'text_id', 'tag_id'
            ]
        ],

        // ===================================
        // = CATEGORIES > PAGES JOIN TABLE =
        // ===================================
        'join_albums_tags' => [
            'fields' => [
                'tag_id' => [
                    'type' => 'INT'
                ],
                'album_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'tag_id', 'album_id'
            ]
        ],

        // ===================================
        // = CATEGORIES > PAGES JOIN TABLE =
        // ===================================
        'join_content_tags' => [
            'fields' => [
                'tag_id' => [
                    'type' => 'INT'
                ],
                'content_id' => [
                    'type' => 'INT'
                ]
            ],
            'keys' => [
                'tag_id', 'content_id'
            ]
        ]
    ];
