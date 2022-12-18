# AllScan
AllStar Favorites Management &amp; Scanning Web App

See [screenshot.png](https://github.com/davidgsd/AllScan/blob/main/screenshot.png) for an example of the AllScan GUI. AllScan supports the following features:

1. Allow Favorites to be added/deleted simply by entering the node# and clicking an 'Add Favorite' button. The favorites.ini file is then updated with the node# and name, description and location data from the ASL DB.

2. Show all Favorites in a table on the main page, allow the connected node to be changed to a new favorite (automatically disconnecting any currently connected node) with only a single click on the GUI.

3. Allow favorites to be sorted by node#, name, description, location, etc.

4. Allow favorites to be easily edited, deleted, and copied/pasted.

5. Allow 'Scanning' of Favorites and other nodes by leveraging the ASL database and info such as Last Keyed and Connected Nodes data. The Scan feature will be able to show what favorites are active or were recently active, and will allow connecting to favorites one at a time and if no activity after a configurable time then disconnecting and moving to the next favorite.

These features will essentially give ASL the same memory management and scan capabilities that analog radios have had for decades. AllScan follows the latest web development standards and best-practices, with PHP, JavaScript, HTML, and CSS cleanly partitioned. AllScan is mobile-friendly and optimized for ease of use on both small and large screens. AllScan is intended to work well in both PC browsers and mobile browsers, using the same UI for both.

Currently AllScan is Beta software. It does the basics of showing detailed node status, supporting connect, disconnect and other common commands, supporting one-click add/delete of favorites to favorites.ini, and showing the Favorites list and allowing it to be sorted by any column.

Prior to installing AllScan it is highly recommended that you have a working install of AllMon2 or SuperMon. AllScan will then automatically read the AM/SM config files and need no additional configuration. The AllMon/Supermon directory is set in include/common.php in the API_DIR define and can be changed to any location. Because AllMon does not have a favorites.ini file however you would want to place a blank favorites.ini file in the allmon directory if you use AllMon2. (AllMon's controlpanel.ini.txt/php file could also be used and may be supported at some point. That file seems equivalent to favorites.ini with exception that it uses "labels[]/cmds[]" instead of "label[]/cmd[]" entries.)

Lots of updates and additions are still in the works but the existing features are already very useful. AllScan is a very easy-to-use app that anyone can download, unzip and be up and running within minutes. If you've ever used AllMon or Supermon you'll have the GUI figured out in seconds.

Currently AllScan supports favorites.ini entries that refer to connecting nodes eg. 'cmd[] = "rpt cmd %node% ilink 3 [node#]"' but will also support other types of commands at some point. The code also saves a backup copy to favorites.ini.bak in case of any issue. New features will be prioritized based on requests so let me know what you'd like to see.

# Install
The first step is to make sure you have AllMon2 or Supermon installed, properly configured and working. If you have Supermon already working, AllScan will then work right away with no configuration changes needed, and will use the favorites.ini file in the /var/www/html/supermon/ directory. See [supermon-install.txt](https://github.com/davidgsd/AllScan/blob/main/supermon-install.txt) or the Supermon groups.io page for details on how to install Supermon.

Make sure you have your node information defined in /var/www/html/supermon/allmon.ini and global.inc (specifically your node numbers in allmon.ini, and $CALL, $LOCATION, and $TITLE2 (eg. "Node 56789") in global.inc). AllScan uses those same settings. Also make sure you are able to properly execute various functions in Supermon such as connecting and disconnecting remote nodes. AllScan will soon support automatically using the AllMon2 files if Supermon is not installed/configured, but Supermon is very easy to set up and has some nice maintenance/debug features.

You will need SSH access to your node and should have basic familiarity with Linux. This has been tested on AllStarLink nodes with Supermon 7, and may not work as well with hamvoip or allmon.

Once you are logged in by SSH to your node run the following commands:

	cd /var/www/html
	sudo mkdir allscan; cd allscan
	wget https://github.com/davidgsd/AllScan/archive/refs/heads/main.zip
	unzip main.zip; mv AllScan-main/* .
	rm main.zip; rmdir AllScan-main

Now open a browser and go to your node's IP address followed by /allscan/ eg. `http://192.168.1.23/allscan/`, and bookmark that URL. You should see a Connection Status table showing your Node number(s), Call Sign and Location, a control form where you can enter node numbers and use the Connect, Disconnect, etc. buttons, and a Favorites table with at least a few favorites listed. If any of these are not showing check your allmon.ini and global.inc files in /var/www/html/supermon/ and make sure they are properly configured.

# Update
AllScan has no configuration files in its own directory currently thus the update process is similar to the install process with exception that you don't need to create the allscan directory and should delete all files in the directory prior to downloading the update. To update AllScan log into your node with SSH and run the following commands:

	cd /var/www/html/allscan
	rm -rf *
	wget https://github.com/davidgsd/AllScan/archive/refs/heads/main.zip
	unzip main.zip; mv AllScan-main/* .
	rm main.zip; rmdir AllScan-main

Now open a browser and go to your node's IP address followed by /allscan/, and force a full reload by pressing Shift-[F5], or in mobile browsers do a long-press of the reload button. This will ensure your browser also reloads the JavaScript and CSS files.

# Notes
SECURITY NOTE: User login support has not yet been implemented. If your node webserver is PUBLICLY accessible you should set up password protection on the /allscan/ directory. If you do not know how to do that, it is NOT recommended that you install AllScan at this time. In the next few weeks a login system will be implemented in AllScan, but currently anyone who has access to your node's IP address will have access to the /allscan/ directory (if they know to check that specific url). If you are using your node only on your local home network and do not have an external port mapped in your internet router to port 80 on your node then having a login and password is generally not necessary, but can still be enabled easily with a few simple steps to enable Apache directory authentication, such as described in this [article](https://www.digitalocean.com/community/tutorials/how-to-set-up-password-authentication-with-apache-on-ubuntu-20-04)

Also note: I have not yet tested AllScan on a node with EchoLink enabled but that will soon be fully supported.

# Troubleshooting / FAQs
If you get a permissions error when trying to Add a Favorite in AllScan, check that the /var/www/html/supermon dir has 775 permissions and www-data group, and that the /var/www/html/supermon/favorites.ini file exists and has 664 permissions and www-data as the group. These settings should already be that way if your Supermon install is properly working, otherwise it would not be able to edit and save the favorites.ini file. As a test you can go into Supermon, click the Configuration Editor button and try adding a blank line to favorites.ini and see if it saves OK. If not, there was something off with the Supermon install. In that case you might want to check on the Supermon groups.io page to let them know, or just run the following commands to correct those settings:

	cd /var/www/html/supermon/
	sudo touch favorites.ini
	sudo chmod 664 favorites.ini
	sudo chmod 775 .
	sudo chgrp www-data favorites.ini .
	
# Contact
If you have any questions email chc_media at yahoo dot com. 73, NR9V

# Road Map
1. Implement Cfg Mgr system that will autodetect Allmon2/Supermon config files if present and properly configured or otherwise use an independent allscan.ini file, validate settings and enable user to enter and edit all needed config settings on the web GUI. Estimated completion: Dec. 20
2. Add support for reading Last Heard, Last Keyed, and connected node count data from the ASL APIs and showing this information in the favorites table. Estimated completion: Dec. 30
3. Add support for EchoLink nodes. Estimated completion: Jan. 7
4. Implement user authentication system supporting management of multiple users with Read-only, Limited, or Full permissions. This will use cookies rather than php sessions in order to provide better control over login durations and support "Remember Me" option to prevent frequent re-logging in. Estimated completion: Jan. 14
5. Support favorites scanning, detecting of activity on a node and disconnect then connect to next node after a configurable inactivity delay. Estimated completion: Jan. 28

# Release Notes
**v0.22 2022-12-17**

CSS optimizations. Add Asterisk Restart button. Improvements to log messages.

**v0.21 2022-12-16**

Support Disconnect before Connect feature. This sends AMI an 'rpt cmd ilink 6' (Disconnect all links) command and waits 500mS before executing a Connect request, if 'Disconnect before Connect' checkbox is checked (default val = checked) and nodes are connected. To have the default for this checkbox be unchecked, set a url parm of autodisc=0. Disable PHP notices. Change Node text input field to a number field, and make the font size larger to make use of the field more mobile-friendly. Check for online/offline JS events.

**v0.2 2022-12-15**

Add Asterisk API. Code refactoring. Add Message Stats div, set up JS functions to output detailed status and error messages during all event processing. Add info links and CPU temp display.

**v0.15 2022-12-14**

Enable sortable columns on Favorites Table. GUI Updates.

**v0.1 2022-12-13**

Initial Commit.
