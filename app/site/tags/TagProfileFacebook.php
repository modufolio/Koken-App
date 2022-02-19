<?php

	class TagProfileFacebook extends Tag {

		protected $allows_close = true;

		function generate()
		{

			return <<<OUT
<?php

	if (!empty(Koken::\$profile['facebook'])):

?>
OUT;
		}
	}