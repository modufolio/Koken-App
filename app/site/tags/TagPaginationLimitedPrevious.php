<?php

	class TagPaginationLimitedPrevious extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$pag = '$value' . Koken::$tokens[1];
			$token = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if ({$pag}['limited']['previous']):
		$token =& {$pag}['limited']['previous'];
?>
OUT;
		}
	}