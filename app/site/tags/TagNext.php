<?php

	class TagNext extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];
			$neighbors = '$__neighbors';

			if (isset($this->parameters['count']))
			{
				$limit = $this->parameters['count'];
			}
			else
			{
				$limit = 1;
			}

			Koken::$max_neighbors[] = $limit*2;

			if (isset($this->parameters['_prev']))
			{
				$key = 'previous';
				$opposite = 'next';
				$slice = '$__next = array_slice($__next, max(0, count($__next) - ' . $limit . '));';
			}
			else
			{
				$key = 'next';
				$opposite = 'previous';
				$slice = '$__next = array_slice($__next, 0, ' . $limit . ');';
			}

			return <<<OUT
<?php
	\$__limit = $limit;
	\$__force_loop = false;

	if (isset($neighbors) && isset({$neighbors}['$key']))
	{
		\$__next = {$neighbors}['$key'];
		\$__force_loop = true;
	}
	else if (isset({$token}['context']) && isset({$token}['context']['$key']))
	{
		\$__next = {$token}['context']['$key'];
		$slice
	}
	else if (isset({$token}['album']['context']) && isset({$token}['album']['context']['$key']))
	{
		\$__next = {$token}['album']['context']['$key'];
		$slice
	}
	else if (isset({$token}['event']['context']) && isset({$token}['event']['context']['$key']))
	{
		\$__next = {$token}['event']['context']['$key'];
		$slice
	}
	else
	{
		\$__next = array();
	}

	if (count(\$__next)):
		if (\$__limit > 1 || \$__force_loop)
		{
			$ref = array();
			if (isset({$token}['context']['album']))
			{
				{$ref}['album'] = {$token}['context']['album'];
			}
			else if (isset({$token}['album']))
			{
				{$ref}['album'] = {$token}['album'];
			}
			{$ref}['__loop__'] =& \$__next;
		}
		else
		{
			$ref = \$__next[0];
			if (isset({$ref}['__koken__']))
			{
				{$ref}[ {$ref}['__koken__'] ] =& $ref;
			}
		}

?>
OUT;
		}
	}