# AllScan Manual Install / Update Instructions
As there are a number of steps involved to set up various directories, download the allscan files, and verify directory and file permissions, the Automated Install/Update process in the [README](https://github.com/davidgsd/AllScan/blob/main/README.md) is highly recommended.

NOTE: The below instructions are for ASL Distributions. For HamVOIP, make the following changes to the install/update commands:
* Replace references to the web root folder "/var/www/html/" with "/srv/http/"
* Replace references to the web server group name "www-data" with "http"
* Command to restart web server on HamVOIP is "systemctl restart lighttpd"

You will need SSH access to your node and should have basic familiarity with Linux. Log into your node and run the following commands. Note that lines starting with '#' are comments. Read each comment and only execute the commands under it if applicable to your system.

	# If you are not already logged in as root user
	sudo su

	# cd to web root folder
	cd /var/www/html

	# (Optional, for Updates Only) Backup your existing allscan folder
	cp -a allscan allscan-old

	# Confirm allscan directories exist and are web server writeable
	ls allscan || mkdir allscan
	ls /etc/allscan || mkdir /etc/allscan
	chmod 775 allscan /etc/allscan
	chgrp www-data allscan /etc/allscan

	# (Optional, if you have Supermon installed) Confirm supermon directory is web server writeable
	chmod 775 supermon; chmod 664 supermon/favorites.ini
	chgrp www-data supermon supermon/favorites.ini

	# Download latest AllScan files
	wget https://github.com/davidgsd/AllScan/archive/refs/heads/main.zip
	unzip main.zip; rm main.zip
	cp -rf AllScan-main/* allscan/
	rm -rf AllScan-main

	# Confirm necessary php extensions are installed and up-to-date
	apt-get install -y php-sqlite3 php-curl;
	
	# Restart web server
	service apache2 restart
	
Now open a browser and go to your node's IP address followed by /allscan/, and be sure to add a bookmark in your browser.

If you did an update, **be sure to force a browser reload by pressing CTRL-[F5] or clearing your browser cache, or in mobile browsers do a long-press of the reload button**, so your browser will load the updated JavaScript and CSS files.