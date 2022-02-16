<?php

	class TagTime extends Tag {

		function generate()
		{

			$defaults = array('show' => 'date', 'class' => 'false', 'rss' => 'false', 'relative' => 'false');
			$options = array_merge($defaults, $this->parameters);


			if (isset($this->parameters['data']))
			{
				$token = $this->field_to_keys('data');
			}
			else
			{
				$token = '$value' . (count(Koken::$tokens) ? Koken::$tokens[0] : '');
			}

			$attr = array();
			foreach($options as $key => $val)
			{
				$val = $this->attr_parse($val);
				$comp = "'$key' => \"$val\"";
				if (!isset($defaults[$key]))
				{
					$attr[] = $comp;
				}
				$params[] = $comp;
			}

			$params = join(',', $params);
			$attr = join(',', $attr);

			return "<?php Koken::time(isset($token) ? $token : false, array($params), array($attr)); ?>";

		}

	}