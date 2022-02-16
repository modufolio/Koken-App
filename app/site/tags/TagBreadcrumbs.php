<?php

	class TagBreadcrumbs extends Tag {

		function generate()
		{
			$params = array();
			foreach($this->parameters as $key => $val)
			{
				$params[] = "'$key' => \"" . $this->attr_parse($val) . '"';
			}

			$params = join(',', $params);
			return "<?php echo Koken::breadcrumbs(array($params)); ?>";
		}
	}