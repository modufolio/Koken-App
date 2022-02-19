<?php

	class TagTags extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{
			if (count(Koken::$tokens) > 1)
			{
				$token = '$value' . Koken::$tokens[1];
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
			}

			$ref = '$value' . Koken::$tokens[0];

			$params = $this->params_to_array_str();

			return <<<OUT
<?php

	\$__params = array($params);
	\$__from_token = true;

	if (!isset({$token}['tags']) && isset({$token}['album']))
	{
		\$__base = {$token}['album'];
	}
	else if (isset({$token}['tags']) && !isset({$token}['counts']) || isset({$token}['album_type']))
	{
		\$__base = $token;
	}
	else
	{
		\$__from_token = false;
		\$__base = Koken::tags( \$__params );
	}

	if (\$__from_token)
	{
		if (isset(\$__params['order_by']) && \$__params['order_by'] === 'count')
		{
			usort(\$__base['tags'], array('Koken', 'sort_tags_by_count' . (isset(\$__params['order_direction']) && strtolower(\$__params['order_direction']) === 'asc' ? '_asc' : '')));
		}
		else if (isset(\$__params['order_direction']) && strtolower(\$__params['order_direction']) === 'desc')
		{
			\$__base['tags'] = array_reverse(\$__base['tags']);
		}
	}

	if (isset(\$__base['tags']) && !empty(\$__base['tags'])):

		$ref = array();

		{$ref}['__loop__'] = \$__base['tags'];

		if (!is_array(\$__base['tags'][0]))
		{
			foreach({$ref}['__loop__'] as &\$t)
			{
				\$t = array(
					'title' => \$t,
					'__koken__' => 'tag_' . \$__base['__koken__'] . 's'
				);
			}
		}
?>
OUT;
		}
	}