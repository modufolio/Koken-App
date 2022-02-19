<?php

	class TagDates extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			if (count(Koken::$tokens) > 1)
			{
				$token = '$value' . Koken::$tokens[1];
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
			}

			$ref = '$value' . Koken::$tokens[0];

			$params = $this->params_to_array_str();

			return <<<OUT
<?php

	\$__base = Koken::dates( array($params) );

	if (isset(\$__base['events']) && !empty(\$__base['events'])):

		$ref = array();
		{$ref}['__loop__'] =& \$__base['events'];
?>
OUT;
		}
	}