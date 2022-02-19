<?php

	class TagVariable extends Tag {

		function generate()
		{

			Koken::$template_variable_keys[] = $this->parameters['name'];
			$value = '"' . $this->attr_parse($this->parameters['value']) . '"';

			return <<<DOC
<?php Koken::\$template_variables['{$this->parameters['name']}'] = $value; ?>
DOC;
		}

	}