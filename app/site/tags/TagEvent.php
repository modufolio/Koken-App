<?php

	class TagEvent extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{
			$token = '$value' . Koken::$tokens[1];
			$ref = Koken::$tokens[0];
			$merged = '$merged' . Koken::$tokens[0];
			$limit = $limit_str = '$limit' . Koken::$tokens[0];
			$url = '$url' . Koken::$tokens[0];

			if (isset($this->parameters['limit']))
			{
				$limit_str .= ' = ' . $this->attr_parse($this->parameters['limit']) . ';';
			}
			else
			{
				$limit_str .= ' = false;';
			}

			return <<<OUT
<?php
	$limit_str;

	$url = {$token}['items'];

	if (is_string($url))
	{
		if (is_numeric($limit))
		{
			$url .= '/limit:' . $limit;
		}

		$merged = Koken::api($url);
	}
	else
	{
		$merged = $token;
	}

	foreach({$merged}['items'] as \$key$ref => \$value$ref):
?>
OUT;
		}

		function close()
		{
			return <<<DOC
<?php endforeach; ?>
DOC;
		}

	}