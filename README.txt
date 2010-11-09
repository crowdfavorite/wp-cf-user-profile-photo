## Setting up to filter get_avatar

You can optionally also set the plugin to automatically filter get_avatar. This consists of two steps: running the avatar setup method and setting the avatar sizes that will be used.

	if (class_exists('cf_User_Profile_Photo')) {
		$avatars = new cf_User_Profile_Photo;
		
		// Setup
		$avatars->setup_custom_avatars();
		$avatars->add_avatar_size(50); // add as many of these as you need
	}

You can use the `add_avatar_size()` as many times as you need - one for every size of avatar you use. cf_User_Profile_Photo will automatically register a custom image size at these dimensions.