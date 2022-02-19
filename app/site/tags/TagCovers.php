<?php

	class TagCovers extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			if (isset($this->parameters['limit']))
			{
				$this->parameters['limit'] = '"' . $this->attr_parse($this->parameters['limit']) . '"';
			}
			else
			{
				$this->parameters['limit'] = 'false';
			}

			if (isset($this->parameters['minimum']))
			{
				$this->parameters['minimum'] = '"' . $this->attr_parse($this->parameters['minimum']) . '"';
			}
			else
			{
				$this->parameters['minimum'] = 'false';
			}
			return <<<OUT
<?php

	\$__covers = Koken::covers({$token}, {$this->parameters['minimum']}, {$this->parameters['limit']});
	if (count(\$__covers) > 0):

		foreach(\$__covers as &\$__cover)
		{
			\$__cover['album'] = {$token}['album'];
		}

		$ref = $token;
		{$ref}['covers'] =& \$__covers;
		{$ref}['__loop__'] =& \$__covers;
?>
OUT;
		}
	}