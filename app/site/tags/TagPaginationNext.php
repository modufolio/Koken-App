<?php

	class TagPaginationNext extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$pag = '$value' . Koken::$tokens[1];
			$token = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if ({$pag}['page'] < {$pag}['pages']):
		$token =& {$pag}['next_page'];
?>
OUT;
		}
	}