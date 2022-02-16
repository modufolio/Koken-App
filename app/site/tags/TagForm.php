<?php

	class TagForm extends Tag {

		protected $allows_close = true;

		function generate()
		{
			$params = $this->params_to_array_str();
			return <<<DOC
<?php
	echo Koken::form(array($params));
?>
DOC;
		}

		function close()
		{
			return '</form>';
		}
	}