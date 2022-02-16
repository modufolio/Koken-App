<?php

	class TagFiltered extends Tag {

		public $tokenize = true;
		public $allows_close = true;
		public $untokenize_on_else = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[0];
			$parent = '$value' . Koken::$tokens[1];

			return <<<DOC
<?php

	if (count(Koken::\$location['parameters']['__overrides'])):
		{$token} = $parent;
		{$token}['filter']['count'] = {$parent}['counts']['total'];

		\$__arr = array();
		foreach(Koken::\$location['parameters']['__overrides_display'] as \$__override)
		{
			\$__f = $token;
			\$__f['filter'] = array_merge( {$token}['filter'], \$__override );
			\$__arr[] = \$__f;
		}
		{$token}['__loop__'] = \$__arr;
?>
DOC;
		}

	}