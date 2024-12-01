<?php

    class Tag
    {
        public $tokenize 		= false;
        public $untokenize_on_else = false;
        protected $allows_close	= false;
        protected $attr_parse_level = 0;

        public function __construct(protected $parameters = [])
        {
        }

        public function attr_replace($matches)
        {
            return '" . ' . $this->field_to_keys($matches[1]) . '. "';
        }

        public function out_cb($matches)
        {
            return '" . Koken::out(\'' . trim(str_replace("'", "\\'", $matches[1])) . '\') . "';
        }

        public function params_to_array_str()
        {
            $params = [];
            foreach ($this->parameters as $key => $val) {
                if ($key === 'data') {
                    $params[] = "'data' => " . $this->field_to_keys($val);
                } else {
                    $params[] = "'$key' => \"" . $this->attr_parse($val) . '"';
                }
            }

            return join(',', $params);
        }
        public function attr_parse($val, $wrap = false)
        {
            $pattern = '/\{\{\s*([^\}]+)\s*\}\}/';
            if (preg_match($pattern, (string) $val)) {
                $o = preg_replace_callback($pattern, $this->out_cb(...), (string) $val);
                if ($wrap) {
                    return '<?php echo "' . $o. '" ?>';
                } else {
                    return $o;
                }
            } else {
                $o =  preg_replace_callback('/\{([a-z_.0-9]+)\}/', $this->attr_replace(...), (string) $val);
                if ($wrap) {
                    return '<?php echo "' . $o. '" ?>';
                } else {
                    return $o;
                }
            }
        }

        public function field_to_keys($param, $variable = 'value', $token_index = false)
        {
            if (!$token_index) {
                $token_index = $this->attr_parse_level;
            }

            $bits = explode('|', (string) $param);

            $options = [];

            foreach ($bits as $param) {
                $param = trim($param);
                $prefix = $postfix = '';

                if (isset($this->parameters[$param])) {
                    $p = $this->parameters[$param];
                } else {
                    $p = $param;
                }

                $p = trim((string) $p, '{} ');

                preg_match('/^(site|location|profile|source|settings|routed_variables|page_variables|pjax|labels|messages)/', $p, $global_matches);

                if (count(Koken::$tokens) === 0 && count($global_matches) === 0 && !in_array($p, Koken::$template_variable_keys)) {
                    return "''";
                }

                if (count($global_matches)) {
                    $f = explode('.', $p);
                    array_shift($f);
                    $prefix = 'Koken::$' . $global_matches[1];

                    if ($global_matches[1] === 'settings') {
                        $s = array_shift($f);
                        if (isset(Koken::$settings['__scoped_' . str_replace('.', '-', Koken::$location['template']) . "_{$s}"])) {
                            $prefix .= "['__scoped_" . str_replace('.', '-', Koken::$location['template']) . "_{$s}']";
                        } else {
                            $prefix .= "['$s']";
                        }
                    }
                } elseif (in_array($p, Koken::$template_variable_keys)) {
                    return "Koken::\$template_variables['$p']";
                } elseif ($p === 'value') {
                    return "\$value" . Koken::$tokens[$token_index];
                } elseif ($p === 'count') {
                    return 'count($value' . Koken::$tokens[$token_index] . "['__loop__'])";
                } else {
                    $pre = '';
                    while (str_contains($p, '_parent.')) {
                        $p = substr($p, strlen('_parent.'));
                        $token_index++;
                    }
                    $p = str_replace('.first', '[0]', $p);
                    if (str_contains($p, '.last')) {
                        $partial = substr($p, 0, strpos($p, '.last'));
                        $p = str_replace('.last', '[count(' . $this->field_to_keys($partial, $variable, $token_index) . ') - 1]', $p);
                    }
                    if (str_contains($p, '.length')) {
                        $p = substr($p, 0, strpos($p, '.length'));
                        $postfix = ')';
                        $pre = 'count(';
                    }
                    $f = explode('.', $p);
                    $prefix = $pre . "\$$variable" . Koken::$tokens[$token_index];
                }

                $final = [];
                foreach ($f as $v) {
                    $bits = explode('[', $v);
                    $str = "['" . array_shift($bits) . "']";
                    if (count($bits)) {
                        $str .= '[' . join('[', $bits);
                    }

                    $final[] = $str;
                }
                $options[] = "{$prefix}" . join("", $final) . $postfix;
            }
            if (count($options) === 1) {
                return $options[0];
            } else {
                return "empty($options[0]) ? $options[1] : $options[0]";
            }
        }

        public function do_else()
        {
            return '<?php else: ?>';
        }

        public function close()
        {
            if ($this->allows_close) {
                return <<<DOC
<?php endif; ?>
DOC;
            }
        }
    }
