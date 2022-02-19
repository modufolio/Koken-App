<?php

	class TagDiscussionCount extends Tag {

		protected $allows_close	= true;

		function generate()
		{
			return <<<DOC
<?php
	if (Shutter::hook_exists('discussion_count')):
?>
DOC;
		}

	}