<?php

	class TagLoad extends Tag {

		public $tokenize = true;
		protected $allows_close = true;
		public $source = false;

		function generate()
		{

			$params = array();
			foreach($this->parameters as $key => $val)
			{
				if ($key === 'tree') continue;
				$params[] = "'$key' => \"" . $this->attr_parse($val) . '"';
			}

			$params = join(',', $params);

			if (!Koken::$main_load_token && !isset($this->parameters['source']) && isset($this->parameters['infinite']))
			{
				$infinite = $this->attr_parse($this->parameters['infinite']);
				Koken::$main_load_token = Koken::$tokens[0];
				if (isset($this->parameters['infinite_toggle']))
				{
					$infinite_selector = $this->attr_parse($this->parameters['infinite_toggle']);
					unset($this->parameters['infinite_toggle']);
				}
				else
				{
					$infinite_selector = '';
				}
				unset($this->parameters['infinite']);
			}
			else
			{
				$infinite = 'false';
				$infinite_selector = '';
			}

			$main = '$value' . Koken::$tokens[0];
			$curl = '$curl' . Koken::$tokens[0];
			$page = '$page' . Koken::$tokens[0];
			$options = '$options' . Koken::$tokens[0];
			$collection_name = '$collection' . Koken::$tokens[0];
			$paginate = '$paginate' . Koken::$tokens[0];
			$custom_source_var = '$source' . Koken::$tokens[0];
			$custom_source = $custom_source_var . ' = ' . (isset($this->parameters['source']) ? 'true' : 'false');
			$load_url = '$url' . Koken::$tokens[0];
			$load_url_var = '\$url' . Koken::$tokens[0];
			$top_token = Koken::$tokens[0];

			return <<<DOC
<?php

	list($load_url, $options, $collection_name, $paginate) = Koken::load( array($params) );
	$custom_source;

	if ($paginate)
	{
		if (isset(Koken::\$location['parameters']['page']))
		{
			$load_url .= '/page:' . Koken::\$location['parameters']['page'];
		}
	}

	if ({$options}['list'] && isset(Koken::\$routed_variables['tags']))
	{
		$load_url .= '/tags:' . Koken::\$routed_variables['tags'];
	}

	Koken::\$load_history[] = $load_url;

	$main = Koken::api($load_url);

	if (!$custom_source_var && isset({$main}['error']))
	{
		header("Location: " . Koken::\$location['root_folder'] . "/error/{{$main}['http']}/");
	}

	if ({$options}['list'])
	{
		if (isset({$main}['page']))
		{
			$page = array(
				'page' => {$main}['page'],
				'pages' => {$main}['pages'],
				'per_page' => {$main}['per_page'],
				'total' => {$main}['total'],
			);

			if ($infinite)
			{
				Koken::\$location['__infinite_token'] = '$top_token';
?>
				<script>
					\$K.infinity.totalPages = <?php echo {$page}['pages']; ?>;
					\$K.infinity.selector = '$infinite_selector';
				</script>
<?php
			}
		}

		if (isset({$main}['content']))
		{
			{$main}['__loop__'] = {$main}['content'];
		}
		else if (isset({$main}['albums']))
		{
			{$main}['__loop__'] = {$main}['albums'];
		}
		else if (isset({$main}['text']))
		{
			{$main}['__loop__'] = {$main}['text'];
		}
		else if (isset({$main}['items']))
		{
			\$__arr = array('items' => {$main}['items']);
			if (isset({$main}['event']))
			{
				\$__arr['__koken__'] = 'event';
				\$__arr['event'] = {$main}['event'];
			}
			{$main}['__loop__'] = array(\$__arr);
			if (isset({$main}['__koken__']) && !isset({$main}[{$main}['__koken__']]))
			{
				{$main}[{$main}['__koken__']] =& $main;
			}
		}
		else if (isset({$main}[$collection_name]))
		{
			{$main}['__loop__'] = {$main}[$collection_name];
			{$main}[$collection_name] =& {$main}['__loop__'];
		}
		else
		{
			{$main}['__loop__'] = {$main};
		}

		if (array_key_exists('counts', $main))
		{
			{$main}[$collection_name]['counts'] =& {$main}['counts'];
		}
	}

	if (({$options}['list'] && !empty({$main}['__loop__'])) || (!{$options}['list'] && $main && !isset({$main}['error']))):

		if ({$options}['list'])
		{
			if ({$options}['archive'])
			{
				switch({$options}['archive'])
				{
					case 'tag':
						{$main}['archive'] = array('__koken__' => 'tag', 'type' => 'tag', 'title' => str_replace(',', ', ', urldecode(isset(Koken::\$routed_variables['id']) ? Koken::\$routed_variables['id'] : Koken::\$routed_variables['slug'])));
						break;

					case 'category':
						{$main}['archive'] = array('__koken__' => 'category', 'type' => 'category', 'title' => {$main}['category']['title'], 'slug' => {$main}['category']['slug']);
						break;

					case 'date':
						{$main}['archive'] = array('__koken__' => 'archive', 'type' => 'date', 'day' => isset(Koken::\$routed_variables['day']) ? Koken::\$routed_variables['day'] : false, 'month' => isset(Koken::\$routed_variables['month']) ? Koken::\$routed_variables['month'] : false, 'year' => Koken::\$routed_variables['year']);
						break;
				}
			}
		}
		else
		{
			if (isset({$main}['page_type']) && isset({$main}['draft']))
			{
				{$main}['content'] = {$main}['draft'];
				{$main}['title'] = {$main}['draft_title'];
				echo '<script>$(document).ready( function() { \$K.textPreview(' . {$main}['id'] . ', ' . ({$main}['published'] ? 'true' : 'false') . '); } );</script>';
				echo <<<CSS
<!-- KOKEN HEAD BEGIN -->
<style type="text/css">
#k_essay_preview {
	height:28px !important;
	line-height:28px !important;
	width:100% !important;
	position:fixed !important;
	z-index:99999 !important;
	top:0 !important;
	left:0 !important;
	color:#bbb !important;
	font-size:12px !important;
	text-align:center !important;
	font-family:'HelveticaNeue-Medium', Helvetica, Arial, sans-serif !important;
	border-top: 1px solid #070707 !important;
	border-bottom: 1px solid #070707 !important;
	text-shadow: 0 1px 1px #000 !important;
	background-color: #303030 !important;
	background-repeat: repeat-x !important;
	background-image: -khtml-gradient(linear, left top, left bottom, from(#333), to(#242424)) !important;
	background-image: -moz-linear-gradient(#333, #242424) !important;
	background-image: -ms-linear-gradient(#333, #242424) !important;
	background-image: -webkit-gradient(linear, left top, left bottom, color-stop(0%, #333), color-stop(100%, #242424)) !important;
	background-image: -webkit-linear-gradient(#333, #242424) !important;
	background-image: linear-gradient(#333, #242424) !important;
}
#k_essay_preview a {
	color:#fff !important;
	text-decoration:none !important;
}
</style>
<!-- KOKEN HEAD END -->
CSS;
			}

			if (!isset({$main}[{$main}['__koken__']]))
			{
				{$main}[{$main}['__koken__']] =& $main;
			}
		}

		if (!$custom_source_var)
		{
			\$__meta_source = $main;

			if (!empty({$main}['title']))
			{
				\$the_title = {$main}['title'];
			}
			else if (isset({$main}['filename']))
			{
				\$the_title = {$main}['filename'];
			}
			else if (isset({$main}['album']['title']))
			{
				\$the_title = {$main}['album']['title'];
				\$__meta_source = {$main}['album'];
			}
			else if (isset({$main}['archive']['title']))
			{
				\$the_title = {$main}['archive']['title'];
			}
			else if (isset({$main}['event']))
			{
				\$__fmt = Koken::\$site['date_format'];
				if (!isset({$main}['event']['day']))
				{
					if (isset({$main}['event']['month']))
					{
						\$__fmt = 'F Y';
					}
					else
					{
						\$__fmt = 'Y';
					}
				}
				\$the_title = date(\$__fmt, strtotime({$main}['event']['year'] . '-' . (isset({$main}['event']['month']) ? {$main}['event']['month'] : '01') . '-' . (isset({$main}['event']['day']) ? {$main}['event']['day'] : '01')));
			}
			else if (isset({$main}['archive']))
			{
				\$the_title = Koken::title_from_archive({$main}['archive']);
			}

			if (isset({$main}['canonical_url']) || isset({$main}['album']['canonical_url']))
			{
				\$__canon = isset({$main}['canonical_url']) ? {$main}['canonical_url'] : {$main}['album']['canonical_url'];
				echo '<!-- KOKEN HEAD BEGIN --><link rel="canonical" href="' . \$__canon . '"><!-- KOKEN HEAD END -->';
			}

			if (isset(\$the_title) && isset(Koken::\$the_title_separator) && !Koken::\$page_title_set)
			{
				Koken::\$page_title_set = true;
				echo '<!-- KOKEN HEAD BEGIN --><koken_title>' . \$the_title . Koken::\$the_title_separator . Koken::\$site['page_title'] . '</koken_title><!-- KOKEN HEAD END -->';
			}

			if (isset({$main}['essay']) && !isset(\$_COOKIE['koken_session_ci']) && !{$main}['essay']['published'])
			{
				header('Location: ' . Koken::\$location['root'] . '/error/403/');
				exit;
			}

			\$__public = isset({$main}['public']) ? {$main}['public'] : ( isset({$main}['album']['public']) ? {$main}['album']['public'] : true );

			if (!\$__public)
			{
				echo '<!-- KOKEN HEAD BEGIN --><meta name="robots" content="noindex" /><!-- KOKEN HEAD END -->';
			}

			if (isset({$main}['album']) || isset({$main}['context']['album']))
			{
				if (isset({$main}['album']))
				{
					\$__rss = {$main}['album']['rss'] = Koken::\$location['root'] . '/feed/albums/' . {$main}['album']['id'] . '/recent.rss';
					\$__title = {$main}['album']['title'];
					\$__public = {$main}['album']['public'];
				}
				else
				{
					\$__rss = {$main}['context']['album']['rss'] = Koken::\$location['root'] . '/feed/albums/' . {$main}['context']['album']['id'] . '/recent.rss';
					\$__title = {$main}['context']['album']['title'];
					\$__public = {$main}['context']['album']['public'];
				}
				if (\$__public)
				{
					echo '<!-- KOKEN HEAD BEGIN --><link rel="alternate" type="application/atom+xml" title="' . Koken::\$site['page_title'] . ': Uploads from ' . \$__title . '" href="' . \$__rss . '" /><!-- KOKEN HEAD END -->';
				}
			}

			\$__meta = array('description' => '', 'keywords' => array());

			\$__candidates = array('summary', 'description', 'caption', 'excerpt', 'title', 'filename');

			while (strlen(\$__meta['description']) === 0 && count(\$__candidates))
			{
				\$__field = array_shift(\$__candidates);
				if (isset(\$__meta_source[\$__field]) && strlen(\$__meta_source[\$__field]) > 0)
				{
					\$__meta['description'] = preg_replace('/\s+/', ' ', preg_replace('/\n+/', ' ', strip_tags(\$__meta_source[\$__field])));
				}
			}

			if (isset(\$__meta_source['tags']) && !isset(\$__meta_source['page']))
			{
				foreach(\$__meta_source['tags'] as \$__tag)
				{
					\$__meta['keywords'][] = \$__tag['title'];
				}
			}

			echo '<!-- KOKEN META DESCRIPTION BEGIN -->' . Koken::truncate(\$__meta['description'], 160) . '<!-- KOKEN META DESCRIPTION END -->';
			echo '<!-- KOKEN META KEYWORDS BEGIN -->' . join(', ', \$__meta['keywords']) . '<!-- KOKEN META KEYWORDS END -->';
		}
?>
DOC;

		}

	}