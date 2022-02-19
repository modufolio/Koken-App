<?php

class TagUnlisted extends Tag
{
    protected $allows_close = true;

    public function generate()
    {
        return <<<DOC
<?php if (!Koken::\$public): ?>
DOC;
    }
}
