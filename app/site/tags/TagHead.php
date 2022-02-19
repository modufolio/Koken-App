<?php

	class TagHead extends Tag {
			

		function generate()
		{
			return '<!-- KOKEN HEAD BEGIN -->';
		}

		function close() {
			return '<!-- KOKEN HEAD END -->';
		}
		
	}