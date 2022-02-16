<?php

	class TagValue extends Tag {

		function generate()
		{
			$token = '$value' . Koken::$tokens[0];
			return <<<DOC
<?php echo $token; ?>
DOC;
		}

	}