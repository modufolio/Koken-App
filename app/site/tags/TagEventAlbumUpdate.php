<?php

	class TagEventAlbumUpdate extends Tag {

		protected $allows_close = true;

		function generate()
		{
			$token = '$value' . Koken::$tokens[0];

			return <<<DOC
<?php

	if (isset({$token}['album_type']) && isset({$token}['event_type']) && {$token}['event_type'] === 'album_update'):
		{$token}['__loop__'] = {$token}['content'];
		{$token}['album'] = Koken::\$current_token['album'] =& $token;
?>
DOC;
		}

	}