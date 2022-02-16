<?php

	class TagContentImage extends Tag {

		protected $allows_close = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if ({$token}['file_type'] === 'image'):

?>
OUT;
		}
	}