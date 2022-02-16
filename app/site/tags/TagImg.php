<?php

	class TagImg extends Tag {

		function generate()
		{
			$defaults = array(
				'width' => 0,
				'height' => 0,
				'crop' => 'false',
				'assign_to_variable' => false,
				'preset' => false,
				'respond_to' => 'width',
				'size' => false,
				'lazy' => false,
				'fade' => true,
				'relative' => true,
			);

			$options = array_merge($defaults, $this->parameters);

			$options['lazy'] = $options['lazy'] === 'true';
			$options['fade'] = $options['fade'] !== 'false';

			if (!isset($this->parameters['alt']))
			{
				$this->parameters['alt'] = '{{ title | filename html_encode="true" }}';
			}
			else if (empty($this->parameters['alt']))
			{
				unset($this->parameters['alt']);
			}

			$data = '';
			$responsive = $custom_data = false;

			if (isset($this->parameters['data']))
			{
				$data = $this->parameters['data'];
				unset($this->parameters['data']);

				if ($options['preset'])
				{
					$data .= '.presets.' . $options['preset'];
				}

				$token = $this->field_to_keys($data);
				$custom_data = true;
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
			}

			if ($options['lazy'])
			{
				$klass = 'k-lazy-loading';
				if ($options['fade'])
				{
					$this->parameters['data-lazy-fade'] = is_numeric($options['fade']) ? $options['fade'] : '400';
				}
				if (isset($this->parameters['class']))
				{
					$this->parameters['class'] .= ' ' . $klass;
				}
				else
				{
					$this->parameters['class'] = $klass;
				}
			}

			$params = $cparams = array();

			foreach($this->parameters as $key => $val)
			{
				if (!isset($defaults[$key]))
				{
					$cval = $this->attr_parse($val);
					$val = $this->attr_parse($val, true);
					$params[] = "$key=\"$val\"";
					$cparams[] = "'$key' => \"$cval\"";
				}
			}

			if ($options['width'] === 0 && $options['height'] === 0 && !$options['preset'])
			{
				if (count($params) > 0)
				{
					$params = join(' ', $params);
				}
				else
				{
					$params = '';
				}

				if ($options['crop'] != 'false')
				{
					$obj = "\$obj['cropped']";
					$name_ext = '.crop';
				}
				else
				{
					$obj = "\$obj";
					$name_ext = '';
				}

				if ($options['size']) {
					$size = 'data-retain-aspect="' . $this->attr_parse($options['size'], true) . '" ';
				} else {
					$size = '';
				}

				$cache_path_prefix = $options['relative'] ? 'relative_prefix' : 'prefix';

				$real_params = str_replace('alt=', 'data-alt=', $params);

				return <<<DOC
<?php

	\$__presets = array();
	if (isset({$token}['presets']))
	{
		\$__item = $token;
	}
	else if (isset({$token}['content']) && !empty({$token}['content']) && isset({$token}['content'][0]['presets']))
	{
		\$__item = {$token}['content'][0];
	}
	else if (isset({$token}['covers']))
	{
		\$__item = {$token}['covers'][0];
	}

	if (\$__item)
	{
		if (!isset(\$__item['cache_path']))
		{
?>
<img $real_params data-src="<?php echo \$__item['presets']['tiny']['url']; ?>" style="max-width:100%" />
<?php
		}
		else
		{
			foreach(\$__item['presets'] as \$name => \$obj)
			{

				\$__presets[] = "\$name{$name_ext},{{$obj}['width']},{{$obj}['height']}";
			}

			\$__presets = join(' ', \$__presets);
?>
<noscript>
	<img width="100%" $params src="<?php echo \$__item['presets']['large']['url']; ?>" />
</noscript>
<img $real_params data-visibility="<?php echo \$__item['visibility']['raw']; ?>" {$size}data-respond-to="{$options['respond_to']}" data-presets="<?php echo \$__presets; ?>" data-base="<?php echo \$__item['cache_path']["{$cache_path_prefix}"]; ?>" data-extension="<?php echo \$__item['cache_path']['extension']; ?>"/>

<?php } } ?>
DOC;
			}
			else
			{

				if ($options['assign_to_variable'])
				{
					$pre = '$value' . Koken::$tokens[0] . "['" . $options['assign_to_variable'] . "'] =";
				}
				else
				{
					$pre = 'echo';
				}

				$cparams = join(',', $cparams);

				$t = Koken::$tokens[0];

				if ($options['preset'] && !$custom_data)
				{
					$preset = "['presets']['{$options['preset']}']";
				}
				else
				{
					$preset = '';
				}

				return <<<DOC
<?php

	\$__params = array($cparams);

	\$__item = isset({$token}['presets']) ? $token : ( isset({$token}['covers']) ? {$token}['covers'][0] : false );

	\$__params['data-visibility'] = \$__item['visibility']['raw'];

	$pre Koken::output_img(\$__item{$preset}, array(
		'width' => {$options['width']},
		'height' => {$options['height']},
		'crop' => {$options['crop']}
	), \$__params);	?>
DOC;
			}

		}

	}