<?php

	class TagSource extends Tag {

		protected $allows_close = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php
	if (isset({$token}['source'])):
?>
OUT;
		}
	}