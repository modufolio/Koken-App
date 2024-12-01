<?php

    class TagKeyboardScroll extends Tag
    {
        public function generate()
        {
            $defaults = ['offset' => '0'];
            $options = array_merge($defaults, $this->parameters);

            return <<<OUT
<script>\$K.keyboard.scroll.init('{$options['element']}', {$options['offset']});</script>
OUT;
        }
    }
