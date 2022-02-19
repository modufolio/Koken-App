<?php

	class TagParent extends Tag {

		protected $allows_close = true;
		public $tokenize = true;
		public $untokenize_on_else = true;

		function generate()
		{
			$parent = count(Koken::$tokens) > 2 ? '$value' . Koken::$tokens[2] : '$dummy';
			$token = '$value' . Koken::$tokens[1];
			$id = '$id' . Koken::$tokens[0];
			$main = '$value' . Koken::$tokens[0];
			$curl = '$curl' . Koken::$tokens[0];

			$order_by = '';
			$order_direction = '';

			if (isset($this->parameters['order_by']))
			{
				$order_by = '/order_by:' . $this->attr_parse($this->parameters['order_by']);

				if (isset($this->parameters['order_direction']))
				{
					$order_direction = '/order_direction:' . $this->attr_parse($this->parameters['order_direction']);
				}
			}

			return <<<OUT
<?php

	if (isset($parent) && isset({$parent}['album']))
	{
		$main =& $parent;
	}
	else
	{
		if (isset({$token}['album']) && {$token}['album']['parent'])
		{
			$id =& {$token}['album']['parent'];
		}
		else if (isset({$token}['content']) && isset({$token}['content']['context']) && isset({$token}['content']['context']['album']))
		{
			$id =& {$token}['content']['context']['album'];
		}

		if (isset($id))
		{
			$main = Koken::api('/albums/' . ( isset({$id}['internal_id']) ? {$id}['internal_id'] : {$id}['id'] ) . "/content{$order_by}{$order_direction}");
		}
		else
		{
			$main = false;
		}
	}


	if (isset({$main}['album'])):

		if (isset({$main}['content']))
		{
			{$main}['__loop__'] = {$main}['content'];
		}
		else if (isset({$main}['albums']))
		{
			{$main}['__loop__'] = {$main}['albums'];
		}

?>
OUT;
		}
	}