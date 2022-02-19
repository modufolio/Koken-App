<?php

	class TagArrayAccess extends Tag {

		public $tokenize = true;

		function generate()
		{
			$token = '$value' . Koken::$tokens[0];
			$parent = '$value' . Koken::$tokens[1];
			$index = $this->parameters['index'];

			return <<<DOC
<?php
	$token = {$parent}['__loop__'][$index];
	if (isset({$token}['__koken__']))
	{
		{$token}[{$token}['__koken__']] =& $token;
	}
?>
DOC;
		}

	}