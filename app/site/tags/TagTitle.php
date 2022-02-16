<?php

	class TagTitle extends Tag {

		function generate()
		{

			if (isset($this->parameters['separator']))
			{
				$sep = $this->parameters['separator'];
			}
			else
			{
				$sep = '-';
			}

			$sep = " $sep ";

			if (Koken::$source && Koken::$source['type'] && !Koken::$custom_page_title)
			{
				$list = Koken::$source['type'] === 'timeline' || substr( strrev(Koken::$source['type']), 0, 1 ) === 's';

				if ($list)
				{
					$obj = Koken::$source['type'] === 'categories' ? 'category' : rtrim(Koken::$source['type'], 's');
					$pre = '{{ labels.' . $obj . '.plural case="title" }}';
				}
			}

			if (isset($pre))
			{
				$pre = "$pre{$sep}";
			}
			else
			{
				$pre = '';
			}
			return <<<DOC
<?php

	if (!Koken::\$the_title_separator)
	{
		Koken::\$the_title_separator = '$sep';
	}
?>
<koken_title>
	<?php if (Koken::\$location['here'] !== '/'): ?>
		<?php
		if (Koken::\$custom_page_title)
		{
			echo Koken::\$custom_page_title . "$sep";
		}
		?>
		$pre
	<?php endif; ?>

	<?php if (isset(Koken::\$routed_variables['code'])): ?>
		<?php echo Koken::\$routed_variables['code']; ?> -
	<?php endif; ?>

	<?php echo Koken::\$site['page_title']; ?>
</koken_title>
DOC;
		}

	}