<?php

    class TagMeta extends Tag
    {
        public function generate()
        {
            return '<?php echo Koken::meta(); ?>';
        }
    }
