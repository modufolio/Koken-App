<?php

	class TagLoop extends Tag {

		public $tokenize = true;

		function generate()
		{
			$token = Koken::$tokens[0];
			if (isset($this->parameters['data']))
			{
				$looper = $this->field_to_keys('data', 'value', 1);
			}
			else
			{
				$looper = '$value' . Koken::$tokens[1] . "['__loop__']";
			}

			$separate = '';
			if (isset($this->parameters['separator']))
			{
				$separate = <<<DOC
	if (\$i$token > 0):
		echo '{$this->parameters['separator']}';
	endif;
DOC;
			}

			$ref = "\$value{$token}['__koken__']";
			if ($looper)
			{
				if (Koken::$main_load_token === Koken::$tokens[1] && !isset($this->parameters['data']))
				{
					$current_token = Koken::$tokens[0];
					$last_token = Koken::$tokens[1];
					$infinite = <<<DOC
	if (isset(Koken::\$location['__infinite_token']) && Koken::\$location['__infinite_token'] === '$last_token')
	{
		\$infinite{$current_token} = true;
		echo '<span class="k-infinite-load">';
	}
DOC;
				$this->parameters['limit'] = 'false';
				}
				else
				{
					$infinite = '';
					if (isset($this->parameters['limit']))
					{
						$this->parameters['limit'] = '"' . $this->attr_parse($this->parameters['limit']) . '"';
					}
					else
					{
						$this->parameters['limit'] = 'false';
					}
				}
				return <<<DOC
<?php
	$infinite
	\$i$token = 0;
	\$__limit = {$this->parameters['limit']};
	if (is_numeric(\$__limit))
	{
		$looper = array_slice($looper, 0, \$__limit);
	}
	foreach($looper as \$key$token => \$value$token):
		$separate
		\$i$token++;
		\$value{$token}['__loop_index'] = \$value{$token}['index'] = \$key$token;
		if (isset($ref) && !isset(\$value{$token}[$ref]) && is_array(\$value$token)):
			\$__clean_ref = $ref === 'max_download' ? 'max_download' : preg_replace('/_.*$/', '', $ref);
			\$value{$token}[\$__clean_ref] =& \$value$token;
			if (\$__clean_ref === 'event')
			{
				\$value{$token}['date'] =& \$value$token;
			}
		endif;

		if (is_array(\$value$token) && isset(\$value{$token}['type']) && in_array(\$value{$token}['__koken__'], array('tag', 'category'))):
			\$value{$token}['archive'] =& \$value$token;
		endif;

?>
DOC;
			}
		}

		function close()
		{
			$current_token = Koken::$tokens[0];
			return <<<DOC
<?php

	endforeach;
	if (isset(\$infinite{$current_token}))
	{
		echo '</span>';
	}

?>
DOC;
		}

	}