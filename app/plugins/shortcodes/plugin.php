<?php

class BD_Shortcodes extends KokenPlugin {

	function __construct()
	{
		$this->register_shortcode('koken_photo', 'koken_media');
		$this->register_shortcode('koken_video', 'koken_media');
		$this->register_shortcode('koken_oembed', 'koken_oembed');
		$this->register_shortcode('koken_slideshow', 'koken_slideshow');
		$this->register_shortcode('koken_upload', 'koken_upload');
		$this->register_shortcode('koken_code', 'koken_code');
		$this->register_shortcode('koken_contact_form', 'koken_contact_form');

		$this->register_hook('site.url', 'koken_contact_submit');
	}

	function koken_contact_submit()
	{
		if (isset($_POST) && isset($_POST['koken_contact_form']))
		{
			if (!empty($_POST['k-contact-field-dummy']))
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
				exit;
			}
			else if (!empty($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-key']))
			{
				$gRecaptchaResponse = $_POST['g-recaptcha-response'];
				$secret_key = $_POST['g-recaptcha-key'];

				$key = Shutter::get_encryption_key();
				$secret_key = base64_decode($secret_key);
				$iv = substr($secret_key, 0, 16);
				$secret_key = substr($secret_key, 16);
				$secret_key = openssl_decrypt($secret_key, 'AES-256-CTR', $key, 0, $iv);

				if (isset($secret_key))
				{
					require(Koken::$root_path . '/app/application/libraries/ReCaptcha/autoload.php');
					$recaptcha = new \ReCaptcha\ReCaptcha($secret_key);
					$resp = $recaptcha->verify($gRecaptchaResponse);

					if (!$resp->isSuccess()) {
						header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
						exit;
					}
				}
			}
			else if (!isset($_POST['g-recaptcha-response'], $_POST['g-recaptcha-key']) && !isset($_POST['k-contact-field-dummy']))
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
				exit;
			}

			// convert unicode escaped to utf-8
			$labels = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
			    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
			}, $_POST['labels']);

			// stripslashes required here for PHP 5.3 weirdness
			$labels = json_decode(stripslashes($labels), true);
			$separator = str_repeat('–', 30);

			$msg = array("You have received a new Koken contact form submission. The details of the submission are below.");
			foreach($labels as $index => $label)
			{
				$key = 'k-contact-field-' . $index;
				$value = isset($_POST[$key]) ? $_POST[$key] : 'No';

				if (empty($value)) continue;

				$msg[] = "$label\n$separator\n$value";
			}

			$msg = join("\n\n\n", $msg);

			$from = $_POST[$_POST['from_field']];

			$this->deliver_email($from, $from, '[' . $_SERVER['HTTP_HOST'] . '] Koken Contact Form Submission', $msg);

