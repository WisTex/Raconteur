Creating Addons
===============

So you want to make $Projectname do something it doesn't already do. There are lots of ways. But let's learn how to write an addon. 

In your $Projectname folder/directory, you will probably see a sub-directory called 'addon'. If you don't have one already, go ahead and create it. 
[code]
	mkdir addon
[/code]
Then figure out a name for your addon. You probably have at least a vague idea of what you want it to do. For our example I'm going to create a plugin called 'randplace' that provides a somewhat random location for each of your posts. The name of your plugin is used to find the functions we need to access and is part of the function names, so to be safe, use only simple text characters.

Once you've chosen a name, create a directory beneath 'addon' to hold your working file or files.
[code]
	mkdir addon/randplace
[/code]
Now create your addon file. It needs to have the same name, and it's a PHP script, so using your favourite editor, create the file
[code]
	addon/randplace/randplace.php
[/code]
The very first line of this file needs to be
[code]
	&lt;?php
[/code]
Then we're going to create a comment block to describe the addon. There's a special format for this. We use /* ... */ comment-style and some tagged lines consisting of
[code]
	/**
	 *
	 * Name: Random Place (here you can use better descriptions than you could in the filename)
	 * Description: Sample plugin, sets a random place when posting.
	 * Version: 1.0
	 * Author: Mike Macgirvin &lt;mike@macgirvin.com&gt;
	 *
	 */
[/code]
These tags will be seen by the site administrator when he/she installs or manages plugins from the admin panel. There can be more than one author. Just add another line starting with 'Author:'.

Next we will create a 'use' statement to include the code in Zotlabs/Lib/Apps.php

[code]
use Zotlabs\Lib\Apps;
[/code]
The typical addon will have at least the following functions:
[code]
 addonname_load()
 addonname_unload()
[/code]
In our case, we'll call them randplace_load() and randplace_unload(), as that is the name of our addon. These functions are called whenever we wish to either initialise the addon or remove it from the current webpage. Also if your addon requires things like altering the database schema before it can run for the very first time, you would likely place these instructions in the functions named
[code]
 addonname_install()
 addonname_uninstall()
[/code]

Next we'll talk about [b]hooks[/b], which are essentially event handlers. There are a lot of these, and they each have a name. What we normally do is use the addonname_load() function to register a &quot;handler function&quot; for any hooks you are interested in. Then when any of the corresponding events occur, your code will be called. These are all called with one argument, which is often an array of data or information that is specific to that hook or event. In order to change any information in that array, you must indicate in your handler function that the argument variable is to be passed "by reference". You can do this with '&$variable_name'.

We register hook handlers with the 'Zotlabs\Extend\Hook::register()' function. It typically takes 3 arguments. The first is the name of the hook we wish to catch, the second is the filename of the file to find our handler function (relative to the base of your $Projectname installation), and the third is the function name of your handler function. Then we'll use 'Zotlabs\Extend\Route::register()' to define a "controller" or web page. This requires two arguments. The first is the name of the file we wish to provide the controller logic and the second is the name of the webpage path where we want our controller to answer web requests. By convention we use addon/addonname/Mod_something.php as the filename and in this case the page will be found at https://{yoursite}/something.  So let's create our randplace_load() function right now. 

[code]
	function randplace_load() {
	    Zotlabs\Extend\Hook::register('post_local', 'addon/randplace/randplace.php', 'randplace_post_hook');
		
        Zotlabs\Extend\Route::register('addon/randplace/Mod_randplace.php', 'randplace');
	}
[/code]

