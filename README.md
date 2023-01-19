# AllScan
AllStar Favorites Management &amp; Scanning Web App

See [screenshot.png](https://github.com/davidgsd/AllScan/blob/main/screenshot.png) for an example of the AllScan GUI. AllScan is a free and open-source web app that provides Favorites Management features, AllStarLink Stats integration, and connection monitoring and control functions.
* Favorites can be added/deleted simply by entering the node# and clicking a button. The favorites.ini file is then updated with the Node#, Name, Description and Location data from the ASL database.
* Shows your favorites in a table and allows favorites to be connected with a single click (optionally automatically disconnecting any currently connected nodes first). Allows the Favorites Table to be sorted by Node#, Name, Description, Location, etc.
* Continually scans the status of each favorite using ASL's Stats API data including Keyed status, Connected Node count, TxKeyed time, UpTime, and derived metrics, showing which favorites are active and have recently been active.

These features finally give AllStar nodes similar memory management and scan capabilities that analog radios have had for decades. AllScan is mobile-friendly and optimized for ease of use on both small and large screens. AllScan follows the latest web development standards, with PHP, JavaScript, HTML, and CSS cleanly partitioned, runs on both ASL and HamVOIP, and is very easy to install, configure, and update.

AllScan supports multiple locations of the favorites.ini file. If no favorites.ini file is found AllScan will ask if you'd like to create the file and if so will copy the docs/favorites.ini.sample file to ./favorites.ini, which has a small list of nodes to get you started. (AllMon's controlpanel.ini file may also be supported at some point.)

Prior to installing AllScan it is recommended that you have a working install of SuperMon or AllMon2. AllScan can automatically read their config files and thereby need no additional configuration. AllScan is very easy-to-use and can be downloaded and installed in minutes. Currently AllScan supports favorites.ini entries that refer to connecting to nodes eg. 'cmd[] = "rpt cmd %node% ilink 3 [node#]"' but may also support other types of commands in the future. AllScan saves a backup copy to favorites.ini.bak in case of any issue.

As AllScan receives data from the ASL stats server it updates the Favorites Table rows with color coded details showing the following:

Color codes for '#' column:
* Dark Green: Node Active (registered and reporting to AllStarLink Network)
* Medium Green: Node Active, Web-Transceiver enabled (may be more likely to accept connections)
* Red: Node is Keyed (transmitting audio)

(Note: The ASL stats data is not always accurate. Some active keyed nodes may not show as Keyed. This is not an issue in AllScan. The remote node may not be reporting that information or may only report it at certain intervals. Future releases may be able to get the keyed status more reliably using other ASL stats APIs/pages or other mechanisms.)

'Rx%' column: The remote node's reported TxTime divided by its Uptime, provides a general indication of how busy the node tends to be.

'LCnt' column: The reported number of Connected Links (ie. user nodes, hubs, bridges, or other links).

ASL's stats APIs are limited to 30 requests/minute per IP Address. AllScan uses a dynamic request timing algorithm to prevent exceeding this limit, even if multiple web clients are using AllScan on a node.

As of v0.45 AllScan implements User Authentication, User Account Administration, Login/Logout, User Settings and Cfg Management functions. After installing or upgrading from pre-v0.45, AllScan will automatically create its Database and necessary tables, and when you first visit the allscan/ url will prompt you to create an Admin user account. By default, public (not logged-in) users will have Read-Only access and will be able to see the Connection Status and Favorites data, but will not be able to make changes or view any admin (Cfgs / Users) pages. To change this setting, Log in, click the "Cfgs" link, and edit the "Public Permission" parameter.

