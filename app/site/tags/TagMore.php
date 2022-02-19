<?php

	class TagMore extends Tag {

		protected $allows_close = true;

		function generate()
		{
			$token = '$value' . Koken::$tokens[0];

			return <<<DOC
<?php

	if (isset({$token}['read_more']) && {$token}['read_more']):
		Koken::\$link_tail = '#more';
?>
DOC;
		}

		function close()
		{
			return <<<DOC
<?php
	Koken::\$link_tail = '';
	endif;
?>
DOC;
		}

	}