Next we'll create an unload function. This is easy, as it just unregisters the things we registered. It takes exactly the same arguments. 
[code]
	function randplace_unload() {
	    Zotlabs\Extend\Hook::unregister('post_local', 'addon/randplace/randplace.php', 'randplace_post_hook');

		Zotlabs\Extend\Route::unregister('addon/randplace/Mod_randplace.php, 'randplace');
	}
[/code]


Let's go ahead and add some code to implement our post_local hook handler. 
[code]
	function randplace_post_hook(&amp;$item) {

	    /**
    	 *
	     * An item was posted on the local system.
    	 * We are going to look for specific items:
	     *      - A status post by a profile owner
    	 *      - The profile owner must have allowed our plugin
	     *
    	 */

	    logger('randplace invoked');

	    if (! local_channel()) {
			/* non-zero if this is a logged in user of this system */
	        return;
		}

	    if (local_channel() !== intval($item['uid'])) {
			/* Does this person own the post? */
	        return;
		}

	    if (($item['parent']) || (! is_item_normal($item))) {
		    /* If the item has a parent, or is not "normal", this is a comment or something else, not a status post. */
	        return;
		}

	    /* Only proceed if the 'randplace' addon is installed and the current channel has installed the 'randplace' app */

	    $active = Apps::addon_app_installed(local_channel(), 'randplace');

    	if (! $active) {
			/* We haven't installed or enabled it. Do nothing. */
        	return;
		}
		
	    /**
    	 *
	     * OK, we're allowed to do our stuff.
    	 * Here's what we are going to do:
	     * load the list of timezone names, and use that to generate a list of world cities.
    	 * Then we'll pick one of those at random and put it in the &quot;location&quot; field for the post.
	     * We'll filter out some entries from the list of timezone names which really aren't physical locations. 
    	 */

	    $cities = [];
    	$zones = timezone_identifiers_list();
	    foreach ($zones as $zone) {
    	    if ((strpos($zone,'/')) &amp;&amp; (! stristr($zone,'US/')) &amp;&amp; (! stristr($zone,'Etc/'))) {
        	    $cities[] = str_replace('_', ' ',substr($zone,strrpos($zone,'/') + 1));
			}
	    }

    	if (! count($cities)) {
        	return;
		}
		
		// select one at random and store it in $item['location']
    	$item['location'] = $cities[array_rand($cities,1)];

	    return;
	}
[/code]

Now let's create our webpage. This simply describes our app and indicates whether or not it is installed.
If it is installed, the addon will do its prescribed work. 
[code]
<?php
/* With rare exception, controllers use the 'Zotlabs\Module' namespace and extend the Zotlabs\Web\Controller class */
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

/* Autoload the Apps code */
use Zotlabs\Lib\Apps;

class Randplace extends Controller {

	function get() {

	    if (! local_channel()) {
	        return;
		}

		/* We are also going to create an 'app'. If it has not yet been installed, visiting https://{yoursite}/randplace should return a description
		 * of the app. t is a translation function. It is passed English text and returns text in the browser language (if available).  
		 */

		$desc = t('This app (if installed) provides a random post location on your submitted posts, taken from a list of world cities');

		if (! Apps::addon_app_installed(local_channel(), 'randplace')) {
			return $desc . '&lt;br&gt;&lt;br&gt;' . t('This app is not currently installed');

		return $desc . '&lt;br&gt;&lt;br&gt;' . t('This app has been installed.');
	}
}
[/code]
   

We need one more thing to make this work, and that is an 'app description file', which allows the system to discover that this code represents an installable
app. This file needs to be located in the addon directory and be named the same as the addon, with an extension of '.apd' (App Description).

So with a text editor, create

addon/randplace/randplace.apd

and inside it, put the following:

[code]
url: $baseurl/randplace
name: Randplace
photo: icon:globe
version: 1
requires: local_channel
[/code]

In this case we will use an icon from the Fork-Awesome icon set named 'globe'. You may also provide an absolute URL to an image file. This app will only be visible if the observer is logged in (requires: local_channel) and is now complete. If you visit the admin/addons page (as the site administrator) you will discover the randplace addon and will be able to install it. Your site members will then be able to see the 'Randplace' addon app in the apps/available page and will be able to install it for their channel. Once they install it and create a post, the post should appear as originating from a random city in the world.

You ***may*** wish for the name you have chosen inside the app description file to have the ability to be translated into other languages. You can do this by providing the following line inside your addon file:

[code]
$tmp = t('Randplace');
[/code]

The location of this code is not important. The 't' function will be found and indexed the next time the list of text strings is generated (via util/run_xgettext.sh). 


[h3]Using class methods as hook handler functions[/h3]

To register a hook using a class method as a callback, a couple of things need to be considered. The first is that the functions need to be declared static public so that they are available from all contexts, and they need to have a namespace attached because they can be called from within multiple namespaces. You can then register them as strings or arrays (using the PHP internal calling method). 

[code]
<?php
/*
 * plugin info block goes here
 */

function myplugin_load() {
	Zotlabs\Extend\Hook::register('hook_name','addon/myplugin/myplugin.php','\\Myplugin::foo');
	/* The next line is identical in how it behaves, but uses a slightly different method */
	Zotlabs\Extend\Hook::register('hook_name','addon/myplugin/myplugin.php', [ '\\Myplugin', 'foo' ]);
}
 
class Myplugin {

	public static function foo($params) {
		/* handler for 'hook_name' */
	}
}
[/code]

