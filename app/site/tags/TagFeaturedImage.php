<?php

	class TagFeaturedImage extends Tag {

		protected $allows_close = true;
		public $tokenize = true;
		public $untokenize_on_else = true;

		function generate()
		{
			$essay = '$value' . Koken::$tokens[1];
			$token = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if (isset($essay) && isset({$essay}['featured_image']) && {$essay}['featured_image']):
		$token = {$essay}['featured_image'];
		{$token}['__koken_url'] = {$essay}['__koken_url'];
		{$token}['url'] = {$essay}['url'];
		{$token}['content'] =& $token;
?>
OUT;
		}
	}