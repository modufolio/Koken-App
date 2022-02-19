<?php

	class TagShift extends Tag {

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

			$ref = "\$value{$token}['__koken__']";

			if (isset($this->parameters['_pop']))
			{
				$action = 'array_pop';
			}
			else
			{
				$action = 'array_shift';
			}

			if ($looper)
			{
				return <<<DOC
<?php

	\$value$token = $action($looper);
	if (isset($ref) && !isset(\$value{$token}[$ref])):
		\$value{$token}[$ref] =& \$value$token;
	endif;

?>
DOC;
			}
		}

	}