<?php

	class TagGeolocation extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if ({$token}['geolocation']):

		$ref = {$token}['geolocation'];
		{$ref}['geolocation'] = $ref;
?>
OUT;
		}
	}