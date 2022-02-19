<?php

	class TagHasCategory extends Tag {

		protected $allows_close = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[0];
			$cat = $this->attr_parse($this->parameters['title']);

			return <<<OUT
<?php

	if (!isset({$token}['categories']) && isset({$token}['album']))
	{
		\$__base = {$token}['album'];
	}
	else
	{
		\$__base = $token;
	}

	\$__has_cat = false;

	if (isset(\$__base['categories']) && \$__base['categories']['count'] > 0)
	{
		\$__categories = Koken::api(\$__base['categories']['url']);
		\$__cats = array();
		foreach(\$__categories['categories'] as \$__c)
		{
			\$__cats[] = \$__c['title'];
		}

		\$__search_cats = explode(',', "$cat");
		\$__has_cat = array_intersect(\$__search_cats, \$__cats) === \$__search_cats;
	}

	if (\$__has_cat):
?>
OUT;
		}
	}