<?php

class Update extends Koken_Controller {

  var $migrate_path;

  function __construct()
  {
    $this->strict_cookie_auth = false;
    $this->migrate_path = FCPATH . 'app' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR;
    parent::__construct();
  }

  function plugin()
  {
    if ($this->method !== 'post')
    {
      $this->error('403', 'Forbidden');
      return;
    }

    $local = $this->input->post('local_path');
    $guid = $this->input->post('guid');
    $install_id = $this->input->post('uuid');
    $purchase = $this->input->post('purchase');
    $is_new = $this->input->post('new');
    $plugin_id = $this->input->post('id');

    $base = FCPATH . 'storage' . DIRECTORY_SEPARATOR;

    $tmp_path = $base . 'tmp';

    $remote = KOKEN_STORE_URL . '/plugins/download/' . $guid . '/for/' . $install_id . ($purchase ? '/' . $purchase : '');

    $local_zip = $tmp_path . DIRECTORY_SEPARATOR . 'update.zip';

    $updated_theme = $tmp_path . DIRECTORY_SEPARATOR . $guid;

    $current_path = $base . $local;

    if (is_dir("$current_path.off"))
    {
      delete_files("$current_path.off", true, 1);
    }

    if (is_dir("$current_path/.git"))
    {
      delete_files("$current_path/.git", true, 1);
    }

    make_child_dir($tmp_path);

    $success = false;

    $old_mask = umask(0);

    if (ENVIRONMENT === 'development' && !$is_new)
    {
      $success = true;
      sleep(3);
    }
    else if ($this->_download($remote, $local_zip))
    {
      $this->load->library('unzip');
      $this->unzip->extract($local_zip);

      if (file_exists($updated_theme . DIRECTORY_SEPARATOR . 'info.json') || file_exists($updated_theme . DIRECTORY_SEPARATOR . 'pulse.json') || file_exists($updated_theme . DIRECTORY_SEPARATOR . 'plugin.json'))
      {
        if ($is_new || rename($current_path, "$current_path.off"))
        {
          if (rename($updated_theme, $current_path))
          {
            // Hack for watermark update issue. Can removed this eventually (0.15.1 was when this was implemented)
            if (file_exists("$current_path.off/image.png"))
            {
              copy("$current_path.off/image.png", "$current_path/storage/image.png");
            }
            // /Hack

            // Copy storage folder to ensure userland files survive update
            if (is_dir("$current_path.off/storage"))
            {
              delete_files("$current_path/storage", true, 1);
              rename("$current_path.off/storage", "$current_path/storage");
            }

            delete_files("$current_path.off", true, 1);
            $success = true;

            if (file_exists($current_path . DIRECTORY_SEPARATOR . 'info.json'))
            {
              $json = $current_path . DIRECTORY_SEPARATOR . 'info.json';
            }
            else if (file_exists($current_path . DIRECTORY_SEPARATOR . 'pulse.json'))
            {
              $json = $current_path . DIRECTORY_SEPARATOR . 'pulse.json';
            }
            else
            {
              $json = $current_path . DIRECTORY_SEPARATOR . 'plugin.json';
            }

            $json = json_decode(file_get_contents($json, true));
          }
          else
          {
            $json = 'Could not rename folder.';
          }
        }
        else
        {
          $json = 'Could not rename old version before upgrading';
        }
      }
      else
      {
        $json = 'Could not download plugin ZIP.';
      }

      unlink($local_zip);
      delete_files($updated_theme, true, 1);
    }

    umask($old_mask);

    if ($plugin_id)
    {
      $p = new Plugin;
      $p->where('id', $plugin_id)->get();

      if ($p->exists())
      {
        $plugins = $this->parse_plugins();
        $plugin = $p->init($plugins);

        if ($plugin->database_fields)
        {
          $db_config = Shutter::get_db_configuration();

          $this->load->dbforge();
          foreach($plugin->database_fields as $table => $fields)
          {
            $table = $db_config['prefix'] . $table;
            foreach($fields as $column => $info)
            {
              if (in_array(strtolower($info['type']), array('text', 'varchar', 'longtext')))
              {
                $info['null'] = true;
              }

              $this->dbforge->add_column($table, array( $column => $info ));
            }
          }

          $this->_clear_datamapper_cache();
        }
        $p->run_plugin_method('after_upgrade', $plugins);

        if (file_exists($current_path . DIRECTORY_SEPARATOR . 'migrate.php'))
        {
          include($current_path . DIRECTORY_SEPARATOR . 'migrate.php');
        }
      }
    }

    $this->_compile_plugins();

    die( json_encode( array('done' => $success, 'info' => isset($json) ? $json : array() ) ) );

  }

