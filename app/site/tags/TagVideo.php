<?php

	class TagVideo extends Tag {

		function generate()
		{

			if (isset($this->parameters['data']))
			{
				$token = $this->field_to_keys($this->parameters['data']);
				unset($this->parameters['data']);
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
			}


			$params = array();

			if (isset($this->parameters['class']))
			{
				$this->parameters['class'] = 'k-video ' . $this->parameters['class'];
			}
			else
			{
				$this->parameters['class'] = 'k-video';
			}

			foreach($this->parameters as $key => $val)
			{
				$val = $this->attr_parse($val);
				$params[] = "$key=\"$val\"";
			}

			$params = join(' ', $params);

			return <<<DOC
<?php
	if ({$token}['html'])
	{
		echo str_replace('<iframe', '<iframe class="k-custom-source"', {$token}['html']);
	}
	else
	{
		if (isset({$token}['presets']) && count({$token}['presets']))
		{
			\$top_preset = array_pop({$token}['presets']);
			\$poster = 'poster="' . \$top_preset['url'] . '"';
		}
		else
		{
			\$poster = '';
		}
?>
<video preload="none" id="k-video-<?php echo {$token}['id']; ?>" data-src="<?php echo {$token}['original']['url']; ?>" $params <?php echo \$poster; ?>></video>

<script>
	$(function() {
		var v = $('#k-video-<?php echo {$token}['id']; ?>');
		if (v.parents('#pjax-container-staging').length) return;
		v.attr('src', v.data('src'));
		v.attr('preload', 'metadata');
		<?php if (is_numeric({$token}['aspect_ratio']) && {$token}['aspect_ratio'] > 0): ?>
		var w = Math.min(isNaN(v.attr('width')) ? Infinity : v.attr('width'), Math.min(<?php echo {$token}['width']; ?>, v.parent().width())),
			h = w / <?php echo {$token}['aspect_ratio']; ?>;
		v.data('aspect', <?php echo {$token}['aspect_ratio']; ?> );
		v.data('width', <?php echo {$token}['width']; ?> );
		v.attr('height', h);
		v.css({
			width: w,
			height: h
		});
		<?php else: ?>
		v.attr('width', v.parent().width());
		<?php endif; ?>
		var m = $('#k-video-<?php echo {$token}['id']; ?>').mediaelementplayer({
			pluginPath: \$K.location.real_root_folder + '/app/site/themes/common/js/',
			success: function(player, dom) {
				$(player).bind('loadedmetadata', function() {
					v.data('aspect', this.videoWidth / this.videoHeight );
					v.data('width', this.videoWidth );
					\$K.resizeVideos();

					var p = $('#k-video-<?php echo {$token}['id']; ?>');
					if (p.attr('autoplay')) {
						player.play();
					}
				});
			}
		});
	});
</script>

<?php } ?>
DOC;
		}

	}