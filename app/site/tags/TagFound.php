<?php

	class TagFound extends Tag {

		protected $allows_close	= true;

		function generate()
		{
			$token = Koken::$tokens[0];
			if (isset($this->parameters['data']))
			{
				$main = $this->field_to_keys('data');
			}
			else
			{
				$main = "\$value{$token}";
			}
			return <<<DOC
<?php
	if (!empty($main)):
?>
DOC;
		}

	}