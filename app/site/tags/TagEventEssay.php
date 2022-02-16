<?php

	class TagEventEssay extends Tag {

		protected $allows_close = true;

		function generate()
		{
			$token = '$value' . Koken::$tokens[0];

			return <<<DOC
<?php

	if (isset({$token}['published'])):
		{$token}['essay'] = Koken::\$current_token['essay'] =& $token;
?>
DOC;
		}

	}