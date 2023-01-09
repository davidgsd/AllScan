# AllScan
AllStar Favorites Management &amp; Scanning Web App

See [screenshot.png](https://github.com/davidgsd/AllScan/blob/main/screenshot.png) for an example of the AllScan GUI. AllScan is a free and open-source web app that provides Favorites Management features, AllStarLink Stats integration, and connection monitoring and control functions.
* Favorites can be added/deleted simply by entering the node# and clicking a button. The favorites.ini file is then updated with the Node#, Name, Description and Location data from the ASL database.
* Shows your favorites in a table and allows favorites to be connected with a single click (optionally automatically disconnecting any currently connected nodes first). Allows the Favorites Table to be sorted by Node#, Name, Description, Location, etc.
* Continually scans the status of each favorite using ASL's Stats API data including Keyed status, Connected Node count, TxKeyed time, UpTime, and derived metrics, showing which favorites are active and have recently been active.

These features finally give AllStar nodes similar memory management and scan capabilities that analog radios have had for decades. AllScan is mobile-friendly and optimized for ease of use on both small and large screens. AllScan follows the latest web development standards, with PHP, JavaScript, HTML, and CSS cleanly partitioned, runs on both ASL and HamVOIP, and is very easy to install, configure, and update.

AllScan supports multiple locations of the favorites.ini file, giving priority to the allscan folder or secondarily using the ../supermon folder. Multiple favorites files can be used and easily switched between. If no favorites.ini file is found AllScan will ask if you'd like to create the file and if so will copy the docs/favorites.ini.sample file to ./favorites.ini, which has a small list of nodes to get you started. (AllMon's controlpanel.ini file may also be supported at some point.)

Prior to installing AllScan it is recommended that you have a working install of SuperMon or AllMon2. AllScan can automatically read their config files and thereby need no additional configuration. AllScan is very easy-to-use and can be downloaded and installed in minutes. Currently AllScan supports favorites.ini entries that refer to connecting to nodes eg. 'cmd[] = "rpt cmd %node% ilink 3 [node#]"' but may also support other types of commands in the future. AllScan saves a backup copy to favorites.ini.bak in case of any issue.

As AllScan receives data from the ASL stats server it updates the Favorites Table rows with color coded details showing the following:

Color codes for '#' column:
* Dark Green: Node Active (registered and reporting to AllStarLink Network)
* Medium Green: Node Active, Web-Transceiver enabled (may be more likely to accept connections)
* Red: Node is Keyed (transmitting audio to the AllStarLink Network)

(Note: The ASL stats data is not always accurate. Some active keyed nodes may not show as Keyed. This is not an issue in AllScan. The remote node may not be reporting that information or may only report it at certain intervals. Future releases may be able to get the keyed status more reliably using other ASL stats APIs/pages or other mechanisms.)

'Rx%' column: The remote node's reported TxTime divided by its Uptime, provides a general indication of how busy the node tends to be.

'LCnt' column: The reported number of Connected Links (ie. user nodes, hubs, bridges, or other links).

ASL's stats APIs are limited to 30 requests/minute per IP Address. AllScan uses a dynamic request timing algorithm to prevent exceeding this limit, even if multiple web clients are using AllScan on a node.

