<?php

	class TagEventContent extends Tag {

		protected $allows_close = true;

		function generate()
		{
			$token = '$value' . Koken::$tokens[0];

			return <<<DOC
<?php

	if (isset({$token}['filename'])):
		{$token}['content'] = Koken::\$current_token['content'] =& $token;
?>
DOC;
		}

	}