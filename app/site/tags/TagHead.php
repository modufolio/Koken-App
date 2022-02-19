<?php

    class TagHead extends Tag
    {
        public function generate()
        {
            return '<!-- KOKEN HEAD BEGIN -->';
        }

        public function close()
        {
            return '<!-- KOKEN HEAD END -->';
        }
    }
