<?php

class Install extends CI_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function complete()
	{
		set_time_limit(0);

		$this->load->database();
		$this->load->dbforge();

        $koken_tables = require(FCPATH . 'app/koken/schema.php');

		foreach($koken_tables as $table_name => $info)
		{
			if (!isset($info['no_id']))
			{
				$this->dbforge->add_field('id');
			}

			foreach($info['fields'] as $name => &$attr)
			{
				if (in_array(strtolower($attr['type']), array('text', 'varchar', 'longtext')) && $name !== 'id')
				{
					$attr['null'] = true;
				}
			}

			$this->dbforge->add_field($info['fields']);
			foreach($info['keys'] as $key)
			{
				$primary = false;
				if ($key == 'id')
				{
					$primary = true;
				}
				$this->dbforge->add_key($key, $primary);
			}
			$this->dbforge->create_table($table_name);

			if (isset($info['uniques']))
			{
				$table = $table_name;
				foreach($info['uniques'] as $key)
				{
					if (is_array($key))
					{
						$name = join('_', $key);
						$key = join(',', $key);
					}
					else
					{
						$name = $key;
					}
					$this->db->query("CREATE UNIQUE INDEX $name ON $table ($key)");
				}
			}
		}

		$this->load->library('datamapper');


		$settings = array(
			'site_timezone' => null,
			'console_show_notifications' => 'yes',
			'console_enable_keyboard_shortcuts' => 'yes',
			'uploading_default_license' => 'all',
			'uploading_default_visibility' => 'public',
			'uploading_default_album_visibility' => 'public',
			'uploading_default_max_download_size' => 'none',
			'uploading_publish_on_captured_date' => 'false',
			'site_title' => 'Koken Site',
			'site_page_title' => 'Koken Site',
			'site_tagline' => 'Your site tagline',
			'site_copyright' => '© ',
			'site_description' => '',
			'site_keywords' => 'photography',
			'site_date_format' => 'F j, Y',
			'site_time_format' => 'g:i a',
			'site_privacy' => 'public',
			'site_hidpi' => 'true',
			'site_url' => 'default',
			'use_default_labels_links' => 'true',
			'uuid' => md5($_SERVER['HTTP_HOST'] . uniqid('', true)),
			'retain_image_metadata' => 'false',
			'image_use_defaults' => 'true',
			'image_tiny_quality' => '80',
			'image_small_quality' => '80',
			'image_medium_quality' => '85',
			'image_medium_large_quality' => '85',
			'image_large_quality' => '85',
			'image_xlarge_quality' => '90',
			'image_huge_quality' => '90',
			'image_tiny_sharpening' => '0.7',
			'image_small_sharpening' => '0.6',
			'image_medium_sharpening' => '0.6',
			'image_medium_large_sharpening' => '0.6',
			'image_large_sharpening' => '0.6',
			'image_xlarge_sharpening' => '0.3',
			'image_huge_sharpening' => '0',
			'last_upload' => 'false',
			'last_migration' => '42',
			'has_toured' => false,
			'email_handler' => 'DDI_Email',
			'email_delivery_address' => '',
            'image_processing_library' => 'gd'
		);

		foreach($settings as $name => $value)
		{
			$u = new Setting;
			$u->name = $name;
			$u->value = $value;
			$u->save();
		}

		$urls = array(
			array(
				'type' => 'content',
				'data' => array(
					'singular' => 'Content',
					'plural' => 'Content',
					'order' => 'published_on DESC',
					'url' => 'slug',
				)
			),
			array(
				'type' => 'favorite',
				'data' => array(
					'singular' => 'Favorite',
					'plural' => 'Favorites',
					'order' => 'manual ASC'
				)
			),
			array(
				'type' => 'feature',
				'data' => array(
					'singular' => 'Feature',
					'plural' => 'Features',
					'order' => 'manual ASC'
				)
			),
			array(
				'type' => 'album',
				'data' => array(
					'singular' => 'Album',
					'plural' => 'Albums',
					'order' => 'manual ASC',
					'url' => 'slug'
				)
			),
			array(
				'type' => 'set',
				'data' => array(
					'singular' => 'Set',
					'plural' => 'Sets',
					'order' => 'title ASC'
				)
			),
			array(
				'type' => 'essay',
				'data' => array(
					'singular' => 'Essay',
					'plural' => 'Essays',
					'order' => 'published_on DESC',
					'url' => 'date+slug'
				)
			),
			array(
				'type' => 'page',
				'data' => array(
					'singular' => 'Page',
					'plural' => 'Pages',
					'url' => 'slug'
				)
			),
			array(
				'type' => 'tag',
				'data' => array(
					'singular' => 'Tag',
					'plural' => 'Tags'
				)
			),
			array(
				'type' => 'category',
				'data' => array(
					'singular' => 'Category',
					'plural' => 'Categories'
				)
			),
			array(
				'type' => 'timeline',
				'data' => array(
					'singular' => 'Timeline',
					'plural' => 'Timeline'
				)
			)
		);

		$u = new Url;
		$u->data = serialize($urls);
		$u->save();

		$u = new User();
		$u->password = '123456';
		$u->email = 'admin@admin.com';
		$u->first_name = 'Admin';
		$u->last_name = 'Dev';
		$u->permissions = 4;
		$u->save();

		$theme = new Draft;
		$theme->path = 'elementary';
		$theme->current = 1;
		$theme->draft = 1;
		$theme->init_draft_nav();
		$theme->live_data = $theme->data;
		$theme->save();

		$h = new History();
		$h->message = 'system:install';
		$h->save($u->id);

		if (ENVIRONMENT === 'development')
		{
			$app = new Application();
			$app->token = '69ad71aa4e07e9338ac49d33d041941b';
			$app->role = 'read-write';
			$app->save();
		}

		$path = str_replace('api.php', 'app/application/httpd', $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']);
		$ch = curl_init($path);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$c = curl_exec($ch);

		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($code != 500 && $code != 403)
		{
			$htaccess = create_htaccess();
			file_put_contents(FCPATH . '.htaccess', $htaccess, FILE_APPEND);
		}


		$key = md5($_SERVER['HTTP_HOST'] . uniqid('', true));
		Shutter::write_encryption_key($key);

		Shutter::write_cache('plugins/compiled.cache',  serialize(array(
			'info' => array('email_delivery_address' => ''),
			'plugins' => array()
		)));

		Shutter::hook('install.complete');

		header('Content-type: application/json');
		die( json_encode(array('success' => true)) );
	}
}

/* End of file install.php */
/* Location: ./system/application/controllers/install.php */