Additional screenshots:
[init.png](https://github.com/davidgsd/AllScan/blob/main/docs/screenshots/init.png)
[cfgs.png](https://github.com/davidgsd/AllScan/blob/main/docs/screenshots/cfgs.png)
[users.png](https://github.com/davidgsd/AllScan/blob/main/docs/screenshots/users.png)
[settings.png](https://github.com/davidgsd/AllScan/blob/main/docs/screenshots/settings.png)

# Pre-Install Notes
Ideally you should be using a recent (2021 or later) 2.0 Beta version of the ASL Distribution (available [here](http://downloads.allstarlink.org/index.php?b=ASL_Images_Beta)), and you should have AllMon2 or Supermon properly configured and working. AllScan works fine on HamVOIP and pre-2.0 ASL releases but ASL 2.0 is the latest open-source standard and thus will have better support.

If you have Supermon already working, AllScan will need no additional configuration and will use the favorites.ini file in the supermon directory. See [supermon-install.txt](https://github.com/davidgsd/AllScan/blob/main/docs/supermon-install.txt) or the Supermon groups.io page for details on how to install Supermon. Confirm you are able to properly execute various functions in Supermon such as connecting and disconnecting remote nodes. Supermon is easy to set up and has some nice maintenance/debug features. For example it allows you to edit the text in the favorites.ini file in your browser, so for example you could add notes there of weekly Net times.

If you use Supermon2 instead of Supermon or want to put your favorites.ini file in some other folder, the favorites.ini search location(s) can be set on the AllScan Cfgs Page.

# Automatic Install / Update
The AllScan Install/Update script automatically checks all system configuration details, changes to the web server root folder, checks if AllScan is already Installed and if so what version, and if not installed or a newer version is available will prompt you to continue with the Install/Update. Just enter 'y' and seconds later the install/update will be complete. If you prefer to install/update manually see the [Manual Install / Update Instructions](https://github.com/davidgsd/AllScan/blob/main/docs/manualInstallUpdate.md).

Log into your node by SSH and run the following commands:

	cd ~
	sudo rm AllScanInstallUpdate.php
	wget 'https://raw.githubusercontent.com/davidgsd/AllScan/main/AllScanInstallUpdate.php'
	chmod 755 AllScanInstallUpdate.php
	sudo ./AllScanInstallUpdate.php

The Install/Update script will provide detailed status messages on each step of the process. Once the update/install is complete it is recommended to delete the script ("rm AllScanInstallUpdate.php"). Then the next time you want to update just run the above commands again.

Now open a browser and go to your node's IP address followed by /allscan/, eg. `http://192.168.1.23/allscan/` and be sure to add a bookmark in your browser.

If you did a new install or upgraded from pre-v0.45, AllScan will prompt you to create an admin account. Be sure to do this right after installing/upgrading. You can then configure the permission settings for AllScan. These default to Read-Only for public (not logged-in) users. This setting can be changed on the "Cfgs" page.

If you did an update, **be sure to force a browser reload by pressing CTRL-[F5] or clearing your browser cache, or in mobile browsers do a long-press of the reload button**, so your browser will load the updated JavaScript and CSS files.

NOTES for HamVOIP only:
1. You may need to uncomment/add the following lines in /etc/php/php.ini (make sure they do not have a ';' in front)<br>
	extension=pdo_sqlite.so<br>
	extension=sqlite3.so
2. Then restart Lighttpd web server or restart the node

# Node Notes
If you do not yet have a node or might like to upgrade your node, check out the following article by AllScan's author NR9V: [How To Build a High-Quality Full-Duplex AllStar Node for Under $200](https://allscan.info/docs/diy-node.php).

# Configuration Files and Parameters
Most nodes already have a number of Cfg files and to simplify the install process AllScan will try to use these rather than require redundant files/data to be created/entered. These files are as follows:
1. **global.inc**: Cfg file in the supermon directory with user settings such as your name, call sign, node title, etc. AllScan will automatically import the following variables from global.inc if found: $CALL, $LOCATION, and $TITLE2. Otherwise, go to the AllScan Cfgs Page and enter your Call Sign, Location and Node Title parameters there. Once these parms have been imported or set AllScan will not read from the global.inc file again. The Call Sign and Location parameters are shown in the AllScan Page Header, and the Node Title parameter is shown in the Connection Status Table header.
2. **astdb.txt**: The ASL database file with the list of all nodes provisioned on the AllStarLink network. This file should already exist in ../supermon/astdb.txt or /var/log/asterisk/astdb.txt. If the file is not found it will be automatically downloaded into the allscan directory. If you have a properly configured node you should have a cron entry that downloads the latest astdb file at least once per week. AllScan shows the status of the above files and their last modification time in the status messages box (below the Favorites Table). If you see there is no recent astdb file (less than 1 week old) you should review your cron settings (which should have been configured when you installed ASL/AllMon and Supermon).
3. **allmon.ini**: This defines your node number(s) and the Asterisk Manager credentials. It can usually be found in any of the following locations: ../supermon/allmon.ini, /etc/asterisk/allmon.ini.php or ../allmon2/allmon.ini.php. AllScan will search those locations in that order and use the first file found. If you see connection/stats error messages check those file locations and verify they have the correct data. If you have multiple Nodes defined in allmon.ini, AllScan will use the first Node# in the file. (A future version of AllScan may support multiple Node#s and allow these cfgs to be stored in AllScan's database.)
4. **favorites.ini**: The favorites file can be found in the supermon directory or in the allscan directory. If not found in ../supermon/ you will be prompted to create a new favorites.ini file in the allscan directory. A future version of AllScan will allow you to specify the location of the favorites file or if AllScan's database should be used instead.

All AllScan Cfg parameters can be viewed and set on the Cfgs page if you are logged in as an Admin user. Just click the 'Cfgs' link and all Cfgs are then shown along with an Edit form.

# Troubleshooting / FAQs
For any issues including directory/file permissions issues or issues with SQLite not being available it is recommended to first always run the update script. The script will check if you have the latest version of AllScan and update your install if not, and will validate all directory and file permissions and update/upgrade any out-of-date OS packages. Refer to the "Automatic Install / Update" section above and run the update script and then see if the issue was resolved.

HamVOIP users: See this [Blog Post by KJ7T](https://randomwire.substack.com/p/updating-allscan-on-the-clearnode) for detailed steps on how to enable the SQLite3 extension in php.ini.

If you get a permissions error when trying to Add a Favorite, check that the /var/www/html/allscan and supermon dirs have 775 permissions and www-data group, and that the favorites.ini file exists in one or both directories and has 664 permissions and www-data as the group. These settings should already be that way if your Supermon install is properly working, or it would not be able to edit and save the favorites.ini file. As a test you can go into Supermon, click the Configuration Editor button and try adding a blank line to favorites.ini and see if it saves OK. If not, there is something improperly configured with the Supermon install. The following commands should correct those settings:

	cd /var/www/html || cd /srv/http # Change to www root folder (works on ASL and HamVOIP)
	cd supermon
	sudo touch favorites.ini favorites.ini.bak
	sudo chmod 664 favorites.ini favorites.ini.bak
	sudo chmod 775 .
	# For ASL set group to www-data
	sudo chgrp www-data favorites.ini favorites.ini.bak .
	# else for HamVOIP set group to http
	# sudo chgrp http favorites.ini favorites.ini.bak .

If you are still unable to get things working after trying the above, email me at the contact info below and provide as much detail as possible on the issue you see along with the following info:
1. All messages shown when you run the install/update script.
2. Directory listing of the web root folder and the allscan folder. Do this by running "cd /var/www/html; ls -la . allscan" (or for HamVOIP "cd /srv/http; ls -la . allscan").

# Contact
If you have any questions email chc_media at yahoo dot com. Also see [AllScan.info](https://AllScan.info). 73, NR9V

# Donations
I have received many Thank You's and offers for a cup of coffee or other small donation, which are much appreciated, initially though I would ask that any contributions be directed to AllStarLink.org, who have put in many years of work maintaining the free & open-source ASL ecosystem, and who have a lot of overhead expenses. See [this link](https://donorbox.org/allstarlink-donations?amount=24&default_interval=a&utm_campaign=donate_4&utm_source=allscan) to donate to Allstarlink Inc. If in addition to supporting ASL you did also want to contribute to AllScan feel free to send anything by paypal or venmo to chc_media at yahoo dot com. Even $1 does help cover web server expenses and enable me to spend more time on further development. Thank you for your support, and with helping spread the word about AllScan and ASL.

# Road Map
1. Additional refinements to existing features
2. Enhanced stats and scan features
3. Other features that are highly requested or that seem like a good fit

# Release Notes
**v0.52 2023-01-19**<br>
Minor bug fix: If after a new install or update an error was detected in dbInit(), an error would occur resulting in a blank page rather than normal page load and a useful error message being displayed. This update is not needed if your AllScan install is already working fine.

**v0.51 2023-01-18**<br>
Optimizations to Cfgs module. Add 'DiscBeforeConn Default' Cfg parameter which determines if the 'Disconnect Before Connect checkbox' is checked by default. To have the checkbox be Off by default, go to the Cfgs page and set 'DiscBeforeConn Default' to Off.

**v0.50 2023-01-17**<br>
Add 'Node Stats' button. Implement Call Sign, Location and Node Title Cfgs, these are automatically imported from global.inc as before but once imported they are now managed on the Cfgs page and global.inc will no longer be read (unless these cfgs are later deleted). Fix issue where API files would require a logged in user prior to checking the 'Public Permission' cfg, resulting in Connection Status data not being shown for non-logged in users.

**v0.49 2023-01-16**<br>
Add user authentication and permissions checks to all API files. Add DTMF command button. Updates and optimizations to installer/updater: Fix issue where updater would exit prior to completing all checks if install was up-to-date, provide more detail about all commands executed, prompt user before executing any apt-get/pacman update/upgrade actions.

**v0.48 2023-01-11**<br>
Implement User Authentication, User Admin, Login/Logout, User Settings and Cfg Management functions. Major refactoring and additions. AllScan now defaults public (not logged-in) users to Read-Only access. This can be changed to None (no public access), Read/Modify, or to Full (no logins needed). Upon install of this version, AllScan will automatically verify the system configuration, create its Database and necessary tables, and when you first visit the allscan url it will prompt you to create an Admin user account, with detailed usage notes. Add additional log messages to dbUtils checkTables(). Change default order of possible allmon.ini locations to look in ../supermon/ prior to /etc/asterisk/ as supermon may be more likely to have valid AMI credentials. Update install/update script to update & upgrade OS packages (fixes issue seen on RPi4 w/latest ASL 2.0 where failed to find SQLite php extension).

**v0.42 2023-01-08**<br>
JavaScript optimizations. Update page Title with node PTT/COS status to allow status to be seen when browser tab is not active. Initial checkin of functions supporting base configs, SQLite3 DB and config management, and user authentication and account management (these functions are not yet all integrated into the main controller file).

**v0.4 2023-01-02**<br>
Only show CPU Temp if data is available. Reduce favs table CSS cell padding from 4 to 3 px. Update InstallUpdate script to verify favorites.ini file in supermon dir is writeable by web server if dir exists. Readme updates. Minor optimizations. Add API to eventually support stats caching and additional stats features. Update CPU temp data once per minute.

**v0.38 2022-12-24**<br>
For EchoLink nodes don't link node Name text to ASL stats page. Revise Green/Yellow CPU Temp range threshold from 120 to 130 °F. Support 7-digit (EchoLink) node numbers when reading in favorites.ini. Fix issue where stats request scanning would stop once it reached an EchoLink node number in the Favorites Table. Properly handle case of invalid node number in favorites file. Download ASTDB file if not found in allscan, allmon or supermon locations. Reload page on event-stream error if location.href is available.

**v0.35 2022-12-21**<br>
Optimize stats request timing to more quickly populate the favorites table after page load, then go to a reduced request rate over time, to reduce the chance of the ASL stats request limit (30 per minute) being exceeded if there are multiple AllScan web clients on a node. Link Favorites table Names text to the ASL stats page. Update JS reload function to prevent POST data being resubmitted after page reload. Add default global.inc file docs/global.inc.sample and give user option to configure and save this to ./global.inc if file was not found in . or ../supermon/. Documentation updates. GUI optimizations. Add default favorites file docs/favorites.ini.sample and give user option to copy this to ./favorites.ini if file was not found in . or ../supermon/. Use PHP cURL lib if present for ASL Stats requests.

**v0.3 2022-12-19**<br>
Implement ASL Stats functions, color coding of Favorites Table and new 'Rx%' and 'LCnt' columns. Improve handling of page reload logic after browser JS online event when node is not accessible. Enable automatic reading of astdb.txt file from allscan's directory or from ../supermon/ or /var/log/asterisk/. Enable automatic reading of allmon.ini file from allscan's directory or from /etc/asterisk/, ../supermon/, or ../allmon/allmon.ini.php. Show detailed messages on any issues found when trying to read various files.

**v0.23 2022-12-18**<br>
When JS online event is received, reload page after 2 Sec delay, to automatically restart server event-stream connection after PC/browser was asleep or offline. Add print of astdb.txt file Last Update times. CSS optimizations. Add Asterisk Restart button. Improvements to log messages. Support Disconnect before Connect feature. This sends AMI an 'rpt cmd ilink 6' (Disconnect all links) command and waits 500mS before executing a Connect request, if 'Disconnect before Connect' checkbox is checked and any nodes are connected.

**v0.2 2022-12-15**<br>
Add Asterisk API. Code refactoring. Add Message Stats div, set up JS functions to output detailed status and error messages during all event processing. Add info links and CPU temp display. Enable sortable columns on Favorites Table. GUI Updates.

**v0.1 2022-12-13**<br>
Initial Commit.

# FAQs
Q: What is the blinking icon for?<br>
A: AllScan's blinking 'lighting bolt' icon is a status indicator similar the 'spinny' in supermon or the blinking asterisk ('*') in allmon, which toggles on/off as each Connection Status event message is received from the node (ie. from AllScan's astapi/server.php file who reads status info from a socket connection to Asterisk on the node and then forwards that data every 500mS to AllScan's JavaScript in the browser.) If it stops blinking that means there is a communication issue between the browser and your node.

Q: If I ever wanted to uninstall AllScan how can this be done?<br>
A: All you'd have to do is delete the allscan folder in the web server root directory &ndash; which on ASL is /var/www/html/ or on HamVOIP is /srv/http/. cd to that folder, then execute "sudo rm -rf allscan" to delete the AllScan folder. Or you could move the folder somewhere else, but it's so easy to install that you might as well just delete it and then if you want to install it again later just run the installer. AllScan also keeps a database file in /etc/allscan/, which isn't going to do anything by itself if allscan was deleted from the web server folder, but if you wanted to delete the db file also, execute "sudo rm -rf /etc/allscan".

# Thanks
Thanks to all ASL Developers. Thanks to KK6QMS, N6ATC, KJ7T and K5LK for help with Beta testing.
