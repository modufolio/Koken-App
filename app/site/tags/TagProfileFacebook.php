<?php

    class TagProfileFacebook extends Tag
    {
        protected $allows_close = true;

        public function generate()
        {
            return <<<OUT
<?php

	if (!empty(Koken::\$profile['facebook'])):

?>
OUT;
        }
    }
