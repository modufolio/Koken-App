<?php

	class TagMeta extends Tag {

		function generate()
		{
			return '<?php echo Koken::meta(); ?>';
		}

	}