<?php

class DDI_StoreOriginals extends KokenPlugin implements KokenOriginalStore
{
    #[\Override]
    public function send($localFile, $key)
    {
        return false;
    }
}
