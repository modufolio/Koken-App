<?php

	class TagPrint extends Tag {

		function generate()
		{
			if (isset($this->parameters['debug']))
			{
				$val = '$value' . Koken::$tokens[0];
				return "<?php print_r($val); ?>";
			}
			$token = $this->field_to_keys('data');
			if (isset($this->parameters['fallback']))
			{
				$fallback = $this->field_to_keys('fallback');
			}
			else
			{
				$fallback = false;
			}

			$format_pre = $format_post = '';

			if (preg_match('/(_on|\.?date)$/', $this->parameters['data']))
			{
				if (isset($this->parameters['date_format']))
				{
					$f = "'{$this->parameters['date_format']}'";
				}
				else
				{
					if (isset($this->parameters['date_only']))
					{
						$f = "Koken::\$site['date_format']";
					}
					else if (isset($this->parameters['time_only'])) {
						$f = "Koken::\$site['time_format']";
					}
					else
					{
						$f = "Koken::\$site['date_format'] . ' ' . Koken::\$site['time_format']";
					}
				}
				$pre = "date($f, ";
				$post = "['timestamp'])";
			}
			else
			{
				list($pre, $post) = $this->parse_formatters();
			}

			if ($token)
			{
				if ($fallback)
				{
					return <<<DOC
<?php echo empty($token) ? {$pre}$fallback{$post} : {$pre}$token{$post}; ?>
DOC;
				}
				else
				{
					return <<<DOC
<?php echo {$pre}$token{$post}; ?>
DOC;
				}
			}
		}

		private function parse_formatters()
		{
			$format_pre = $format_post = '';

			if (!empty($this->parameters))
			{
				if (isset($this->parameters['case']))
				{
					switch($this->parameters['case'])
					{
						case 'lower':
							$format_pre = 'strtolower(' . $format_pre;
							$format_post = ')';
							break;

						case 'upper':
							$format_pre = 'strtoupper(' . $format_pre;
							$format_post = ')';
							break;

						case 'title':
							$format_pre = 'ucwords(' . $format_pre;
							$format_post = ')';
							break;

						case 'sentence':
							$format_pre = 'ucfirst(' . $format_pre;
							$format_post = ')';
							break;
					}
				}

				if (isset($this->parameters['truncate']) && is_numeric($this->parameters['truncate']))
				{
					$after = isset($this->parameters['after_truncate']) ? $this->parameters['after_truncate'] : '...';
					$format_pre = 'Koken::truncate(' . $format_pre;
					$format_post .= ", {$this->parameters['truncate']},  '$after')";
				}

				if (isset($this->parameters['paragraphs']))
				{
					$format_pre = "Koken::format_paragraphs(" . $format_pre;
					$format_post .= ")";
				}
			}

			return array($format_pre, $format_post);
		}

	}