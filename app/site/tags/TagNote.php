<?php

    class TagNote extends Tag
    {
        protected $allows_close = true;

        public function generate()
        {
            return <<<DOC
<?php
	if (Koken::\$draft):
?>
<span class="k-note">
DOC;
        }

        public function close()
        {
            return <<<DOC
</span>
<?php
	endif;
?>
DOC;
        }
    }
