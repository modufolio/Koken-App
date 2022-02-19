<?php

    class TagForm extends Tag
    {
        protected $allows_close = true;

        public function generate()
        {
            $params = $this->params_to_array_str();
            return <<<DOC
<?php
	echo Koken::form(array($params));
?>
DOC;
        }

        public function close()
        {
            return '</form>';
        }
    }
