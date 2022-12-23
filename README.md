# AllScan
AllStar Favorites Management &amp; Scanning Web App

See [screenshot.png](https://github.com/davidgsd/AllScan/blob/main/screenshot.png) for an example of the AllScan GUI. AllScan is a free and open-source web app that provides Favorites Management features, AllStarLink Statistics integration, and connection monitoring and control functions.
* Favorites can be added/deleted simply by entering the node# and clicking a button. The favorites.ini file is then updated with the Node#, Name, Description and Location data from the ASL DB.
* Shows your favorites in a table and allows favorites to be connected with a single click (optionally automatically disconnecting any currently connected nodes first).
* Allows the Favorites Table to be sorted by Node#, Name, Description, Location, etc.
* Continually scans the status of each favorite using ASL's stats API data including Keyed status, Connected Node count, TxKeyed time, UpTime, and derived metrics, and shows what favorites are active and have recently been active.

These features now finally enable AllStar nodes to have similar memory management and scan capabilities that analog radios have had for decades. AllScan is mobile-friendly and optimized for ease of use on both small and large screens. AllScan follows the latest web development standards and best-practices, with PHP, JavaScript, HTML, and CSS cleanly partitioned. AllScan is mobile-friendly and optimized for ease-of-use on both small and large screens.

AllScan supports multiple locations of the favorites.ini file, giving priority to the allscan folder or secondarily using the ../supermon folder. Multiple favorites files can be used and easily switched between. If no favorites.ini file is found AllScan will ask if you'd like to create the file and if so will copy the docs/favorites.ini.sample file to ./favorites.ini, which has a small list of nodes to get you started. (AllMon's controlpanel.ini file may also be supported at some point.)

Prior to installing AllScan it is recommended that you have a working install of SuperMon or AllMon2. AllScan can automatically read their config files and thereby need no additional configuration. AllScan is very easy-to-use and can be downloaded and installed in minutes. Currently AllScan supports favorites.ini entries that refer to connecting to nodes eg. 'cmd[] = "rpt cmd %node% ilink 3 [node#]"' but may also support other types of commands in the future. AllScan saves a backup copy to favorites.ini.bak in case of any issue.

As AllScan receives data from the ASL stats server it updates the Favorites Table rows with color coded details showing the following:

Color codes for '#' column:
* Dark Green: Node Active (registered and reporting to AllStarLink Network)
* Medium Green: Node Active, Web-Transceiver enabled (may be more likely to accept connections)
* Red: Node is Keyed (transmitting audio to the AllStarLink Network)

(Note: The ASL stats data is not always accurate. Some active keyed nodes may not show as Keyed. This is not an issue in AllScan. The remote node may not be reporting that information or may only report it at certain intervals. It may be possible in a future release to get the keyed status more reliably from another ASL stats API/page, or to connect to nodes in Local Monitor mode.)

'Rx%' column: The remote node's reported TxTime divided by its Uptime, provides a general indication of how busy the node tends to be.

'LCnt' column: The reported number of Connected Links (ie. user nodes, hubs, bridges, or other links).

ASL's stats APIs are limited to 30 requests/minute per IP Address. AllScan uses a dynamic request timing algorithm to prevent exceeding this limit, even if multiple web clients are using AllScan on a node.

