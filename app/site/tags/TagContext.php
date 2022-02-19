<?php

	class TagContext extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if (isset({$token}['context']) && isset({$token}['context']['type'])):

		\$__terms = Koken::\$site['url_data'][ {$token}['context']['type'] ];
		$ref = array(
			'label' => array(
				'singular' => \$__terms['singular'],
				'plural' => \$__terms['plural']
			),
			'position' => {$token}['context']['position'],
			'total' => {$token}['context']['total'],
			'type' => {$token}['context']['type'],
			'url' => isset({$token}['context']['url']) ? {$token}['context']['url'] : false,
			'title' => isset({$token}['context']['title']) ? {$token}['context']['title'] : false,
			'__koken_url' => isset({$token}['context']['__koken_url']) ? {$token}['context']['__koken_url'] : false
		);

		{$ref}['context'] = $ref;
?>
OUT;
		}
	}