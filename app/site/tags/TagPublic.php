<?php

class TagPublic extends Tag {

	protected $allows_close = true;

	function generate()
	{
		return <<<DOC
<?php if (Koken::\$public): ?>
DOC;
	}
}