# Install
Ideally you should be using a recent (2021 or later) 2.0 Beta version of the ASL Distribution (available [here](http://downloads.allstarlink.org/index.php?b=ASL_Images_Beta)), and you should have AllMon2 or Supermon properly configured and working. Most testing has been done on ASL 2.0 but AllScan has been confirmed to be fully working on HamVOIP and pre-2.0 ASL releases. If you have Supermon already working, AllScan will need no additional configuration and will use the favorites.ini file in the supermon directory. See [supermon-install.txt](https://github.com/davidgsd/AllScan/blob/main/docs/supermon-install.txt) or the Supermon groups.io page for details on how to install Supermon. Confirm you are able to properly execute various functions in Supermon such as connecting and disconnecting remote nodes. Supermon is easy to set up and has some nice maintenance/debug features. For example it allows you to edit the text in the favorites.ini file in your browser, so for example you could add notes there of weekly Net times. AllScan will use the ../supermon/global.inc file automatically if global.inc is not present in the allscan folder. If you do not have Supermon installed or its global.inc file cannot be read AllScan will prompt you to enter your Call, Location and Node Title and save to global.inc.

You will need SSH access to your node and should have basic familiarity with Linux. Log into your node with SSH and run the following commands*:

	sudo su # if you are not already logged in as root user
	cd /var/www/html

	mkdir allscan; chmod 775 allscan; chgrp www-data allscan

	wget https://github.com/davidgsd/AllScan/archive/refs/heads/main.zip
	unzip main.zip; rm main.zip
	cp -rf AllScan-main/* allscan/
	rm -rf AllScan-main

	# Below needed only if you do not have php-curl installed and get ASL stats errors
	apt-get install php-curl; service apache2 restart

Now open a browser and go to your node's IP address followed by /allscan/ eg. `http://192.168.1.23/allscan/`, and bookmark that URL. You should see a Connection Status table showing your Node number(s), Call Sign and Location, a control form where you can enter node numbers and use the Connect, Disconnect, etc. buttons, and a Favorites table with at least a few favorites listed.

*Note for HamVOIP nodes: The web server folder may be /srv/http/ instead of /var/www/html/, and the web server group name may be http instead of www-data.

# Update
The update process is similar to the install process with exception that you don't need to create the allscan directory and should make a backup copy of it. Log into your node with SSH and run the following commands*:

	sudo su # if you are not already logged in as root user
	cd /var/www/html

	# If the allscan dir does not have 775 permissions or the web server group name run the following
	chmod 775 allscan; chgrp www-data allscan

	# Make a backup copy of your existing allscan folder (optional but recommended)
	cp -a allscan allscan-old

	wget https://github.com/davidgsd/AllScan/archive/refs/heads/main.zip
	unzip main.zip; rm main.zip
	cp -rf AllScan-main/* allscan/
	rm -rf AllScan-main

	# Below needed only if you do not have php-curl installed and get ASL stats errors
	apt-get install php-curl; service apache2 restart

Now open a browser and go to your node's IP address followed by /allscan/, and **be sure to force a full reload by pressing CTRL-[F5] or clearing your browser cache, or in mobile browsers do a long-press of the reload button**, so your browser will load the updated JavaScript and CSS files.

*Note for HamVOIP nodes: The web server folder may be /srv/http/ instead of /var/www/html/, and the web server group name may be http instead of www-data.

# Notes
SECURITY NOTE: User login support has not yet been implemented. If your node webserver is publicly accessible you might want to set up password protection on the /allscan/ directory. If you don't, someone could potentially edit your favorites or connect/disconnect remote nodes, though that's about all they could do. In the next couple weeks a login system will be implemented in AllScan. If you're using your node only on your local home network and do not have an external port mapped in your internet router to port 80 on your node then having a login and password is generally not necessary, but can still be enabled easily with a few simple steps such as described in this [article](https://www.digitalocean.com/community/tutorials/how-to-set-up-password-authentication-with-apache-on-ubuntu-20-04).

# Node Notes
If you do not have a node or if your node is out-of-date, noisy, or unreliable, check out the following post: [How To Build a High-Quality Full-Duplex AllStar Node for Under $200](https://forums.qrz.com/index.php?threads/how-to-build-a-professional-grade-full-duplex-allstar-node-for-under-200.839842/).

# Troubleshooting / FAQs
If you get a permissions error when trying to Add a Favorite in AllScan, check that the /var/www/html/allscan and supermon dirs have 775 permissions and www-data group, and that the favorites.ini file exists in one or both of directories and has 664 permissions and www-data as the group. These settings should already be that way if your Supermon install is properly working, or it would not be able to edit and save the favorites.ini file. As a test you can go into Supermon, click the Configuration Editor button and try adding a blank line to favorites.ini and see if it saves OK. If not, there is something improperly configured with the Supermon install. The following commands should correct those settings:

	cd /var/www/html/supermon/
	sudo touch favorites.ini
	sudo chmod 664 favorites.ini
	sudo chmod 775 .
	sudo chgrp www-data favorites.ini .

If you keep your favorites.ini file in the allscan directory and see error messages when writing the file from allscan, run the same steps as above but in the /var/www/html/allscan/ folder.

# Contact
If you have any questions email chc_media at yahoo dot com. Also see [AllScan.info](https://AllScan.info). 73, NR9V

# Road Map
1. Additional refinements to existing features
2. Implement user authentication system supporting multiple users with Read-only, Limited, or Full permissions and full control over login durations. Estimated completion: Jan. 5
3. Other features that are highly requested or that seem like a good fit
4. Additional stats/scan features

# Release Notes
**v0.37 2022-12-23**<br>
Support 7-digit (EchoLink) node numbers when reading in favorites.ini. Fix issue where stats request scanning would stop once it reached an EchoLink node number in the Favorites Table.

**v0.36 2022-12-22**<br>
Properly handle case of invalid node number in the favorites file. Download ASTDB file if not found in allscan, allmon or supermon locations. Reload page on event-stream error if location.href is available. Update install/update notes.

**v0.35 2022-12-21**<br>
Optimize stats request timing to more quickly populate the favorites table after page load, then go to a reduced request rate over time, to reduce the chance of the ASL stats request limit (30 per minute) being exceeded if there are multiple AllScan web clients on a node. Link Favorites table Names text to the ASL stats page. Update JS reload function to prevent POST data being resubmitted after page reload. Minor optimizations.

**v0.33 2022-12-20**<br>
Add default global.inc file docs/global.inc.sample and give user option to configure and save this to ./global.inc if file was not found in . or ../supermon/. Documentation updates. GUI optimizations. Add default favorites file docs/favorites.ini.sample and give user option to copy this to ./favorites.ini if file was not found in . or ../supermon/. Use PHP cURL lib if present for ASL Stats requests.

**v0.3 2022-12-19**<br>
Implement ASL Stats functions, color coding of Favorites Table and new 'Rx%' and 'LCnt' columns. Improve handling of page reload logic after browser JS online event when node is not accessible. Enable automatic reading of astdb.txt file from allscan's directory or from ../supermon/ or /var/log/asterisk/. Enable automatic reading of allmon.ini file from allscan's directory or from /etc/asterisk/, ../supermon/, or ../allmon/allmon.ini.php. Show detailed messages on any issues found when trying to read various files.

**v0.23 2022-12-18**<br>
When JS online event is received, reload page after 2 Sec delay, to automatically restart server event-stream connection after PC/browser was asleep or offline. Add print of astdb.txt file Last Update times.

**v0.22 2022-12-17**<br>
CSS optimizations. Add Asterisk Restart button. Improvements to log messages.

**v0.21 2022-12-16**<br>
Support Disconnect before Connect feature. This sends AMI an 'rpt cmd ilink 6' (Disconnect all links) command and waits 500mS before executing a Connect request, if 'Disconnect before Connect' checkbox is checked (default val = checked) and nodes are connected. To have the default for this checkbox be unchecked, set a url parm of autodisc=0.

**v0.2 2022-12-15**<br>
Add Asterisk API. Code refactoring. Add Message Stats div, set up JS functions to output detailed status and error messages during all event processing. Add info links and CPU temp display.

**v0.15 2022-12-14**<br>
Enable sortable columns on Favorites Table. GUI Updates.

**v0.1 2022-12-13**<br>
Initial Commit.

# Thanks
Thanks to all ASL Developers. Thanks to KK6QMS for help in Beta testing, and N6ATC for help in verifying AllScan works on HamVOIP.
