# AllScan
AllStar Favorites Management &amp; Scanning Web App

See [screenshot.png](https://github.com/davidgsd/AllScan/blob/main/screenshot.png) for an example of the AllScan GUI. AllScan supports the following features:

1. Allow Favorites to be added/deleted simply by entering the node# and clicking an 'Add Favorite' button. The favorites.ini file is then updated with the node# and name, description and location data from the ASL DB. (This is a nice step up in user-friendliness vs. manually editing cfg files.)

2. Show all Favorites in a table on the main page, allow the connected node to be changed to a new favorite (automatically disconnecting any currently connected node) with only a single click needed on the GUI.

3. Allow the favorites list shown on the main page to be sorted by node#, name, description, frequency, location, etc.

4. Allow favorites to be easily edited, deleted, or copied/pasted on the main GUI page.

5. Allow 'Scanning' of Favorites and other nodes by leveraging the ASL database and info such as Last Keyed and Connected Nodes data. The Scan feature will be able to show what favorites are active or were recently active and will allow connecting to favorites one at a time and if no activity after a configurable time then disconnecting and moving to the next favorite.

The above features will essentially give ASL the same memory management and scan capabilities that analog FM radios have had for decades. This should be a natural evolution and nice improvement in core functionality over what AllMon2 or Supermon now provide.

All coding has been done to the latest web development standards and best-practices, with php, javascript, html, and css cleanly partitioned, and following an MVC architecture. AllScan is mobile-friendly and optimized for ease of use on both small and large screens.

With recent smartphones having large hi-res displays (relative to phones from 10 years ago) a lot of info can be shown and it's easy to zoom in and out and move the page around as needed. A challenge of good UI design is keeping the info on the page as concise, clear and intuitive (eg. using visual cues/color-coding) while still showing the more important info and minimizing the number of UI interactions needed to execute the most frequently used functions. There are tradeoffs and some people will prefer a more minimal UI with bigger text (but having to click around more to access other info or functions) whereas others will prefer a more detailed UI but maybe have to do a bit more zooming/dragging. These tradeoffs can be refined over time and settings options added to enable what info/columns are shown by default or are maybe shown in popup divs or detail links.

AllScan is intended to work well in both normal PC browsers and mobile browsers and in fact has the same UI for both. This is a somewhat less common UI design paradigm where many sites and apps design specifically for one or the other, assuming that mobile users only want a super-simple UI with huge text and buttons. But decent phones, tablets, etc. work fine with most regular ‘desktop' sites and many users would rather see all relevant info in one place rather than have to click lots of buttons and menus. Thus AllScan is more oriented toward desktop users and mobile users who tend to use their phone browsers more like a desktop browser.

Currently AllScan has just been released and is Beta software. It does the basics of showing node(s) status, allowing connects/disconnects, and supporting one-click add/delete of favorites to the favorites.ini file. It does this with a relatively small amount of code, leveraging an existing AllMon or Supermon install ie. server.php for the event-stream and connect.php. Those files haven't changed significantly in years and provide a nice Asterisk API. Requiring a working install of AM/SM also ensures that users will already have all that working, and AllScan is then essentially an extension needing very little if any configuration.

The AllMon/Supermon directory is set in include/common.php in the API_DIR define and can be changed to any location there. Because AllMon does not have a favorites.ini file however you would want to place a blank favorites.ini file in the allmon directory if you use AllMon2. AllMon's controlpanel.ini.txt/php file could also be used and may be supported at some point. That file seems equivalent to favorites.ini with exception that it uses "labels[]/cmds[]" instead of "label[]/cmd[]" entries.

Lots of updates are in the works but even just the favorites management features are already quite handy. I'll also be doing a youtube video soon with a quick demo and walkthrough of the app. But it's such a simple app that anyone can download it, unzip the files and be up and running within minutes, at which point if you've ever used AllMon/Supermon you'd have the GUI figured out in about 2 minutes.

Currently AllScan supports favorites.ini entries that refer to connecting nodes eg. 'cmd[] = "rpt cmd %node% ilink 3 [node#]"' but will also support other types of commands at some point. The code also saves a backup copy to favorites.ini.bak in case there is any issue. New features will be prioritized based on requests so let me know what you'd like to see.

# Install
AllScan uses the Asterisk API provided by AllMon2 or Supermon, so the first step is to make sure you have one of those installed, properly configured and working. If you have Supermon already working, AllScan will then work right away with no configuration changes or set up needed. See [supermon-install.txt](https://github.com/davidgsd/AllScan/blob/main/supermon-install.txt) or the Supermon groups.io page for details on how to install Supermon.

Make sure you have your node information defined in /var/www/html/supermon/allmon.ini and global.inc (specifically your node numbers in allmon.ini, and $CALL, $LOCATION, and $TITLE2 (eg. "Node 56789") in global.inc). AllScan uses those same settings. Also make sure you are able to properly execute various functions in Supermon such as connecting and disconnecting remote nodes. If for some reason you would rather not install Supermon let me know. I may at some point implement support for automatically using the AllMon2 files if Supermon is not installed or not working. But Supermon is easy to set up and has some nice maintenance/debug features.

You will need SSH access to your node and should have basic familiarity with Linux. This has been tested on AllStarLink nodes with Supermon 7, and may not work as well with hamvoip or allmon.

Once you are logged in by SSH to your node run the following commands:

	cd /var/www/html/

	mkdir allscan; cd allscan

	wget https://github.com/davidgsd/AllScan/archive/refs/heads/main.zip

	unzip main.zip; mv AllScan-main/* .

	rm main.zip; rmdir AllScan-main

(If you get any permissions errors writing to directories/files during the above steps, try switching to superuser by running "sudo su". This should not generally be necessary though.)

Now open a browser and go to your node's IP address followed by /allscan/ eg. `http://192.168.1.23/allscan/`
You should see a Connection Status table showing your Node number(s), Call Sign and Location, a control form where you can enter node numbers and use the Connect, Disconnect, etc. buttons, and a Favorites table with at least a few favorites listed. If any of these are not showing check your allmon.ini and global.inc files in /var/www/html/supermon/ and make sure they are properly configured.

# Notes
SECURITY NOTE: User login support has not yet been implemented. If your node webserver is PUBLICLY accessible you should set up password protection on the /allscan/ directory. If you do not know how to do that, it is NOT recommended that you install AllScan at this time. In the next few weeks a login system will be implemented in AllScan, but currently anyone who has access to your node's IP address will have access to the /allscan/ directory (if they know to check that specific url). If you are using your node only on your local home network and do not have an external port mapped in your internet router to port 80 on your node then having a login and password is generally not necessary, but can still be enabled easily with a few simple steps to enable Apache directory authentication, such as described in this [article](https://www.digitalocean.com/community/tutorials/how-to-set-up-password-authentication-with-apache-on-ubuntu-20-04)

Also note: I have not yet tested AllScan on a node with EchoLink enabled but that will soon be fully supported.

# Contact
If you have any questions email chc_media at yahoo dot com. 73, NR9V
