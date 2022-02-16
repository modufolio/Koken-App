<?php

	class TagRating extends Tag {

		protected $allows_close	= true;

		function generate()
		{
			return <<<DOC
<?php
	if (Shutter::hook_exists('rating')):
?>
DOC;
		}

	}