<?php

	class TagNeighbors extends Tag {

		protected $allows_close = true;
		function generate()
		{

			$token = '$value' . Koken::$tokens[0];
			$ref = '$__neighbors';

			if (isset($this->parameters['count']))
			{
				$limit = $this->attr_parse($this->parameters['count']);
				Koken::$max_neighbors[] = $limit;
			}
			else
			{
				$limit = 2;
			}

			return <<<OUT
<?php
	if (isset({$token}['context']) || (isset({$token}['album']['context']))):
		$ref = array();
		$ref = isset({$token}['album']) ? {$token}['album']['context'] : {$token}['context'];
		\$__limit = \$__nlimit = \$__plimit = ( (int) "$limit" )/2;


		if (count({$ref}['previous']) < \$__limit)
		{
			\$__nlimit += \$__limit - count({$ref}['previous']);
		}
		else if ((count({$ref}['next']) < \$__limit))
		{
			\$__plimit += \$__limit - count({$ref}['next']);
		}

		if (count({$ref}['previous']) > \$__plimit)
		{
			{$ref}['previous'] = array_slice({$ref}['previous'], max(0, count({$ref}['previous']) - \$__plimit));
		}

		if (count({$ref}['next']) > \$__nlimit)
		{
			{$ref}['next'] = array_slice({$ref}['next'], 0, \$__nlimit);
		}
?>
OUT;
		}

		function close()
		{
			return <<<OUT
<?php
	unset(\$__neighbors);
	endif;
?>
OUT;
		}
	}