<?php

	class Tag {

		public $tokenize 		= false;
		public $untokenize_on_else = false;
		protected $parameters	= array();
		protected $allows_close	= false;
		protected $attr_parse_level = 0;

		function __construct($parameters = array())
		{
			$this->parameters = $parameters;
		}

		public function attr_replace($matches)
		{
			return '" . ' . $this->field_to_keys($matches[1]) . '. "';
		}

		public function out_cb($matches)
		{
			return '" . Koken::out(\'' . trim(str_replace("'", "\\'", $matches[1])) . '\') . "';
		}

		public function params_to_array_str()
		{
			$params = array();
			foreach($this->parameters as $key => $val)
			{
				if ($key === 'data')
				{
					$params[] = "'data' => " . $this->field_to_keys($val);
				}
				else
				{
					$params[] = "'$key' => \"" . $this->attr_parse($val) . '"';
				}
			}

			return join(',', $params);
		}
		public function attr_parse($val, $wrap = false)
		{
			$pattern = '/\{\{\s*([^\}]+)\s*\}\}/';
			if (preg_match($pattern, $val))
			{
				$o = preg_replace_callback($pattern, array($this, 'out_cb'), $val);
				if ($wrap)
				{
					return '<?php echo "' . $o. '" ?>';
				}
				else
				{
					return $o;
				}
			}
			else
			{
				$o =  preg_replace_callback('/\{([a-z_.0-9]+)\}/', array($this, 'attr_replace'), $val);
				if ($wrap)
				{
					return '<?php echo "' . $o. '" ?>';
				}
				else
				{
					return $o;
				}
			}
		}

		public function field_to_keys($param, $variable = 'value', $token_index = false)
		{

			if (!$token_index)
			{
				$token_index = $this->attr_parse_level;
			}

			$bits = explode('|', $param);

			$options = array();

			foreach($bits as $param)
			{
				$param = trim($param);
				$prefix = $postfix = '';

				if (isset($this->parameters[$param]))
				{
					$p = $this->parameters[$param];
				}
				else
				{
					$p = $param;
				}

				$p = trim($p, '{} ');

				preg_match('/^(site|location|profile|source|settings|routed_variables|page_variables|pjax|labels|messages)/', $p, $global_matches);

				if (count(Koken::$tokens) === 0 && count($global_matches) === 0 && !in_array($p, Koken::$template_variable_keys))
				{
					return "''";
				}

				if (count($global_matches))
				{
					$f = explode('.', $p);
					array_shift($f);
					$prefix = 'Koken::$' . $global_matches[1];

					if ($global_matches[1] === 'settings')
					{
						$s = array_shift($f);
						if (isset(Koken::$settings['__scoped_' . str_replace('.', '-', Koken::$location['template']) . "_{$s}"]))
						{
							$prefix .= "['__scoped_" . str_replace('.', '-', Koken::$location['template']) . "_{$s}']";
						}
						else
						{
							$prefix .= "['$s']";
						}
					}
				}
				else if (in_array($p, Koken::$template_variable_keys))
				{
					return "Koken::\$template_variables['$p']";
				}
				else if ($p === 'value')
				{
					return "\$value" . Koken::$tokens[$token_index];
				}
				else if ($p === 'count')
				{
					return 'count($value' . Koken::$tokens[$token_index] . "['__loop__'])";
				}
				else
				{
					$pre = '';
					while (strpos($p, '_parent.') !== false)
					{
						$p = substr($p, strlen('_parent.'));
						$token_index++;
					}
					$p = str_replace('.first', '[0]', $p);
					if (strpos($p, '.last') !== false)
					{
						$partial = substr($p, 0, strpos($p, '.last'));
						$p = str_replace('.last', '[count(' . $this->field_to_keys($partial, $variable, $token_index) . ') - 1]', $p);
					}
					if (strpos($p, '.length') !== false)
					{
						$p = substr($p, 0, strpos($p, '.length'));
						$postfix = ')';
						$pre = 'count(';
					}
					$f = explode('.', $p);
					$prefix = $pre . "\$$variable" . Koken::$tokens[$token_index];
				}

				$final = array();
				foreach($f as $v)
				{
					$bits = explode('[', $v);
					$str = "['" . array_shift($bits) . "']";
					if (count($bits))
					{
						$str .= '[' . join('[', $bits);
					}

					$final[] = $str;
				}
				$options[] = "{$prefix}" . join("", $final) . $postfix;
			}
			if (count($options) === 1)
			{
				return $options[0];
			}
			else
			{
				return "empty($options[0]) ? $options[1] : $options[0]";
			}
		}

		public function do_else()
		{
			return '<?php else: ?>';
		}

		function close()
		{
			if ($this->allows_close)
			{
				return <<<DOC
<?php endif; ?>
DOC;
			}
		}
	}