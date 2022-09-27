<?php

    class TagLink extends Tag
    {
        protected $allows_close = true;

        public function generate()
        {
            $attr = [];
            $get = false;

            $params = $this->params_to_array_str();

            if (isset($this->parameters['echo'])) {
                $this->allows_close = false;
            }

            return "<?php Koken::link(array($params)); ?>";
        }

        public function close()
        {
            return $this->allows_close ? '</a>' : '';
        }
    }