  function migrate($n = false)
  {
    if ($this->method !== 'post')
    {
      $this->error('403', 'Forbidden');
      return;
    }

    $CI =& get_instance();
    $this->db =& $CI->db;

    $db_config = Shutter::get_db_configuration();

    $this->load->dbforge();

    if ($n === 'schema')
    {
      require(FCPATH . 'app' .
                DIRECTORY_SEPARATOR . 'koken' .
                DIRECTORY_SEPARATOR . 'schema.php');

      foreach($koken_tables as $table_name => $info)
      {
        $table = $db_config['prefix'] . "$table_name";
        if ($this->db->table_exists($table))
        {
          $existing_fields = array();
          foreach($this->db->field_data($table) as $field)
          {
            $existing_fields[$field->name] = $field;
          }

          foreach($info['fields'] as $field_name => $field_info)
          {
            if (array_key_exists($field_name, $existing_fields))
            {
              $field_info['type'] = strtolower($field_info['type']);

              $compare = (array) $existing_fields[$field_name];

              unset($compare['name']);
              unset($compare['primary_key']);

              if (isset($compare['max_length']))
              {
                $compare['constraint'] = (int) $compare['max_length'];
                unset($compare['max_length']);
              }

              if (in_array(strtolower($field_info['type']), array('text', 'varchar', 'longtext')))
              {
                $field_info['null'] = true;
              }

              $diff = array_diff_assoc( $field_info, $compare );

              if (isset($diff['null']) && $diff['null'] === true && is_null($compare['default']) && $field_info['type'] !== 'text' && $field_info['type'] !== 'varchar')
              {
                unset($diff['null']);
              }

              if (!empty( $diff ))
              {
                $this->dbforge->modify_column($table, array($field_name => $field_info));
              }
            }
            else
            {
              if (in_array(strtolower($field_info['type']), array('text', 'varchar', 'longtext')))
              {
                $field_info['null'] = true;
              }

              $this->dbforge->add_column($table, array( $field_name => $field_info ));
            }
          }

          if (isset($info['keys']))
          {
            foreach($info['keys'] as $key)
            {
              if (is_array($key))
              {
                $key_name = $this->db->_protect_identifiers(implode('_', $key));
                $key = $this->db->_protect_identifiers($key);
              }
              else
              {
                $key_name = $this->db->_protect_identifiers($key);
                $key = array($key_name);
              }

              $sql = "ALTER TABLE $table ADD KEY {$key_name} (" . implode(', ', $key) . ")";
              $this->db->query($sql);
            }
          }

          if (isset($info['uniques']))
          {
            foreach($info['uniques'] as $key)
            {
              $this->db->query("CREATE UNIQUE INDEX $key ON $table ($key)");
            }
          }
        }
        else
        {
          if (!isset($info['no_id']))
          {
            $this->dbforge->add_field('id');
          }
          $this->dbforge->add_field($info['fields']);
          if (isset($info['keys']))
          {
            foreach($info['keys'] as $key)
            {
              $primary = false;
              if ($key == 'id')
              {
                $primary = true;
              }
              $this->dbforge->add_key($key, $primary);
            }
          }
          $this->dbforge->create_table($db_config['prefix'] . "$table_name");

          if (isset($info['uniques']))
          {
            $table = $db_config['prefix'] . "$table_name";
            foreach($info['uniques'] as $key)
            {
              $this->db->query("CREATE UNIQUE INDEX $key ON $table ($key)");
            }
          }
        }
      }

      $this->_clear_system_caches();

      $s = new Setting;
      $s->where('name', 'uuid')->get();

      if (!$s->exists())
      {
        $s = new Setting;
        $s->name = 'uuid';
        $s->value = md5($_SERVER['HTTP_HOST'] . uniqid('', true));
        $s->save();
      }

      $uuid = $s->value;

      $base_folder = trim(preg_replace('/\/api\.php(.*)?$/', '', $_SERVER['SCRIPT_NAME']), '/');

      include(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'DarkroomUtils.php');

      $s->where('name', 'image_processing_library')->get();
      $libs = DarkroomUtils::libraries();
      $processing_string = $libs[$s->value]['label'];

      $themes = array(
        'axis' => '86d2f683-9f90-ca3f-d93f-a2e0a9d0a089',
        'blueprint' => '1a355994-6217-c7ce-b67a-4241be3feae8',
        'boulevard' => 'b30686d9-3490-9abb-1049-fe419a211502',
        'chastain' => 'd174e766-5a5f-19eb-d735-5b46ae673a6d',
        'elementary' => 'be1cb2d9-ed05-2d81-85b4-23282832eb84',
        'madison' => '618e0b9f-fba0-37eb-810a-6d615d0f0e08',
        'observatory' => '605ea246-fa37-11f0-f078-d54c8a7cbd3c',
        'regale' => 'efde04b6-657d-33b6-767d-67af8ef15e7b',
        'repertoire' => 'fa8a5d39-01a5-dfd6-92ff-65a22af5d5ac'
      );

      $themes_dir = FCPATH .
              'storage' . DIRECTORY_SEPARATOR .
              'themes' . DIRECTORY_SEPARATOR;

      foreach($themes as $name => $guid)
      {
        $dir = $themes_dir . $name;
        $guid_path = $dir . DIRECTORY_SEPARATOR . 'koken.guid';
        $old_guid_path = $dir . DIRECTORY_SEPARATOR . '.guid';
        if (file_exists($old_guid_path))
        {
          rename($old_guid_path, $guid_path);
        }
        else if (is_dir($dir) && !file_exists($guid_path))
        {
          file_put_contents($guid_path, $guid);
        }
      }

      $plugins = array(
        'google-analytics' => 'c4e5bc2b-be8b-3ae7-ccbe-d7e7a1a26136',
        'font-loader' => '5b6016ae-9d1a-2336-78c4-63dbb74d39b3',
        'koken-spotify' => 'e24a53fc-ac9a-5ab6-5777-237f6dc98496',
        'koken-rdio' => '84eb1b9a-ea40-c204-5420-c1af5e1bcbe6',
        'koken-html-injector' => '045cb01a-07a6-02b6-a0df-2ae377ce18af',
        'koken-pulse-timer' => '6e5cbaa3-9fee-ca89-c989-a7969aa491f3',
        'koken-pulse-transition-pack' => '7e958135-8e3e-3b34-5ccd-defe39db9400',
        'koken-disqus' => '0a430465-cb52-be7d-a160-94bf73e40c03',
        'koken-timeago' => 'bf4ceae8-b2b8-dc16-a439-46a4d915161c',
      );

      $plugins_dir = FCPATH .
              'storage' . DIRECTORY_SEPARATOR .
              'plugins' . DIRECTORY_SEPARATOR;

      foreach($plugins as $name => $guid)
      {
        $dir = $plugins_dir . $name;
        $guid_path = $dir . DIRECTORY_SEPARATOR . 'koken.guid';
        if (is_dir($dir) && !file_exists($guid_path))
        {
          file_put_contents($guid_path, $guid);
        }
      }

      $this->load->library('webhostwhois');

      $host = new WebhostWhois(array(
        'useDns' => false
      ));

      if ($host->key === 'unknown' && isset($_SERVER['KOKEN_HOST']))
      {
        $host->key = $_SERVER['KOKEN_HOST'];
      }

      $data = array(
        'domain' => $_SERVER['HTTP_HOST'],
        'path' => '/' . $base_folder,
        'uuid' => $uuid,
        'php' => PHP_VERSION,
        'version' => KOKEN_VERSION,
        'ip' => $_SERVER['SERVER_ADDR'],
        'image_processing' => urlencode($processing_string),
        'host' => $host->key,
        'plugins' => array(),
      );

      $s = new Setting;
      $s->where('name', 'site_url')->get();

      if ($s->value !== 'default')
      {
        $data['published_path'] = $s->value;
      }

      $t = new Theme;
      $themes = $t->read();

      foreach($themes as $theme)
      {
        if (isset($theme['koken_store_guid']))
        {
          $data['plugins'][] = array(
            'guid' => $theme['koken_store_guid'],
            'version' => $theme['version'],
          );
        }
      }

      $plugins = $this->parse_plugins();

      foreach($plugins as $plugin)
      {
        if (isset($plugin['koken_store_guid']))
        {
          $data['plugins'][] = array(
            'guid' => $plugin['koken_store_guid'],
            'version' => $plugin['version'],
          );
        }
      }

      if (!isset($_COOKIE['koken_session']) && !isset($_COOKIE['koken_session_ci']))
      {
        // Catch upgrades with old auth setup and try to keep them logged in.
        $u = new User;
        $u->get_by_id($this->auth_user_id);

        if ($u->exists())
        {
          $this->load->library('session');
          $u->create_session($this->session);
        }
      }

      // Session upgrade to CI sessions (0.14)
      if (!isset($_COOKIE['koken_session_ci']) && isset($_COOKIE['koken_session']))
      {
        $old_session = unserialize($_COOKIE['koken_session']);
        if ($old_session)
        {
          $u = new User;
          $u->get_by_id($old_session['user']['id']);

          if ($u->exists())
          {
            $this->load->library('session');
            $u->create_session($this->session);
          }
        }
      }

      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, KOKEN_STORE_URL . '/register');
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      $r = curl_exec($curl);
      curl_close($curl);

      die( json_encode( array('done' => true) ) );
    }
    else if ($n)
    {
      $path = $this->migrate_path . "$n.php";
      $migrate_setting = new Setting;
      $migrate_setting->where('name', 'last_migration')->get();
      if (is_file($path))
      {
        include($path);

        $is_done = isset($done);

        if ($migrate_setting->exists() && $is_done)
        {
          $migrate_setting->value = (int) $n;
          $migrate_setting->save();
        }

        die( json_encode( array('done' => $is_done) ) );
        exit;
      }
    }
  }

  function index()
  {
    if ($this->method !== 'post')
    {
      $this->error('403', 'Forbidden');
      return;
    }

    copy(FCPATH . 'app/koken/recover.php', FCPATH . 'recover.php');

    function rollback($back)
    {
      foreach($back as $b)
      {
        $f = FCPATH . $b;
        $dest = str_replace('.off', '', $f);
        if (is_dir($dest))
        {
          delete_files($dest, true, 1);
        }
        else if (file_exists($dest))
        {
          unlink($dest);
        }
        @rename($f, $dest);
      }
    }

    function fail($msg = 'Koken does not have the necessary permissions to perform the update automatically. Try setting the permissions on the entire Koken folder to 777, then try again.')
    {
      @unlink(FCPATH . 'recover.php');
      delete_files(FCPATH . 'storage/tmp', true);
      die( json_encode( array('error' => $msg) ) );
    }

    $get_core = $this->input->post('url');

    if ($get_core) {

      if (ENVIRONMENT === 'development')
      {
        $manifest = FCPATH . 'manifest.php';

        require $manifest;

        if (count($compatCheckFailures))
        {
          die(json_encode(array(
            'requirements' => $compatCheckFailures
          )));
        }

        //hack
        sleep(2);

        // fail();
        unlink(FCPATH . 'recover.php');

        die(
          json_encode(
            array('migrations' => array('0001.php', '0001.php', '0001.php')
            )
          )
        );
      }

      $old_mask = umask(0);

      $core = FCPATH . 'storage/tmp/core.zip';

      make_child_dir(dirname($core));

      if ($this->_download($get_core, $core)) {

        $this->load->library('unzip');

        $this->unzip->extract($core);

        @unlink($core);

        $manifest = FCPATH . 'storage/tmp/manifest.php';

        require $manifest;

        if (count($compatCheckFailures))
        {
          delete_files(FCPATH . 'storage/tmp', true);

          unlink(FCPATH . 'recover.php');

          die(json_encode(array(
            'requirements' => $compatCheckFailures
          )));
        }

        $migrations_before = scandir($this->migrate_path);

        $moved = array();

        // updateFileList comes from manifest.php
        foreach($updateFileList as $path)
        {
          $fullPath = FCPATH . 'storage/tmp/' . $path;
          $dest = FCPATH . $path;
          $off = $dest . '.off';

          if (!file_exists($fullPath))
          {
            rollback($moved);
            umask($old_mask);
            fail();
          }

          if (file_exists($dest))
          {
            if (file_exists($off))
            {
              delete_files($off, true, 1);
            }

            if (rename($dest, $off))
            {
              $moved[] = $path;
            }
            else
            {
              rollback($moved);
              umask($old_mask);
              fail();
            }
          }

          if (!rename($fullPath, $dest))
          {
            rollback($moved);
            umask($old_mask);
            fail();
          }
        }

        foreach($moved as $m)
        {
          $path = FCPATH . $m . '.off';
          if (is_dir($path))
          {
            delete_files($path, true, 1);
          }
          else if (file_exists($path))
          {
            unlink($path);
          }
        }

        unlink(FCPATH . 'recover.php');

        @unlink(FCPATH . 'manifest.php');

        // Remove temporary update files
        delete_files(FCPATH . 'storage/tmp', true);

        if (is_really_callable('opcache_reset'))
        {
          opcache_reset();
        }

        die(
          json_encode(
            array('migrations' => array_values(
              array_diff(
                scandir($this->migrate_path), $migrations_before)
              )
            )
          )
        );
      } else {
        umask($old_mask);
        @unlink($core);
        fail();
      }
    }
  }
}

/* End of file trashes.php */
/* Location: ./system/application/controllers/trashes.php */
