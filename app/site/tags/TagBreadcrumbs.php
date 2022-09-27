<?php

    class TagBreadcrumbs extends Tag
    {
        public function generate()
        {
            $params = [];
            foreach ($this->parameters as $key => $val) {
                $params[] = "'$key' => \"" . $this->attr_parse($val) . '"';
            }

            $params = implode(',', $params);
            return "<?php echo Koken::breadcrumbs(array($params)); ?>";
        }
    }
