<?php

	class TagAlbumDownload extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{
			$token = count(Koken::$tokens) > 2 ? '$value' . Koken::$tokens[2] : '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];
			$cache_path = (Koken::$rewrite === true) ? '/storage/cache/albums/' : '/a.php?/';

			return <<<OUT
<?php
	\$__download = false;
  \$__visibility_values = array('public', 'unlisted', 'private');

	if (isset({$token}['__loop__']) && isset({$token}['album']) && {$token}['album']['album_type'] !== 'set')
	{
		\$__album_visibility = array_search({$token}['album']['visibility']['raw'], \$__visibility_values);

		foreach({$token}['__loop__'] as \$__content)
		{
    	\$__visibility = array_search(\$__content['visibility']['raw'], \$__visibility_values);

			if (\$__content['max_download']['raw'] !== 'none' && \$__visibility <= \$__album_visibility) {
				\$__download = true;
				break;
			}
		}
	}

	if (\$__download):
		\$__album_id = {$token}['album']['id'];
		\$__album_title = {$token}['album']['title'];
		\$__album_slug = {$token}['album']['slug'];
		\$__padded_id = str_pad(\$__album_id, 6, '0', STR_PAD_LEFT);
		\$__ts = {$token}['album']['modified_on']['timestamp'];

		\$__dl = array(
			'title' => \$__album_title,
			'link' => Koken::\$location['host'] . Koken::\$location['real_root_folder'] . '{$cache_path}' . substr(\$__padded_id, 0, 3) . '/' . substr(\$__padded_id, 3) . '/' . \$__ts . '/' . \$__album_slug . '.zip',
			'__koken__' => 'album_download',
		);

		$ref = \$__dl;
		{$ref}['album_download'] =& $ref;

?>
OUT;
		}
	}