			exit;
		}
	}

	function koken_contact_form($attr)
	{
		$id = 'k-contact-form-' . md5(uniqid('', true));
		$out = <<<HTML
<figure class="k-content-embed">
	<p class="k-contact-form-success" style="display: none">{$attr['success_message']}</p>
	<koken:form id="$id" class="k-contact-form">
		<input type="hidden" name="koken_contact_form" value="1" />
HTML;

		$required = array();
		$labels = array();

		$fromEmail = false;

		foreach(json_decode($attr['fields']) as $index => $field)
		{
			$labels[] = htmlentities($field[0], ENT_QUOTES);
			$name = 'k-contact-field-' . $index;
			$type = $field[1];

			$field_id = $id . '-' . $name;

			if ($field[2]) {
				$required[] = $name;
			}

			if ($type === 'email' && !$fromEmail)
			{
				$fromEmail = $name;
			}

			if ($type === 'textarea')
			{
				$input = '<textarea id="' . $field_id . '" name="' . $name . '" rows="10"></textarea>';
			}
			else
			{
				$input = '<input id="' . $field_id . '" name="' . $name . '" type="' . $type . ($type === 'checkbox' ? '" value="Yes"' : '"') . ' />';
			}

			$required_class = $field[2] ? ' k-contact-form-required-field' : '';

			$before_label = $after_label = '';

			if ($type === 'checkbox')
			{
				$before_label = $input;
			}
			else
			{
				$after_label = $input;
			}

			$out .= <<<HTML
<fieldset class="k-contact-form-$type-field{$required_class}">
	{$before_label}
	<label for="$field_id">{$field[0]}</label>
	{$after_label}
</fieldset>
HTML;
		}

		list($recaptcha, $site_key, $secret_key) = json_decode($attr['recaptcha']);

		if ($recaptcha)
		{
			$key = Shutter::get_encryption_key();
			$iv = openssl_random_pseudo_bytes(16);
			$secret_key = openssl_encrypt($secret_key, 'AES-256-CTR', $key, 0, $iv);
			$secret_key = base64_encode($iv . $secret_key);
			$required[] = 'g-recaptcha-response';

			$out .= <<<HTML
<script src='https://www.google.com/recaptcha/api.js'></script>
<fieldset class="k-contact-form-captcha-field k-contact-form-required-field">
	<label>Captcha</label>
	<div class="g-recaptcha" data-sitekey="{$site_key}"></div>
	<input type="hidden" name="g-recaptcha-key" value="{$secret_key}" />
</fieldset>
HTML;
		}
		else
		{
			$out .= <<<HTML
<input type="text" name="k-contact-field-dummy" style="display:none" />
HTML;
		}

		$required = json_encode($required);
		$labels = json_encode($labels);

		$out .= <<<HTML
<fieldset class="k-contact-form-submit">
	<input type="hidden" name="labels" value='$labels' />
	<input type="hidden" name="from_field" value="$fromEmail" />
	<button type="submit">{$attr['button_label']}</button>
</fieldset>

</koken:form>
</figure>

<script>
	$(document).off('submit.contact').on('submit.contact', '#$id', function() {
		var form = $(this);
		var button = form.find('button[type="submit"]');
		var required = $required;
		var valid = true;

		button.attr('disabled', true);

		form.find('.k-contact-form-error').removeClass('k-contact-form-error');

		$.each(required, function(i, name) {
			var input = form.find('[name="' + name + '"]');
			if (!input.val().length) {
				input.parents('fieldset').addClass('k-contact-form-error');
				if (valid) {
					input.focus();
				}
				valid = false;
			}
		});


		if (valid) {
			form.addClass('k-contact-form-processing');

			$.post(location.href, form.serialize(), function() {
				form.parent().find('.k-contact-form-success').show();
				form.hide();
			});
		} else {
			button.attr('disabled', false);
		}

		return false;
	});
</script>
HTML;

		return $out;
	}

	function koken_oembed($attr)
	{
		if (!isset($attr['url']) || !isset($attr['endpoint'])) { return ''; }

		$endpoint = $attr['endpoint'];

		if (strpos($endpoint, 'maxwidth=') === false)
		{
			if (strpos($endpoint, '?') !== false)
			{
				$endpoint .= '&';
			}
			else
			{
				$endpoint .= '?';
			}

			$endpoint .= 'maxwidth=1920&maxheight=1080';
		}

		if (strpos($endpoint, '?') !== false)
		{
			$endpoint .= '&';
		}
		else
		{
			$endpoint .= '?';
		}

		$info = Shutter::get_oembed($endpoint . 'url=' . $attr['url']);

		if (isset($info['html']))
		{
			$html = preg_replace('/<iframe/', '<iframe style="display:none"', $info['html']);
		}
		else if (isset($info['url'])) {
			$html = '<img src="' . $info['url'] . '" />';
		}
		else
		{
			return '';
		}
		return '<figure class="k-content-embed"><div class="k-content">' . $html . '</div></figure>';
	}

	function koken_media($attr)
	{
		if (!isset($attr['id'])) { return ''; }

		if ($attr['media_type'] === 'image')
		{
			$tag = 'img lazy="true"';

			if (isset($attr['height']) && is_numeric($attr['height']))
			{
				$tag .= ' height="' . $attr['height'] . '"';
			}
		}
		else
		{
			$tag = 'video';
		}

		$fig_style = '';
		if (isset($attr['width']) && is_numeric($attr['width']))
		{
			$tag .= ' width="' . $attr['width'] . '"';
			$fig_style = ' style="width:' . $attr['width'] . 'px;"';
		}

		$text = '';
		if (!isset($attr['caption']) || $attr['caption'] !== 'none')
		{
			if (!isset($attr['caption']) || $attr['caption'] === 'both')
			{
				$text .= '<koken:not empty="content.title && content.caption">';
			}
			else
			{
				$text .= '<koken:not empty="content.' .  $attr['caption'] . '">';
			}
			$text .= '<figcaption class="k-content-text">';
			if (!isset($attr['caption']) || $attr['caption'] !== 'caption')
			{
				$text .= '<koken:not empty="content.title"><span class="k-content-title">{{ content.title }}</span></koken:if>';
			}
			if (!isset($attr['caption']) || $attr['caption'] !== 'title')
			{
				$text .= '<koken:not empty="content.caption"><span class="k-content-caption">{{ content.caption }}</span></koken:if>';
			}
			$text .= '</figcaption></koken:not>';
		}

		$link_pre = $link_post = $context_param = '';

		if (isset($attr['link']) && $attr['link'] !== 'none')
		{
			if ($attr['link'] === 'detail' || $attr['link'] === 'lightbox')
			{
				$link_pre = '<koken:link' . ( $attr['link'] === 'lightbox' ? ' lightbox="true"': '' ) . '>';
				$link_post = '</koken:link>';
			}
			else if ($attr['link'] === 'album')
			{
				$context_param = " filter:context=\"{$attr['album']}\"";
				$link_pre = '<koken:link data="context.album">';
				$link_post = '</koken:link>';
			}
			else
			{
				$link_pre = '<a href="' . $attr['custom_url'] . '">';
				$link_post = '</a>';
			}
		}

		return <<<HTML
<figure class="k-content-embed" ${fig_style}>
	<koken:load source="content" filter:id="{$attr['id']}"$context_param>
		<div class="k-content">
			$link_pre
			<koken:$tag />
			$link_post
		</div>
		$text
	</koken:load>
</figure>
HTML;

	}

	function koken_upload($attr)
	{
		$text = '';
		$src = $attr['filename'];
		$link_pre = $link_post = '';

		if (isset($attr['link']) && !empty($attr['link']))
		{
			$link_pre = '<a href="' . $attr['link'] . '"' . ( isset($attr['target']) && $attr['target'] !== 'none' ? ' target="_blank"' : '' ) . '>';
			$link_post = '</a>';
		}

		if (isset($attr['title']) && !empty($attr['title']))
		{
			$text .= '<span class="k-content-title">' . $attr['title'] . '</span>';
		}

		if (isset($attr['caption']) && !empty($attr['caption']))
		{
			$text .= '<span class="k-content-caption">' . $attr['caption'] . '</span>';
		}

		if (!empty($text))
		{
			$text = "<figcaption class=\"k-content-text\">$text</figcaption>";
		}

		if (strpos($src, 'http://') === 0)
		{
			return <<<HTML
<figure class="k-content-embed">
	<div class="k-content">
		$link_pre
		<img src="$src" style="max-width:100%" />
		$link_post
	</div>
	$text
</figure>
HTML;
		}
		else
		{
			return <<<HTML
<figure class="k-content-embed">
	<koken:load source="content" filter:custom="$src">
		<div class="k-content">
			$link_pre
			<koken:img lazy="true" />
			$link_post
		</div>
		$text
	</koken:load>
</figure>
HTML;
		}

	}

	function koken_code($attr)
	{
		if (isset($attr['code']))
		{
			return $attr['code'];
		}
		else
		{
			return '';
		}
	}

	function koken_slideshow($attr)
	{
		$rand = 'p' . md5(uniqid(function_exists('mt_rand') ? mt_rand() : rand(), true));

		if (!isset($attr['link_to']))
		{
			$attr['link_to'] = 'default';
		}

		$attr['link_to'] = 'link_to="' . $attr['link_to'] . '"';

		if (isset($attr['content']))
		{
			$path = '/content/' . $attr['content'];
		}
		else if (isset($attr['album']))
		{
			$path = '/albums/' . $attr['album'] . '/content';
		}

		$text = '';
		if (isset($attr['caption']) && $attr['caption'] !== 'none')
		{
			$text .= '<figcaption id="' . $rand .'_text" class="k-content-text">';
			if ($attr['caption'] !== 'caption')
			{
				$text .= '<span class="k-content-title">&nbsp;</span>';
			}
			if ($attr['caption'] !== 'title')
			{
				$text .= '<span class="k-content-caption">&nbsp;</span>';
			}
			$text .= '</figcaption>';
			$text .= <<<JS
	<script>
		$rand.on( 'transitionstart', function(e) {
			var title = $('#{$rand}_text').find('.k-content-title'),
				caption = $('#{$rand}_text').find('.k-content-caption');

			if (title) {
				title.text(e.data.title || e.data.filename);
			}

			if (caption) {
				caption.html(e.data.caption);
			}
		});
	</script>
JS;
		}

		return <<<HTML
<figure class="k-content-embed">
	<div class="k-content">
		<koken:pulse jsvar="$rand" data_from_url="$path" size="auto" {$attr['link_to']} group="essays" />
	</div>
	$text
</figure>
HTML;

	}
}
