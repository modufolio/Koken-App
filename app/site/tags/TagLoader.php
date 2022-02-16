<?php

	// This class responds to koken:content, koken:albums and koken:essays
	class TagLoader extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$obj = $this->parameters['_obj'];
			unset($this->parameters['_obj']);

			if (count(Koken::$tokens) > 1)
			{
				$token = '$value' . Koken::$tokens[1];
				$copy = '$copy' . Koken::$tokens[1];
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
				$copy = '$copy' . Koken::$tokens[0];
			}
			$ref = '$value' . Koken::$tokens[0];
			$tmp = '$tmp' . Koken::$tokens[0];
			$limit = '$limit' . Koken::$tokens[0];
			$archive = '$archive' . Koken::$tokens[0];

			if (isset($this->parameters['limit']))
			{
				$l = $this->attr_parse($this->parameters['limit']);
			}
			else
			{
				$l = 'false';
			}

			if (isset($this->parameters['include_current']))
			{
				$current = $this->attr_parse($this->parameters['include_current']);
			}
			else
			{
				$current = 'true';
			}

			$params = $this->params_to_array_str();

			return <<<OUT
<?php

	$archive = $limit = false;

	if (isset($token))
	{
		if (isset({$token}['album']))
		{
			$copy = {$token}['album'];
		}
		else
		{
			$copy = $token;
		}
	}
	else
	{
		$copy = array();
	}

	\$__params = array($params);

	if (isset(\$__params['limit_to']) || !isset({$copy}['$obj']) || isset({$copy}['$obj']['counts']))
	{
		$copy = Koken::$obj(\$__params);
	}
	else if (!isset(\$__params['limit_to']) && isset({$copy}['$obj']['url']) && is_string({$copy}['$obj']['url']) && strpos({$copy}['$obj']['url'], 'http') !== false)
	{
		if (isset({$copy}['$obj']['count']) && {$copy}['$obj']['count'] === 0)
		{
			{$copy}['$obj'] = array();
		}
		else if (isset({$copy}['filename']) || isset({$copy}['album_type']) || isset({$copy}['page_type']) || (isset({$copy}['counts']) && isset({$copy}['counts']['$obj']) && {$copy}['counts']['$obj'] > 0))
		{
			if ($current)
			{
				{$copy}['$obj']['url'] = preg_replace('~/context:[0-9]+~', '', {$copy}['$obj']['url']);
			}
			\$__url = {$copy}['$obj']['url'] . ( $l > 0 ? '/limit:' . $l : '' );
			if (isset(\$__params['order_direction']))
			{
				\$__url .= '/order_direction:' . \$__params['order_direction'];
			}
			if (isset(\$__params['order_by']))
			{
				\$__url .= '/order_by:' . \$__params['order_by'];
			}
			$copy = Koken::api(\$__url);
		}
		else
		{
			{$copy}['$obj'] = array();
		}
	}

	if (isset({$copy}['text']))
	{
		{$copy}['$obj'] = {$copy}['text'];
	}
	else if ('$obj' === 'topics' && isset({$copy}['albums']))
	{
		{$copy}['$obj'] = {$copy}['albums'];
	}

	if (!isset({$copy}['$obj']) && isset({$copy}['preview']['$obj']))
	{
		$archive = {$copy};
		$tmp = {$copy}['preview'];
		$limit = $l;
	}
	else
	{
		$tmp = $copy;
	}

	if (isset({$tmp}['$obj']) && !empty({$tmp}['$obj'])):

		if ($limit > 0)
		{
			{$tmp}['$obj'] = array_slice({$tmp}['$obj'], 0, $limit);
		}

		$ref = array();
		{$ref}['__loop__'] =& {$tmp}['$obj'];
		{$ref}['$obj'] =& {$tmp}['$obj'];

		if ($archive)
		{
			foreach($archive as \$__k => \$__v)
			{
				if (\$__k === 'preview') continue;
				{$ref}[\$__k] = \$__v;
			}
			{$ref}['__koken__'] .= '_{$obj}';
			{$ref}['link'] = Koken::form_link($ref, false, false);
		}
?>
OUT;
		}
	}