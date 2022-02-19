<?php

	class TagNavigation extends Tag {

		function generate()
		{
			if (isset($this->parameters['group']))
			{
				$group = $this->parameters['group'];
			}
			else
			{
				$group = '';
			}

			if (isset($this->parameters['list']))
			{
				$list = $this->parameters['list'] == 'true';
			}
			else
			{
				$list = true;
			}

			if ($list)
			{
				if (isset($this->parameters['nested']))
				{
					$nested = $this->attr_parse($this->parameters['nested']);
				}
				else
				{
					$nested = 'false';
				}
			}
			else
			{
				$nested = 'false';
			}

			if (!isset($this->parameters['class']))
			{
				$this->parameters['class'] = '';
			}

			if (isset($this->parameters['fallbacktext']))
			{
				$fallback = '<span class=\"k-note\">' . $this->parameters['fallbacktext'] . '</span>';
			}
			else
			{
				$fallback = '';
			}
			$list = $list ? 'true' : 'false';

			return <<<OUT
<?php
	\$__nested = "$nested";
	\$__nested = \$__nested === '1' || \$__nested === 'true';
	\$__group = '$group';
	\$__nav = Koken::\$site['navigation'];

	if (strlen(\$__group))
	{
		\$__nav = \$__nav['groups'][\$__group];
	}

	if (\$__nested)
	{
		\$__nav = \$__nav['items_nested'];
	}
	else
	{
		\$__nav = \$__nav['items'];
	}

	echo empty(\$__nav) ? (Koken::\$draft ? "$fallback" : '') : Koken::render_nav(\$__nav, $list, true, '{$this->parameters['class']}');
?>
OUT;

		}

	}