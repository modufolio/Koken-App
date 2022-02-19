<?php

    class TagValue extends Tag
    {
        public function generate()
        {
            $token = '$value' . Koken::$tokens[0];
            return <<<DOC
<?php echo $token; ?>
DOC;
        }
    }
