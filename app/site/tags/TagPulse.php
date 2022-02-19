<?php

	class TagPulse extends Tag {

		function _clean_val($val)
		{
			if (strpos($val, '$') === 0)
			{
				return $val;
			}
			else if (strpos($val, '{$') === false)
			{
				if ($val != 'true' && $val != 'false' && !is_numeric($val))
				{
					$val = "\"$val\"";
				}
			}
			else
			{
				$val = "trim('" . preg_replace('/\{(\$[^}]+)\}/', "' . $1 . '", $val) . "')";
				$val = "is_numeric($val) ? (int) $val : ( $val == 'false' || $val == 'true' ? $val === 'true' : $val)";
			}
			return $val;
		}

		function generate()
		{

			$options = array(
				'group' => 'default',
				'relative' => true,
			);
			$disabled = array();

			$group_wrap = '<?php echo "default"; ?>';

			$style = 'clear:left;';

			$params = array();
			foreach($this->parameters as $key => $val)
			{
				if ($key === 'source' || strpos($key, 'filter:') === 0)
				{
					$params[] = "'$key' => \"" . $this->attr_parse($val) . '"';
					unset($this->parameters[$key]);
				}
				else if ($key === 'style')
				{
					$style .= $this->parameters[$key];
					unset($this->parameters[$key]);
				}
				else
				{
					if ($key === 'group')
					{
						$group_wrap = $this->attr_parse($val, true);
					}
					$val = $this->attr_parse($val);
					if (strpos($key, ':') !== false)
					{
						$bits = explode(':', $key);
						if (in_array($bits[0], $disabled))
						{
							continue;
						}
						if ($bits[1] === 'enabled' && $val == 'false')
						{
							$disabled[] = $bits[0];
							unset($options[$bits[0]]);
						}
						else
						{
							if (!isset($options[$bits[0]]))
							{
								$options[$bits[0]] = array();
							}
							$options[$bits[0]][$bits[1]] = $val;
						}
					}
					else
					{
						$options[$key] = $val;
					}
				}
			}

			$params = join(',', $params);

			if (isset($options['jsvar']))
			{
				$js = 'var ' . $this->attr_parse($options['jsvar'], true) . ' = ';
			}
			else
			{
				$js = '';
			}
			if (isset($options['data_from_url']))
			{
				$options['dataUrl'] = Koken::$location['real_root_folder'] . '/api.php?' . $this->attr_parse($options['data_from_url']);
				unset($options['data_from_url']);
			}
			else if (isset($options['data']))
			{
				$data = $this->field_to_keys('data');
				if (strpos($data, 'covers') !== false)
				{
					$base = str_replace("['covers']", '', $data);
					$options['data'] = "array( 'content' => $data, 'album_id' => {$base}['id'], 'album_type' => {$base}['album_type'] )";
				}
				else
				{
					$options['data'] = "array( 'content' => $data )";
				}
			}

			unset($options['source']);

			$native = array();

			foreach($options as $key => $val)
			{
				if ($key === 'data')
				{
					$native[] = "'$key' => $val";
				}
				else if ($key !== 'group')
				{
					$native[] = "'$key' => " . $this->_clean_val($val);
				}
			}

			if (isset(Koken::$site['urls']['album']))
			{
				$native[] = "'albumUrl' => '" . Koken::$site['urls']['album'] . "'";
			}
			$native = join(', ', $native);

			return <<<OUT
<?php
	\$__id = 'pulse_' . md5(uniqid());
	\$__group = '{$options['group']}';
	\$__native_raw = array($native);

	if (!isset(\$__native_raw['data']) && !isset(\$__native_raw['dataUrl']))
	{
		\$__params = array($params);
		if (isset(\$__params['source']) || Koken::\$source)
		{
			list(\$__url,) = Koken::load( \$__params );
		}
		else if (count(Koken::\$load_history) > 0)
		{
			\$__url = end(Koken::\$load_history);
		}
		if (!is_null(Koken::\$site['draft_id']))
		{
			\$__url .= '/draft:' . Koken::\$site['draft_id'];
		}
		else if (Koken::\$preview)
		{
			\$__url .= '/draft:' . Koken::\$preview;
		}
		\$__native_raw['dataUrl'] = Koken::\$location['real_root_folder'] . '/api.php?' . \$__url;

		if (isset(Koken::\$current_token) && isset(Koken::\$current_token['album']) && Koken::\$current_token['album']['visibility']['raw'] === 'private')
		{
			\$__native_raw['dataUrl'] = preg_replace('~slug:[^/]+~', 'slug:' . Koken::\$current_token['album']['internal_id'], \$__native_raw['dataUrl']);
		}
	}

	if (isset(\$__native_raw['data']['content']))
	{
		foreach(\$__native_raw['data']['content'] as &\$__item)
		{
			if (isset(\$__item['cache_path']))
			{
				if ('{$options['relative']}')
				{
					foreach(\$__item['presets'] as &\$__preset)
					{
						\$__preset['url'] = str_replace(\$__item['cache_path']['prefix'], \$__item['cache_path']['relative_prefix'], \$__preset['url']);
						\$__preset['hidpi_url'] = str_replace(\$__item['cache_path']['prefix'], \$__item['cache_path']['relative_prefix'], \$__preset['url']);
					}

					\$__item['cache_path']['prefix'] = \$__item['cache_path']['relative_prefix'];
				}
				unset(\$__item['cache_path']['relative_prefix']);
			}
		}
	}


	\$__native = array_merge( \$__native_raw, isset(Koken::\$site['pulse_groups'][\$__group]) ? Koken::\$site['pulse_groups'][\$__group] : array() );
	if (\$__group === 'essays' && isset(\$__native_raw['link_to']) && \$__native_raw['link_to'] !== 'default')
	{
		\$__native['link_to'] = \$__native_raw['link_to'];
	}

	if (isset(\$__native['link_to']) && \$__native['link_to'] === 'default')
	{
		\$__native['link_to'] = 'advance';
	}
?>
<div id="<?php echo \$__id; ?>" class="k-pulse" style="$style" data-pulse-group="$group_wrap"></div>
<script>
	{$js}\$K.pulse.register({ id: '<?php echo \$__id; ?>', options: <?php echo json_encode(\$__native); ?> })
</script>
OUT;

		}

	}
