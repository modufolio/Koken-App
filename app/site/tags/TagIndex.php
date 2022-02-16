<?php

	class TagIndex extends Tag {

		function generate()
		{
			$token = Koken::$tokens[0];
			$parent = Koken::$tokens[1];
			return <<<DOC
<?php echo \$key{$token} + 1 + ( isset(\$page{$parent}) ? ((\$page{$parent}['page'] - 1) * \$page{$parent}['per_page']) : 0 ); ?>
DOC;
		}

	}