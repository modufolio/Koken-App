<?php

	$koken_tables = array(

		// ================
		// = APPLICATIONS =
		// ================
		'applications' => array(
			'fields' => array(
				'user_id' => array(
					'type' => 'INT',
					'null' => true
				),
				'nonce' => array(
					'type' => 'VARCHAR',
					'constraint' => 32
				),
				'token' => array(
					'type' => 'VARCHAR',
					'constraint' => 32
				),
				'role' => array(
					'type' => 'VARCHAR',
					'constraint' => 10,
					'default' => 'read'
				),
				'name' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 11
				),
				'single_use' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				)
			),
			'keys' => array('user_id', 'nonce', 'token')
		),

		// ===========
		// = PLUGINS =
		// ===========
		'plugins' => array(
			'fields' => array(
				'path' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'setup' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 1
				),
				'data' => array(
					'type' => 'LONGTEXT'
				)
			),
			'keys' => array('path')
		),

		// ==========
		// = ALBUMS =
		// ==========
		'albums' => array(
			'fields' => array(
				'title' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'slug' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'old_slug' => array(
					'type' => 'TEXT'
				),
				'summary' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'description' => array(
					'type' => 'TEXT'
				),
				'sort' => array(
					'type' => 'VARCHAR',
					'constraint' => 255,
					'default' => 'manual ASC'
				),
				'visibility' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'level' => array(
					'type' => 'INT',
					'default' => 1
				),
				'left_id' => array(
					'type' => 'INT'
				),
				'right_id' => array(
					'type' => 'INT'
				),
				'deleted' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'featured' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'featured_on' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'featured_order' => array(
					'type' => 'INT',
					'null' => true
				),
				'total_count' => array(
					'type' => 'INT',
					'default' => 0
				),
				'video_count' => array(
					'type' => 'INT',
					'default' => 0
				),
				'published_on' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'modified_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'album_type' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'internal_id' => array(
					'type' => 'CHAR',
					'constraint' => 32
				)
			),
			'keys' => array(
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
				array('featured', 'featured_order'),
				'slug'
			)
 		),

		// ==============================
		// = ALBUMS > TEXT (TOPICS) JOIN TABLE =
		// ==============================
		'join_albums_text' => array(
			'fields' => array(
				'album_id' => array(
					'type' => 'INT'
				),
				'text_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'album_id', 'text_id'
			)
 		),

		// ===========
		// = CONTENT =
		// ===========
		'content' => array(
			'fields' => array(
				'title' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'slug' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'old_slug' => array(
					'type' => 'TEXT'
				),
				'filename' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'caption' => array(
					'type' => 'TEXT'
				),
				'visibility' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'max_download' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'license' => array(
					'type' => 'CHAR',
					'constraint' => 3,
					'default' => 'all'
				),
				'deleted' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'featured' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'featured_order' => array(
					'type' => 'INT',
					'null' => true
				),
				'favorite_order' => array(
					'type' => 'INT',
					'null' => true
				),
				'favorite' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'favorited_on' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'featured_on' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'uploaded_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'captured_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'published_on' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'modified_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'file_modified_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'focal_point' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'filesize' => array(
					'type' => 'INT'
				),
				'width' => array(
					'type' => 'INT',
					'null' => true
				),
				'height' => array(
					'type' => 'INT',
					'null' => true
				),
				'aspect_ratio' => array(
					'type' => 'DECIMAL(5,3)',
					'null' => true
				),
				'duration' => array(
					'type' => 'INT',
					'null' => true
				),
				'file_type' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'lg_preview' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'internal_id' => array(
					'type' => 'CHAR',
					'constraint' => 32
				),
				'has_exif' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'has_iptc' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'source' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'source_url' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'html' => array(
					'type' => 'TEXT'
				),
				'storage_url' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'storage_url_midsize' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				)
			),
			'keys' => array(
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
				array('featured', 'featured_order'),
				array('favorite', 'favorite_order'),
				'slug',
				// TODO: How to key the caption field
			)
 		),

		// ==============================
		// = CONTENT > ALBUMS JOIN TABLE =
		// ==============================
		'join_albums_content' => array(
			'fields' => array(
				'album_id' => array(
					'type' => 'INT'
				),
				'content_id' => array(
					'type' => 'INT'
				),
				'order' => array(
					'type' => 'INT',
					'null' => true
				)
			),
			'keys' => array(
				'album_id', 'content_id', 'order'
			)
 		),

 		// ==============================
		// = COVERS > ALBUMS JOIN TABLE =
		// ==============================
		'join_albums_covers' => array(
			'fields' => array(
				'album_id' => array(
					'type' => 'INT'
				),
				'cover_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'album_id', 'cover_id'
			)
 		),

		// =========
		// = USERS =
		// =========
		'users' => array(
			'fields' => array(
				'password' => array(
					'type' => 'varchar',
					'constraint' => 60
				),
				'email' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'modified_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'first_name' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'last_name' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'public_first_name' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'public_last_name' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'public_display' => array(
					'type' => 'varchar',
					'constraint' => 255,
					'default' => 'both'
				),
				'public_email' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'twitter' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'facebook' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'google' => array(
					'type' => 'varchar',
					'constraint' => 255
				),
				'internal_id' => array(
					'type' => 'CHAR',
					'constraint' => 32
				),
				'remember_me' => array(
					'type' => 'CHAR',
					'constraint' => 32,
					'null' => true
				)
			),
			'keys' => array(
				'password',
				'email',
				'internal_id'
			)
		),

		// ===========
		// = HISTORY =
		// ===========
		'history' => array(
			'fields' => array(
				'user_id' => array(
					'type' => 'INT'
				),
				'message' => array(
					'type' => 'TEXT'
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
			),
			'keys' => array(
				'user_id', 'created_on'
			)
 		),

		// =========
		// = TRASH =
		// =========
		'trash' => array(
			'fields' => array(
				'id' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'data' => array(
					'type' => 'TEXT'
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				)
			),
			'keys' => array(
				'id', 'created_on'
			),
			'no_id' => true
 		),

 		// ===========
 		// TAG CACHE =
 		// ===========
 		'tags' => array(
			'fields' => array(
				'name' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'modified_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'last_used' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'album_count' => array(
					'type' => 'INT',
					'constraint' => 11,
					'default' => 0
				),
				'text_count' => array(
					'type' => 'INT',
					'constraint' => 11,
					'default' => 0
				),
				'content_count' => array(
					'type' => 'INT',
					'constraint' => 11,
					'default' => 0
				)
			),
			'keys' => array(
				'name'
			)
 		),

 		// ============
 		// CATEGORIES =
 		// ============
 		'categories' => array(
			'fields' => array(
				'title' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'slug' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'album_count' => array(
					'type' => 'INT',
					'constraint' => 11,
					'default' => 0
				),
				'text_count' => array(
					'type' => 'INT',
					'constraint' => 11,
					'default' => 0
				),
				'content_count' => array(
					'type' => 'INT',
					'constraint' => 11,
					'default' => 0
				)
			),
			'keys' => array(
				'album_count',
				'content_count',
				'text_count',
				'slug'
			)
 		),

 		// ==================================
		// = CATEGORIES > ALBUMS JOIN TABLE =
		// ==================================
		'join_albums_categories' => array(
			'fields' => array(
				'album_id' => array(
					'type' => 'INT'
				),
				'category_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'album_id', 'category_id'
			)
 		),

 		// ===================================
		// = CATEGORIES > CONTENT JOIN TABLE =
		// ===================================
		'join_categories_content' => array(
			'fields' => array(
				'content_id' => array(
					'type' => 'INT'
				),
				'category_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'content_id', 'category_id'
			)
 		),

 		// ============
 		// SITE =
 		// ============
 		'settings' => array(
			'fields' => array(
				'name' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'value' => array(
					'type' => 'TEXT'
				)
			),
			'keys' => array( 'name' )
 		),

 		// ============
 		// URLS =
 		// ============
 		'urls' => array(
			'fields' => array(
				'data' => array(
					'type' => 'TEXT'
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				)
			)
 		),

 		// ============
 		// SLUGS =
 		// ============
 		'slugs' => array(
			'fields' => array(
				'id' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				)
			),
			'keys' => array(
				'id'
			),
			'no_id' => true
 		),

 		// ============
 		// DRAFTS =
 		// ============
 		'drafts' => array(
			'fields' => array(
				'path' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'live_data' => array(
					'type' => 'MEDIUMTEXT',
					'null' => true
				),
				'data' => array(
					'type' => 'MEDIUMTEXT'
				),
				'current' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'draft' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'modified_on' => array(
					'type' => 'INT',
					'constraint' => 10
				)
			),
			'keys' => array(
				'path', 'current', 'draft', 'created_on', 'modified_on'
			)
		),

		// ============
 		// PAGES =
 		// ============
	 	'text' => array(
			'fields' => array(
				'title' => array(
					'type' => 'TEXT'
				),
				'draft_title' => array(
					'type' => 'TEXT'
				),
				'slug' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'old_slug' => array(
					'type' => 'TEXT'
				),
				'featured_image_id' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'featured' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'featured_on' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'featured_order' => array(
					'type' => 'INT',
					'null' => true
				),
				'custom_featured_image' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'content' => array(
					'type' => 'LONGTEXT'
				),
				'draft' => array(
					'type' => 'LONGTEXT'
				),
				'excerpt' => array(
					'type' => 'VARCHAR',
					'constraint' => 255
				),
				'published' => array(
					'type' => 'TINYINT',
					'constraint' => 1,
					'default' => 0
				),
				'page_type' => array(
					'type' => 'INT',
					'constraint' => 1,
					'default' => 0
				),
				'published_on' => array(
					'type' => 'INT',
					'constraint' => 10,
					'null' => true
				),
				'created_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'modified_on' => array(
					'type' => 'INT',
					'constraint' => 10
				),
				'internal_id' => array(
					'type' => 'CHAR',
					'constraint' => 32
				)
			),
			'keys' => array(
				'published',
				'created_on',
				'modified_on',
				'published_on',
				'page_type',
				'internal_id',
				'featured_image_id',
				'slug',
				array('featured', 'featured_order')
			)
 		),

 		// ===================================
		// = CATEGORIES > PAGES JOIN TABLE =
		// ===================================
		'join_categories_text' => array(
			'fields' => array(
				'text_id' => array(
					'type' => 'INT'
				),
				'category_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'text_id', 'category_id'
			)
 		),

 		// ===================================
		// = CATEGORIES > PAGES JOIN TABLE =
		// ===================================
		'join_tags_text' => array(
			'fields' => array(
				'tag_id' => array(
					'type' => 'INT'
				),
				'text_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'text_id', 'tag_id'
			)
 		),

 		// ===================================
		// = CATEGORIES > PAGES JOIN TABLE =
		// ===================================
		'join_albums_tags' => array(
			'fields' => array(
				'tag_id' => array(
					'type' => 'INT'
				),
				'album_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'tag_id', 'album_id'
			)
 		),

 		// ===================================
		// = CATEGORIES > PAGES JOIN TABLE =
		// ===================================
		'join_content_tags' => array(
			'fields' => array(
				'tag_id' => array(
					'type' => 'INT'
				),
				'content_id' => array(
					'type' => 'INT'
				)
			),
			'keys' => array(
				'tag_id', 'content_id'
			)
 		)
	);