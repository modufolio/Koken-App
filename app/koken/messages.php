<?php 

	// =========================
	// = Notification messages =
	// =========================
	// 
	// Do not add trailing periods, system will take care of that.
	//

	$koken_messages = array(
		
		// SYSTEM
		'system:install' => 'koken was installed', 

		// ALBUM
		'album:create' => '"%s" was created',
		'album:move' => '%d item was moved to the "%s" set',
		'album:move:multiple' => '%d items were moved to the "%s" set',
		'album:remove' => '%d item was removed from the "%s" set',
		'album:remove:multiple' => '%d items were removed from the "%s" set',
		'album:delete' => 'The "%s" album was deleted',
		'album:update' => 'The "%s" album was updated',
		
		// CONTENT
		'content:move' => '%s was moved to the %s album',
		'content:move:multiple' => '%d items were moved to the "%s" album',
		'content:remove' => '%s was removed from the "%s" album',
		'content:remove:multiple' => '%d items were removed from the "%s" album'
		
	);