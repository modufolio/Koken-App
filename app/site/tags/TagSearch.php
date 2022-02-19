<?php

	class TagSearch extends Tag {
    protected $allows_close = false;

		function generate()
		{
			if (!isset(Koken::$template_routes['tag']))
      {
        return;
      }

      $placeholder = Koken::$language['search_tags'];
      $tag_route = Koken::$location['root'] . Koken::$template_routes['tag'];
      $klass = 'k-search';

      if (isset($this->parameters['class']))
      {
        $this->parameters['class'] .= ' ' . $klass;
      }
      else
      {
        $this->parameters['class'] = $klass;
      }

      $params = array();

      foreach($this->parameters as $key => $val)
      {
        $params[] = "$key=\"$val\"";
      }

      $params = join(' ', $params);

      return <<<OUT
<form {$params}>
  <input type="search" id="k-search-tag" placeholder="{$placeholder}" list="k-search-tags">
  <datalist id="k-search-tags">

<?php
\$__tags = Koken::api('/tags/limit:0');

if (isset(\$__tags['tags']) && is_array(\$__tags['tags']))
{
  foreach (\$__tags['tags'] as \$__t) {
    if (\$__t['counts']['total'] > 0)
    {
      echo '<option value="' . \$__t['title'] . '">';
    }
  }
}
?>

  </datalist>
</form>
<script>
$('.k-search').off('submit').on('submit', function(e) {
  var url = '{$tag_route}'.replace(':slug', encodeURI(this['k-search-tag'].value));

  e.preventDefault();
  window.location.assign(url);
});

$('.k-search input[type="search"]').off('input').on('input', function(e) {
  if (this.list && $.contains(this.list, $('[value="' + this.value + '"]').get(0))) {
    $(this.form).submit();
  }
});
</script>
OUT;
		}
	}