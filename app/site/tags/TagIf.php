<?php

	class TagIf extends Tag {

		protected $allows_close = true;
		protected $js_condition;
		protected $can_live_update;
		protected $data_attrs;
		protected $condition;

		private $condition_template;
		private $js_condition_template;

		private function split_cb($matches)
		{
			$token = $this->field_to_keys($matches[1]);
			return str_replace('__TOKEN__', $token, $this->condition_template);
		}

		private function split_cb_js($matches)
		{
			$token = $this->field_to_keys($matches[1]);
			return str_replace('__TOKEN__', $token, $this->js_condition_template);
		}

		private function parse_dynamic($matches)
		{
			return Koken::out($matches[1]);
		}

		private function clean_parameter($parameter)
		{
			$parameter = trim(preg_replace('/(^\{\{)|(\}\}$)/', '', $parameter));
			if (strpos($parameter, '{{') !== false)
			{
				$parameter = preg_replace_callback('/\{\{\s*([^\}]+)\s*\}\}/', array($this, 'parse_dynamic'), $parameter);
			}
			return $parameter;
		}

		private function tokenize_condition($parameter)
		{
			return preg_replace_callback('/([a-z_\.]+)/', array($this, 'split_cb'), $this->clean_parameter($parameter));
		}

		private function tokenize_js_condition($parameter)
		{
			return preg_replace_callback('/([a-z_\.]+)/', array($this, 'split_cb_js'), $this->clean_parameter($parameter));
		}

		function generate()
		{
			switch(true)
			{

				case isset($this->parameters['empty']):
					$this->condition_template = "empty(__TOKEN__)";
					$this->js_condition_template = "__TOKEN__.length";
					$condition = $this->tokenize_condition($this->parameters['empty']);
					$js_condition = $this->tokenize_js_condition($this->parameters['empty']);
					break;

				case isset($this->parameters['equals']):
					$value = preg_split('/\s?\|\|\s?/', str_replace("'", '', $this->parameters['equals']));
					$value_joined = join("', '", $value);
					$value = "array('" . $value_joined . "')";
					$js_value = "['" . $value_joined . "']";
					$this->condition_template = "in_array(__TOKEN__, $value)";
					$this->js_condition_template = "$.inArray(__TOKEN__, $js_value) !== -1";
					$condition = $this->tokenize_condition($this->parameters['data']);
					$js_condition = $this->tokenize_js_condition($this->parameters['data']);
					break;

				case isset($this->parameters['true']):
					$this->condition_template = "(isset(__TOKEN__) && (bool) __TOKEN__)";
					$this->js_condition_template = "__TOKEN__";
					$condition = $this->tokenize_condition($this->parameters['true']);
					$js_condition = $this->tokenize_js_condition($this->parameters['true']);
					break;

				case isset($this->parameters['exists']):
					$this->condition_template = "isset(__TOKEN__)";
					$this->js_condition_template = "__TOKEN__";
					$condition = $this->tokenize_condition($this->parameters['exists']);
					$js_condition = $this->tokenize_js_condition($this->parameters['exists']);
					break;

				case isset($this->parameters['false']):
					$this->condition_template = "(!isset(__TOKEN__) || !(bool) __TOKEN__)";
					$this->js_condition_template = "!__TOKEN__";
					$condition = $this->tokenize_condition($this->parameters['false']);
					$js_condition = $this->tokenize_js_condition($this->parameters['false']);
					break;

				case isset($this->parameters['condition']):
					$condition = $js_condition = preg_replace_callback('/\{{1,2}\s*([a-z_.]+)\s*\}{1,2}/', array($this, 'condition_parse'), $this->parameters['condition']);
					break;

			}

			if (strpos($condition, 'settings') !== false && (!isset($this->parameters['live']) || $this->parameters['live'] != 'false'))
			{
				$this->can_live_update = true;
				$js_settings = preg_match_all('/Koken::\$settings\[\'([^\']+)\'\]/', $js_condition, $matches);
				$this->data_attrs = ' data-' . join('="true" data-', $matches[1]) . '="true"';
				$this->js_condition = str_replace("'", "\'", preg_replace('/Koken::\$settings\[\'([^\']+)\'\]/', '__setting.$1', $js_condition));
			}
			else
			{
				$this->data_attrs = '';
				$this->can_live_update = false;
				$this->js_condition = '';
			}
			if (isset($this->parameters['_not']))
			{
				if ($this->js_condition && !empty($this->js_condition))
				{
					$this->js_condition = "!({$this->js_condition})";
				}
				$condition = "!($condition)";
			}

			if ($condition)
			{
				$this->condition = $condition;
				// TODO: Remove @ from here for production
				if (Koken::$draft && !Koken::$preview && $this->can_live_update)
				{
					return <<<DOC
<?php
	echo '<i class="k-control-structure"$this->data_attrs data-control-condition="$this->js_condition" style="display:' . (  @$condition ? 'inline': 'none' ) . '">';
?>
DOC;
				}
				else
				{
					return <<<DOC
<?php

	if (@$condition):

?>
DOC;
				}
			}

		}

		function do_else()
		{
			if (Koken::$draft &&!Koken::$preview && $this->can_live_update)
			{
				return <<<DOC
<?php
		echo '</i><i class="k-control-structure"$this->data_attrs data-control-condition="!($this->js_condition)" style="display:' . ( $this->condition ? 'none': 'inline' ) . '">';
?>
DOC;
			}
			else
			{
				return '<?php else: ?>';
			}
		}

		function close()
		{
			if (Koken::$draft &&!Koken::$preview && $this->can_live_update)
			{
				return '</i>';
			}
			else
			{
				return '<?php endif; ?>';
			}

		}

		private function condition_parse($matches)
		{
			return $this->field_to_keys($matches[1]);
		}

	}