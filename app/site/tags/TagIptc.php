<?php

	class TagIptc extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php
	if (isset({$token}['iptc']) && !empty({$token}['iptc'])):

		$ref = array( 'iptc' => array() );
		{$ref}['__loop__'] = {$token}['iptc'];

		foreach({$token}['iptc'] as \$__arr)
		{
			{$ref}['iptc'][\$__arr['key']] = \$__arr['value'];
		}

		foreach({$ref}['__loop__'] as &\$__arr)
		{
			\$__arr['__koken__'] = 'iptc';
		}
?>
OUT;
		}
	}