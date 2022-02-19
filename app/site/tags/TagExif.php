<?php

	class TagExif extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php
	if (isset({$token}['exif']) && !empty({$token}['exif'])):

		$ref = array( 'exif' => array() );
		{$ref}['__loop__'] = {$token}['exif'];

		foreach({$token}['exif'] as \$__arr)
		{
			{$ref}['exif'][\$__arr['key']] = array();
			if (isset(\$__arr['clean']))
			{
				{$ref}['exif'][\$__arr['key']]['clean'] = \$__arr['clean'];
			}
			if (isset(\$__arr['computed']))
			{
				{$ref}['exif'][\$__arr['key']]['computed'] = \$__arr['computed'];
			}
			{$ref}['exif'][\$__arr['key']]['raw'] = \$__arr['raw'];
		}

		foreach({$ref}['__loop__'] as &\$__arr)
		{
			\$__arr['__koken__'] = 'exif';
		}
?>
OUT;
		}
	}