<?php

	class TagPagination extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{
			$token = Koken::$tokens[0];
			$page = '$page' . Koken::$tokens[1];
			$limit_var = '$limit' . Koken::$tokens[0];
			$limit = $limit_var . ' = ' . (isset($this->parameters['limit']) ? '"' . $this->attr_parse($this->parameters['limit']) . '"' : 'false') . ';';

			return <<<DOC
<?php

	if(isset($page) && {$page}['pages'] > 1):
		$limit

		\$__start = 1;
		\$__end = {$page}['pages'];

		if ($limit_var && \$__end > $limit_var)
		{
			$limit_var -= 1;
			\$__bottom = ceil($limit_var / 2);
			\$__top = $limit_var - \$__bottom;
			if ({$page}['page'] > \$__bottom)
			{
				\$__start = {$page}['page'] - \$__bottom;
			}
			else
			{
				\$__top += \$__bottom - {$page}['page'] + 1;
			}

			\$__end = {$page}['page'] + \$__top;

			if (\$__end > {$page}['pages'])
			{
				\$__start = max(1, \$__start - (\$__end - {$page}['pages']));
				\$__end = {$page}['pages'];
			}
		}

		\$__start = (int) \$__start;
		\$__end = (int) \$__end;

		\$value{$token} = {$page};
		\$value{$token}['limited'] = array(
			'previous' => \$__start !== 1 ? array('link' => rtrim(Koken::\$location['here'], '/') . '/page/' . (\$__start - 1) . '/') : false,
			'next' => \$__end !== {$page}['pages'] ? array('link' => rtrim(Koken::\$location['here'], '/') . '/page/' . (\$__end + 1) . '/') : false,
		);
		\$value{$token}['previous_page'] = array(
			'number' => {$page}['page'] - 1,
			'link' => rtrim(Koken::\$location['here'], '/') . ({$page}['page'] > 2 ?  '/page/' . ({$page}['page'] - 1) : '' ) . '/'
		);
		\$value{$token}['next_page'] = array(
			'number' => {$page}['page'] + 1,
			'link' => rtrim(Koken::\$location['here'], '/') . '/page/' . ({$page}['page'] + 1) . '/'
		);
		\$value{$token}['__loop__'] = array();
		foreach(range(\$__start, \$__end) as \$num):
			\$value{$token}['__loop__'][] = array(
				'number' => \$num,
				'is_current' => \$num == {$page}['page'] ? 'k-pagination-current' : '',
				'link' => rtrim(Koken::\$location['here'], '/') . (\$num == 1 ? '/' : '/page/' . \$num . '/')
			);
		endforeach;

		if ({$page}['page'] > 1):
			echo '<!-- KOKEN HEAD BEGIN --><link rel="prev" href="' . \$value{$token}['previous_page']['link'] . '" /><!-- KOKEN HEAD END -->';
		endif;

		if ({$page}['page'] < {$page}['pages']):
			echo '<!-- KOKEN HEAD BEGIN --><link rel="next" href="' . \$value{$token}['next_page']['link'] . '" /><!-- KOKEN HEAD END -->';
		endif;
?>
DOC;
		}

	}