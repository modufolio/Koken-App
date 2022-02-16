<?php

	class TagBackground extends Tag {

		public $tag = 'div';

		function generate()
		{

			$defaults = array(
				'tag' => 'div',
				'data' => false,
				'position' => 'focal',
				'lazy' => false,
				'fade' => true,
				'relative' => true,
			);

			$options = array_merge($defaults, $this->parameters);

			$options['lazy'] = $options['lazy'] === 'true';
			$options['fade'] = $options['fade'] !== 'false';

			if (isset($this->parameters['data']))
			{
				$token = $this->field_to_keys($this->parameters['data']);
				unset($this->parameters['data']);
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
			}

			if ($options['lazy'])
			{
				$klass = 'k-lazy-loading-background';
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

			$cache_path_prefix = $options['relative'] ? 'relative_prefix' : 'prefix';

			$this->tag = $options['tag'];

			unset($this->parameters['tag']);

			$params = array();

			foreach($this->parameters as $key => $val)
			{
				if ($key === 'position' || !isset($defaults[$key]))
				{
					if ($key === 'position')
					{
						$key = 'data-position';
					}
					$val = $this->attr_parse($val, true);
					$params[] = "$key=\"$val\"";
				}
			}

			$params = join(' ', $params);

			return <<<DOC
<?php
		\$__presets = array();
		if (isset({$token}['presets']))
		{
			\$__item = $token;
		}
		else if (isset({$token}['covers']))
		{
			\$__item = {$token}['covers'][0];
		}
		else if (isset({$token}['album']) && isset({$token}['album']['covers']))
		{
			\$__item = {$token}['album']['covers'][0];
		}
		else if (isset({$token}['featured_image']))
		{
			\$__item = {$token}['featured_image'];
		}
		else
		{
			\$__item = false;
		}

		if (\$__item)
		{
			foreach(\$__item['presets'] as \$name => \$obj)
			{

				\$__presets[] = "\$name,{\$obj['width']},{\$obj['height']}";
			}
		}

		\$__presets = join(' ', \$__presets);

		if (\$__item):
?>

<{$this->tag} $params data-aspect="<?php echo \$__item['aspect_ratio']; ?>" data-focal-point="<?php echo \$__item['focal_point']['x']; ?>,<?php echo \$__item['focal_point']['y']; ?>" data-bg-presets="<?php echo \$__presets; ?>" data-base="<?php echo \$__item['cache_path']["{$cache_path_prefix}"]; ?>" data-extension="<?php echo \$__item['cache_path']['extension']; ?>">
DOC;
		}

		function close()
		{
			return '</' . $this->tag . '><?php endif; ?>';
		}

	}