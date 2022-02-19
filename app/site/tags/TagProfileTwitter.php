<?php

	class TagProfileTwitter extends Tag {

		protected $allows_close = true;

		function generate()
		{

			return <<<OUT
<?php

	if (!empty(Koken::\$profile['twitter'])):

?>
OUT;
		}
	}