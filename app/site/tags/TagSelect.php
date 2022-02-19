<?php

	class TagSelect extends Tag {

		function generate()
		{

			$token = '$value' . Koken::$tokens[0];

			if (isset($this->parameters['label']))
			{
				$label = '<option value="__label__">' . $this->parameters['label'] . '</option>';
			}
			else
			{
				$label = '';
			}

			return <<<OUT
<select class="k-select">
	$label
	<?php foreach({$token}['__loop__'] as \$__item): ?>
	<option value="<?php echo \$__item['__koken_url']; ?>"<?php echo \$__item['__koken_url'] === Koken::\$location['here'] ? ' selected' : ''; ?>><?php
		if (isset(\$__item['title']))
		{
			echo \$__item['title'];
		}
		else if (isset(\$__item['month']))
		{
			echo Koken::title_from_archive(\$__item);
		}
		else if (\$__item['year'])
		{
			echo \$__item['year'];
		}
	?></option>
	<?php endforeach; ?>
</select>
OUT;
		}
	}