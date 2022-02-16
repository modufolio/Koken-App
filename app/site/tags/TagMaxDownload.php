<?php

	class TagMaxDownload extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php
	if (isset({$token}['max_download']) && {$token}['max_download']['raw'] !== 'none'):
		\$__max = {$token}['max_download'];

		\$__dls = array();
		\$__last_width = \$__last_height = 0;

		foreach(array_slice({$token}['presets'], 2) as \$__name => \$__preset)
		{
			if (\$__preset['width'] === \$__last_width && \$__preset['height'] === \$__last_height) break;

			\$__last_width = \$__preset['width'];
			\$__last_height = \$__preset['height'];

			switch(\$__name)
			{
				case 'huge':
					\$__clean = 'Huge (2048)';
					break;
				case 'xlarge':
					\$__clean = 'X-Large (1600)';
					break;
				case 'large':
					\$__clean = 'Large (1024)';
					break;
				case 'medium_large':
					\$__clean = 'Medium-Large (800)';
					break;
				case 'medium':
					\$__clean = 'Medium (480)';
					break;
			}

			\$__dls[] = array(
				'title' => \$__clean,
				'label' => preg_replace('/\s.*$/', '', \$__clean),
				'link' => preg_replace('/(jpe?g|png|gif)$/i', '$1.dl', \$__preset['url']),
				'width' => \$__preset['width'],
				'height' => \$__preset['height'],
				'__koken__' => 'max_download'
			);

			if (\$__name === \$__max['raw']) break;
		}

		if (\$__max['raw'] === 'original' && !({$token}['original']['width'] === \$__last_width && {$token}['original']['height'] === \$__last_height))
		{
			\$__dls[] = array(
				'title' => 'Original',
				'label' => 'Original',
				'link' => Koken::\$location['host'] . Koken::\$location['real_root_folder'] . '/dl.php?src=' . {$token}['original']['relative_url'],
				'width' => {$token}['original']['width'],
				'height' => {$token}['original']['height'],
				'__koken__' => 'max_download'
			);
		}

		\$__dls = array_reverse(\$__dls);
		$ref = \$__dls[0];
		{$ref}['max_download'] =& $ref;
		{$ref}['content'] = $token;
		{$ref}['__loop__'] = \$__dls;
?>
OUT;
		}
	}