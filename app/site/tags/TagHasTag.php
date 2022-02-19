<?php

	class TagHasTag extends Tag {

		protected $allows_close = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[0];
			$tag = $this->attr_parse($this->parameters['title']);

			return <<<OUT
<?php

	if (!isset({$token}['tags']) && isset({$token}['album']))
	{
		\$__base = {$token}['album'];
	}
	else
	{
		\$__base = $token;
	}

	\$__tags = explode(',', '$tag');

	\$__base_tags = array();
	foreach(\$__base['tags'] as \$__tag)
	{
		\$__base_tags[] = \$__tag['title'];
	}

	if (!empty(\$__base_tags) && array_intersect(\$__tags, \$__base_tags) === \$__tags):

?>
OUT;
		}
	}