# Pre-Install Notes
Ideally you should be using a recent (2021 or later) 2.0 Beta version of the ASL Distribution (available [here](http://downloads.allstarlink.org/index.php?b=ASL_Images_Beta)), and you should have AllMon2 or Supermon properly configured and working. AllScan works fine on HamVOIP and pre-2.0 ASL releases but ASL 2.0 is the latest open-source standard and thus will have better support.

If you have Supermon already working, AllScan will need no additional configuration and will use the favorites.ini file in the supermon directory. See [supermon-install.txt](https://github.com/davidgsd/AllScan/blob/main/docs/supermon-install.txt) or the Supermon groups.io page for details on how to install Supermon. Confirm you are able to properly execute various functions in Supermon such as connecting and disconnecting remote nodes. Supermon is easy to set up and has some nice maintenance/debug features. For example it allows you to edit the text in the favorites.ini file in your browser, so for example you could add notes there of weekly Net times.

AllScan will use the ../supermon/global.inc file automatically if global.inc is not present in the allscan folder. If you do not have Supermon installed or its global.inc file cannot be read AllScan will prompt you to enter your Call, Location and Node Title and save to global.inc. 

If you use Supermon2 instead of Supermon, or want to put your global.inc or favorites.ini files in some other folder, you can make symbolic links to those locations. For example to use the supermon2 favorites.ini do the following: 1. "cd /var/www/html/allscan" 2. "ln -s ../supermon2/favorites.ini ."

# Automatic Install / Update
The AllScan Install/Update script automatically checks all system configuration details, changes to the web server root folder, checks if AllScan is already Installed and if so what version, and if not installed or a newer version is available will prompt you to continue with the Install/Update. Just enter 'y' and seconds later the install/update will be complete. If you prefer to install/update manually see the [Manual Install / Update Instructions](https://github.com/davidgsd/AllScan/blob/main/docs/manualInstallUpdate.md).

Log into your node by SSH and run the following commands:

	cd ~
	wget 'https://raw.githubusercontent.com/davidgsd/AllScan/main/AllScanInstallUpdate.php'
	chmod 755 AllScanInstallUpdate.php
	sudo ./AllScanInstallUpdate.php

The Install/Update script will provide detailed status messages on each step of the process. Once the update/install is complete it is recommended to delete the script ("rm AllScanInstallUpdate.php"). Then the next time you want to do an update just run the above commands again.

Now open a browser and go to your node's IP address followed by /allscan/, eg. `http://192.168.1.23/allscan/` and be sure to add a bookmark in your browser.

If you did an update, **be sure to force a browser reload by pressing CTRL-[F5] or clearing your browser cache, or in mobile browsers do a long-press of the reload button**, so your browser will load the updated JavaScript and CSS files.

# Notes
SECURITY NOTE: User login support has not yet been implemented but will be completed in the next few days. If your node webserver is publicly accessible you might want to set up password protection on the /allscan/ directory. If you don't, someone could potentially edit your favorites or connect/disconnect remote nodes, though that's about all they could do. If you're using your node only on your local home network and do not have an external port mapped in your internet router to port 80 on your node then having a login and password is generally not necessary, but can still be enabled easily with a few simple steps such as described in this [article](https://www.digitalocean.com/community/tutorials/how-to-set-up-password-authentication-with-apache-on-ubuntu-20-04).

# Node Notes
If you do not have a node or if your node is out-of-date, noisy, or unreliable, check out the following article by AllScan's author NR9V: [How To Build a High-Quality Full-Duplex AllStar Node for Under $200](https://allscan.info/docs/diy-node.php).

# Troubleshooting / FAQs
For any issues including directory/file permissions issues, it is recommended to first always run the update script. The script will check if you have the latest version of AllScan, will update your install if not, and either way it will also validate all directory and file permissions. Refer to the "Automatic Install / Update" section above and run the update script and then see if the issue was resolved.

If you get a permissions error when trying to Add a Favorite, check that the /var/www/html/allscan and supermon dirs have 775 permissions and www-data group, and that the favorites.ini file exists in one or both of directories and has 664 permissions and www-data as the group. These settings should already be that way if your Supermon install is properly working, or it would not be able to edit and save the favorites.ini file. As a test you can go into Supermon, click the Configuration Editor button and try adding a blank line to favorites.ini and see if it saves OK. If not, there is something improperly configured with the Supermon install. The following commands should correct those settings:

	cd /var/www/html || cd /srv/http # Change to www root folder (works on ASL and HamVOIP)
	cd supermon
	sudo touch favorites.ini favorites.ini.bak
	sudo chmod 664 favorites.ini favorites.ini.bak
	sudo chmod 775 .
	# For ASL set group to www-data
	sudo chgrp www-data favorites.ini favorites.ini.bak .
	# else for HamVOIP set group to http
	# sudo chgrp http favorites.ini favorites.ini.bak .

If you keep your favorites.ini file in the allscan directory and see error messages when writing the file from allscan, run the same steps as above but in the /var/www/html/allscan/ folder.

# Contact
If you have any questions email chc_media at yahoo dot com. Also see [AllScan.info](https://AllScan.info). 73, NR9V

# Support / Donations
I have received many Thank You's and inquiries/offers for a cup of coffee or other small donation which are much appreciated, but for now I ask that any contributions you can spare be directed to AllStarLink.org, who have put in many years of work maintaining the free & open-source AllStar ecosystem, and who have a lot of overhead expenses. ASL probably needs your support more than I do. See [this link](https://donorbox.org/allstarlink-donations?amount=24&default_interval=a&utm_campaign=donate_4&utm_source=allscan) to donate to Allstarlink Inc. But if in addition to supporting ASL you did also want to contribute to AllScan feel free to send anything by paypal or venmo to chc_media at yahoo dot com. Even $1 does help cover web server expenses.

# Road Map
1. Implement user authentication system supporting multiple users with Read-only, Read/Modify, Full, or Admin permissions and full control over user accounts, all managed within the web GUI. Estimated completion: Jan. 10
2. Additional refinements to existing features
3. Other features that are highly requested or that seem like a good fit
4. Additional stats/scan features

# Release Notes
**v0.41 2022-01-08**<br>
JavaScript optimizations. Update page Title with node PTT/COS status to allow status to be seen when browser tab is not active. Initial checkin of functions supporting base configs, SQLite3 DB and config management, and user authentication and account management (these functions are not yet tied into the main controller file).

**v0.4 2022-01-02**<br>
Only show CPU Temp if data is available. Reduce favs table CSS cell padding from 4 to 3 px. Update InstallUpdate script to verify favorites.ini file in supermon dir is writeable by web server if dir exists. Readme updates.

**v0.39 2022-12-28**<br>
Minor optimizations. Add API to eventually support stats caching and additional stats features. Update CPU temp data once per minute.

**v0.38 2022-12-24**<br>
For EchoLink nodes don't link node Name text to ASL stats page. Revise Green/Yellow CPU Temp range threshold from 120 to 130 °F.

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

# FAQs
Q: What is the blinking icon for?<br>
A: AllScan's blinking 'lighting bolt' icon is a status indicator similar the 'spinny' in supermon or the blinking asterisk ('*') in allmon, which toggles on/off as each Connection Status event message is received from the node (ie. from AllScan's astapi/server.php file who reads status info from a socket connection to Asterisk on the node and then forwards that data every 500mS to AllScan's JavaScript in the browser.) If it stops blinking that means there is a communication issue between the browser and your node.

# Thanks
Thanks to all ASL Developers. Thanks to KK6QMS for help in Beta testing, and N6ATC for help in verifying AllScan works on HamVOIP.
