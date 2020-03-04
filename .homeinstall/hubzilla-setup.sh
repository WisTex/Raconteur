#!/bin/bash
#
# How to use
# ----------
# 
# This file automates the installation of
# - hubzilla: https://zotlabs.org/page/hubzilla/hubzilla-project and
# - zap: https://zotlabs.com/zap/
# under Debian Linux
#
# 1) Copy the file "hubzilla-config.txt.template" to "hubzilla-config.txt"
#       Follow the instuctions there
# 
# 2) Switch to user "root" by typing "su -"
# 
# 3) Run with "./hubzilla-setup.sh"
#       If this fails check if you can execute the script.
#       - To make it executable type "chmod +x hubzilla-setup.sh"
#       - or run "bash hubzilla-setup.sh"
# 
# 
# What does this script do basically?
# -----------------------------------
# 
# This file automates the installation of hubzilla under Debian Linux
# - install
#        * apache webserer, 
#        * php,  
#        * mariadb - the database for hubzilla,  
#        * adminer,  
#        * git to download and update addons
# - configure cron
#        * "Master.php" for regular background prozesses of hubzilla
#        * "apt-get update" and "apt-get dist-upgrade" and "apt-get autoremove" to keep linux up-to-date
#        * run command to keep the IP up-to-date > DynDNS provided by selfHOST.de or freedns.afraid.org
#        * backup hubzillas database and files (rsync)
# - run letsencrypt to create, register and use a certifacte for https
# 
# 
# Discussion
# ----------
# 
# Security - password  is the same for mysql-server, phpmyadmin and hubzilla db
# - The script runs into installation errors for phpmyadmin if it uses
#   different passwords. For the sake of simplicity one singel password.
# 
# How to restore from backup
# --------------------------
#
# Daily backup
# - - - - - - 
# 
# The installation
# - writes a script /var/www/hubzilla-daily.sh
# - creates a daily cron that runs the hubzilla-daily.sh
#
# hubzilla-daily.sh makes a (daily) backup of all relevant files
# - /var/lib/mysql/ > database
# - /var/www/ > hubzilla/zap from github
# - /etc/letsencrypt/ > certificates
# 
# hubzilla-daily.sh writes the backup to an external disk compatible to LUKS+ext4 (see hubzilla-config.txt)
# 
# Credits
# -------
#
# The script is based on Thomas Willinghams script "debian-setup.sh"
# which he used to install the red#matrix.
#
# The documentation for bash is here
# https://www.gnu.org/software/bash/manual/bash.html
#
function check_sanity {
    # Do some sanity checking.
    print_info "Sanity check..."
    if [ $(/usr/bin/id -u) != "0" ]
    then
        die 'Must be run by root user'
    fi

    if [ -f /etc/lsb-release ]
    then
        die "Distribution is not supported"
    fi
    if [ ! -f /etc/debian_version ]
    then
        die "Debian is supported only"
    fi
    if ! grep -q 'Linux 10' /etc/issue
    then
        die "Linux 10 (buster) is supported only"x
    fi
}

function check_config {
    print_info "config check..."
    # Check for required parameters
    if [ -z "$db_pass" ]
    then
        die "db_pass not set in $configfile"
    fi     
    if [ -z "$le_domain" ]
    then
        die "le_domain not set in $configfile"
    fi   
    # backup is important and should be checked
	if [ -n "$backup_device_name" ]
	then
		if [ ! -d "$backup_mount_point" ]
		then
			mkdir "$backup_mount_point"
		fi
		device_mounted=0
		if fdisk -l | grep -i "$backup_device_name.*linux"
		then
		    print_info "ok - filesystem of external device is linux"
	        if [ -n "$backup_device_pass" ]
	        then
	            echo "$backup_device_pass" | cryptsetup luksOpen $backup_device_name cryptobackup
	            if mount /dev/mapper/cryptobackup /media/hubzilla_backup
	            then
                    device_mounted=1
	                print_info "ok - could encrypt and mount external backup device"
                	umount /media/hubzilla_backup
	            else
            		print_warn "backup to external device will fail because encryption failed"
	            fi
                cryptsetup luksClose cryptobackup
            else
	            if mount $backup_device_name /media/hubzilla_backup
	            then
                    device_mounted=1
	                print_info "ok - could mount external backup device"
                	umount /media/hubzilla_backup
	            else
            		print_warn "backup to external device will fail because mount failed"
	            fi
            fi
		else
        	print_warn "backup to external device will fail because filesystem is either not linux or 'backup_device_name' is not correct in $configfile"
		fi
        if [ $device_mounted == 0 ]
        then
            die "backup device not ready"
        fi
	fi
}

function die {
    echo "ERROR: $1" > /dev/null 1>&2
    exit 1
}


function update_upgrade {
    print_info "updated and upgrade..."
    # Run through the apt-get update/upgrade first. This should be done before
    # we try to install any package
    apt-get -q -y update && apt-get -q -y dist-upgrade
    print_info "updated and upgraded linux"
}

function check_install {
    if [ -z "`which "$1" 2>/dev/null`" ]
    then
        # export DEBIAN_FRONTEND=noninteractive ... answers from the package
        # configuration database
        # - q ... without progress information
        # - y ... answer interactive questions with "yes"
        # DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends -q -y install $2
        DEBIAN_FRONTEND=noninteractive apt-get -q -y install $2
        print_info "installed $2 installed for $1"
    else
        print_warn "$2 already installed"
    fi
}

function nocheck_install {
    # export DEBIAN_FRONTEND=noninteractive ... answers from the package configuration database
    # - q ... without progress information
    # - y ... answer interactive questions with "yes"
    # DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends -q -y install $2
    # DEBIAN_FRONTEND=noninteractive apt-get --install-suggests -q -y install $1
    DEBIAN_FRONTEND=noninteractive apt-get -q -y install $1
    print_info "installed $1"
}


function print_info {
    echo -n -e '\e[1;34m'
    echo -n $1
    echo -e '\e[0m'
}

function print_warn {
    echo -n -e '\e[1;31m'
    echo -n $1
    echo -e '\e[0m'
}

function stop_hubzilla {
    print_info "stopping apache webserver..."
    systemctl stop apache2
    print_info "stopping mysql db..."
    systemctl stop mariadb
}

function install_apache {
    print_info "installing apache..."
    nocheck_install "apache2 apache2-utils"
    a2enmod rewrite
    systemctl restart apache2
}

function install_imagemagick {
    print_info "installing imagemagick..."
    nocheck_install "imagemagick"
}

function install_curl {
    print_info "installing curl..."
    nocheck_install "curl"
}

function install_wget {
    print_info "installing wget..."
    nocheck_install "wget"
}

function install_sendmail {
    print_info "installing sendmail..."
    nocheck_install "sendmail sendmail-bin"
}

function install_php {
    # openssl and mbstring are included in libapache2-mod-php
    print_info "installing php..."
    nocheck_install "libapache2-mod-php php php-pear php-curl php-gd php-mbstring php-xml php-zip"
    sed -i "s/^upload_max_filesize =.*/upload_max_filesize = 100M/g" /etc/php/7.3/apache2/php.ini
    sed -i "s/^post_max_size =.*/post_max_size = 100M/g" /etc/php/7.3/apache2/php.ini
}

function install_mysql {
    print_info "installing mysql..."
    if [ -z "$mysqlpass" ]
    then
        die "mysqlpass not set in $configfile"
    fi
	if type mysql ; then
		echo "Yes, mysql is installed"
	else
		echo "mariadb-server"
		nocheck_install "mariadb-server"
        systemctl status mariadb
        systemctl start mariadb
        mysql --user=root <<_EOF_
UPDATE mysql.user SET Password=PASSWORD('${db_root_password}') WHERE User='root';
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
_EOF_
    fi    
}

function install_adminer {
    print_info "installing adminer..."
    nocheck_install "adminer"
    if [ ! -f /etc/adminer/adminer.conf ]
    then
        echo "Alias /adminer /usr/share/adminer/adminer" > /etc/adminer/adminer.conf
        ln -s /etc/adminer/adminer.conf /etc/apache2/conf-available/adminer.conf
    else
        print_info "file /etc/adminer/adminer.conf exists already"
    fi

    a2enmod rewrite

    if [ ! -f /etc/apache2/apache2.conf ]
    then
        die "could not find file /etc/apache2/apache2.conf"
    fi
    sed -i \
        "s/AllowOverride None/AllowOverride all/" \
        /etc/apache2/apache2.conf

    a2enconf adminer
    systemctl restart mariadb
    systemctl reload apache2
}

function create_hubzilla_db {
    print_info "creating hubzilla database..." 
    if [ -z "$hubzilla_db_name" ]
    then
        die "hubzilla_db_name not set in $configfile"
    fi     
    if [ -z "$hubzilla_db_user" ]
    then
        die "hubzilla_db_user not set in $configfile"
    fi     
    if [ -z "$hubzilla_db_pass" ]
    then
        die "hubzilla_db_pass not set in $configfile"
    fi
    systemctl restart mariadb
    Q1="CREATE DATABASE IF NOT EXISTS $hubzilla_db_name;"
    Q2="GRANT USAGE ON *.* TO $hubzilla_db_user@localhost IDENTIFIED BY '$hubzilla_db_pass';"
    Q3="GRANT ALL PRIVILEGES ON $hubzilla_db_name.* to $hubzilla_db_user@localhost identified by '$hubzilla_db_pass';"
    Q4="FLUSH PRIVILEGES;"
    SQL="${Q1}${Q2}${Q3}${Q4}"     
    mysql -uroot -p$phpmyadminpass -e "$SQL"
}

function run_freedns {
    print_info "run freedns (dynamic IP)..."
    if [ -z "$freedns_key" ]
    then
        print_info "freedns was not started because 'freedns_key' is empty in $configfile"
    else
        if [ -n "$selfhost_user" ]
        then
            die "You can not use freeDNS AND selfHOST for dynamic IP updates ('freedns_key' AND 'selfhost_user' set in $configfile)"
        fi
        wget --no-check-certificate -O - http://freedns.afraid.org/dynamic/update.php?$freedns_key       
    fi
}

function install_run_selfhost {
    print_info "install and start selfhost (dynamic IP)..."
    if [ -z "$selfhost_user" ]
    then
        print_info "selfHOST was not started because 'selfhost_user' is empty in $configfile"
    else
        if [ -n "$freedns_key" ]
        then
            die "You can not use freeDNS AND selfHOST for dynamic IP updates ('freedns_key' AND 'selfhost_user' set in $configfile)"
        fi
        if [ -z "$selfhost_pass" ]
        then
            die "selfHOST was not started because 'selfhost_pass' is empty in $configfile"
        fi
        if [ ! -d $selfhostdir ]
        then
            mkdir $selfhostdir
        fi
        # the old way
        # https://carol.selfhost.de/update?username=123456&password=supersafe
        #
        # the prefered way
        wget --output-document=$selfhostdir/$selfhostscript http://jonaspasche.de/selfhost-updater
        echo "router" > $selfhostdir/device
        echo "$selfhost_user" > $selfhostdir/user
        echo "$selfhost_pass" > $selfhostdir/pass
        bash $selfhostdir/$selfhostscript update
    fi
}

function ping_domain {
    print_info "ping domain $domain..."
    # Is the domain resolved? Try to ping 6 times à 10 seconds 
    COUNTER=0    
    for i in {1..6}
    do
        print_info "loop $i for ping -c 1 $domain ..."     
        if ping -c 4 -W 1 $le_domain    
        then
            print_info "$le_domain resolved"
            break
        else 
            if [ $i -gt 5 ]
            then
                die "Failed to: ping -c 1 $domain not resolved"
            fi            
        fi 
        sleep 10
    done
    sleep 5
}

function configure_cron_freedns {
    print_info "configure cron for freedns..."
    if [ -z "$freedns_key" ]
    then
        print_info "freedns is not configured because freedns_key is empty in $configfile"
    else
        # Use cron for dynamich ip update
        #   - at reboot
        #   - every 30 minutes
        if [ -z "`grep 'freedns.afraid.org' /etc/crontab`" ]
        then
            echo "@reboot root http://freedns.afraid.org/dynamic/update.php?$freedns_key > /dev/null 2>&1" >> /etc/crontab
            echo "*/30 * * * * root wget --no-check-certificate -O - http://freedns.afraid.org/dynamic/update.php?$freedns_key > /dev/null 2>&1" >> /etc/crontab
        else
            print_info "cron for freedns was configured already"
        fi       
    fi
}

function configure_cron_selfhost {
    print_info "configure cron for selfhost..."
    if [ -z "$selfhost_user" ]
    then
        print_info "selfhost is not configured because selfhost_key is empty in $configfile"
    else
        # Use cron for dynamich ip update
        #   - at reboot
        #   - every 5 minutes
        if [ -z "`grep 'selfhost-updater.sh' /etc/crontab`" ]
        then
            echo "@reboot root bash /etc/selfhost/selfhost-updater.sh update > /dev/null 2>&1" >> /etc/crontab
            echo "*/5 * * * * root /bin/bash /etc/selfhost/selfhost-updater.sh update > /dev/null 2>&1" >> /etc/crontab
        else
            print_info "cron for selfhost was configured already"
        fi        
    fi
}

function install_letsencrypt {
    print_info "installing let's encrypt ..."
    # check if user gave domain
    if [ -z "$le_domain" ]
    then
        die "Failed to install let's encrypt: 'le_domain' is empty in $configfile"
    fi
    if [ -z "$le_email" ]
    then
        die "Failed to install let's encrypt: 'le_email' is empty in $configfile"
    fi
    nocheck_install "certbot python-certbot-apache" 
    print_info "run certbot ..."
	certbot --apache -w /var/www/html -d $le_domain -m $le_email --agree-tos --non-interactive --redirect --hsts --uir
    service apache2 restart
}

function check_https {
    print_info "checking httpS > testing ..."
    url_https=https://$le_domain
    wget_output=$(wget -nv --spider --max-redirect 0 $url_https)
    if [ $? -ne 0 ]
    then
        print_warn "check not ok"
    else
        print_info "check ok"
    fi
}

function install_hubzilla {
    print_info "installing addons..."
    cd /var/www/html/
    if git remote -v | grep -i "origin.*hubzilla.*"
    then
        print_info "hubzilla"
        util/add_addon_repo https://framagit.org/hubzilla/addons hzaddons
    elif git remote -v | grep -i "origin.*zap.*"
    then
        print_info "zap"
        util/add_addon_repo https://framagit.org/zot/zap-addons.git zaddons
    else
        die "neither zap nor hubzilla repository > did not install addons or zap/hubzilla"
    fi
    mkdir -p "cache/smarty3"
    chmod -R 777 store
    touch .htconfig.php
    chmod ou+w .htconfig.php
    cd /var/www/
    chown -R www-data:www-data html
	chown root:www-data /var/www/html/
	chown root:www-data /var/www/html/.htaccess
	chmod 0644 /var/www/html/.htaccess
    print_info "installed addons"
}

function install_rsync {
    print_info "installing rsync..."
    nocheck_install "rsync"
}

function install_cryptosetup {
    print_info "installing cryptsetup..."
    nocheck_install "cryptsetup"
}

function configure_cron_daily {
    print_info "configuring cron..."
    # every 10 min for poller.php
    if [ -z "`grep 'Master.php' /etc/crontab`" ]
    then
        echo "*/10 * * * * www-data cd /var/www/html; php Zotlabs/Daemon/Master.php Cron >> /dev/null 2>&1" >> /etc/crontab
    fi
    # Run external script daily at 05:30
    # - stop apache and mysql-server
    # - renew the certificate of letsencrypt
    # - backup db, files (/var/www/html), certificates if letsencrypt
    # - update hubzilla core and addon
    # - update and upgrade linux
    # - reboot is done by "shutdown -h now" because "reboot" hangs sometimes depending on the system
echo "#!/bin/sh" > /var/www/$hubzilladaily
echo "#" >> /var/www/$hubzilladaily
echo "echo \" \"" >> /var/www/$hubzilladaily
echo "echo \"+++ \$(date) +++\"" >> /var/www/$hubzilladaily
echo "echo \" \"" >> /var/www/$hubzilladaily
echo "echo \"\$(date) - renew certificate...\"" >> /var/www/$hubzilladaily
echo "certbot renew --noninteractive" >> /var/www/$hubzilladaily
echo "#" >> /var/www/$hubzilladaily
echo "echo \"\$(date) - stopping apache and mysql...\"" >> /var/www/$hubzilladaily
echo "service apache2 stop" >> /var/www/$hubzilladaily
echo "/etc/init.d/mysql stop # to avoid inconsistencies" >> /var/www/$hubzilladaily
echo "#" >> /var/www/$hubzilladaily
echo "# backup" >> /var/www/$hubzilladaily
echo "echo \"\$(date) - try to mount external device for backup...\"" >> /var/www/$hubzilladaily
echo "backup_device_name=$backup_device_name" >> /var/www/$hubzilladaily
echo "backup_device_pass=$backup_device_pass" >> /var/www/$hubzilladaily
echo "backup_mount_point=$backup_mount_point" >> /var/www/$hubzilladaily
echo "device_mounted=0" >> /var/www/$hubzilladaily
echo "if [ -n \"$backup_device_name\" ]" >> /var/www/$hubzilladaily
echo "then" >> /var/www/$hubzilladaily
echo "    if blkid | grep $backup_device_name" >> /var/www/$hubzilladaily
echo "    then" >> /var/www/$hubzilladaily
	if [ -n "$backup_device_pass" ]
	then
echo "        echo \"decrypting backup device...\"" >> /var/www/$hubzilladaily
echo "        echo "\"$backup_device_pass\"" | cryptsetup luksOpen $backup_device_name cryptobackup" >> /var/www/$hubzilladaily
    fi
echo "        if [ ! -d $backup_mount_point ]" >> /var/www/$hubzilladaily
echo "        then" >> /var/www/$hubzilladaily
echo "            mkdir $backup_mount_point" >> /var/www/$hubzilladaily
echo "        fi" >> /var/www/$hubzilladaily
echo "        echo \"mounting backup device...\"" >> /var/www/$hubzilladaily
	if [ -n "$backup_device_pass" ]
	then
echo "        if mount /dev/mapper/cryptobackup $backup_mount_point" >> /var/www/$hubzilladaily
	else
echo "        if mount $backup_device_name $backup_mount_point" >> /var/www/$hubzilladaily
	fi
echo "        then" >> /var/www/$hubzilladaily
echo "            device_mounted=1" >> /var/www/$hubzilladaily
echo "            echo \"device $backup_device_name is now mounted. Starting backup...\"" >> /var/www/$hubzilladaily
echo "            rsync -a --delete /var/lib/mysql/ /media/hubzilla_backup/mysql" >> /var/www/$hubzilladaily
echo "            rsync -a --delete /var/www/ /media/hubzilla_backup/www" >> /var/www/$hubzilladaily
echo "            rsync -a --delete /etc/letsencrypt/ /media/hubzilla_backup/letsencrypt" >> /var/www/$hubzilladaily
echo "            echo \"\$(date) - disk sizes...\"" >> /var/www/$hubzilladaily
echo "            df -h" >> /var/www/$hubzilladaily
echo "            echo \"\$(date) - db size...\"" >> /var/www/$hubzilladaily
echo "            du -h $backup_mount_point | grep mysql/hubzilla" >> /var/www/$hubzilladaily
echo "            echo \"unmounting backup device...\"" >> /var/www/$hubzilladaily
echo "            umount $backup_mount_point" >> /var/www/$hubzilladaily
echo "        else" >> /var/www/$hubzilladaily
echo "            echo \"failed to mount device $backup_device_name\"" >> /var/www/$hubzilladaily
echo "        fi" >> /var/www/$hubzilladaily
	if [ -n "$backup_device_pass" ]
	then
echo "        echo \"closing decrypted backup device...\"" >> /var/www/$hubzilladaily
echo "        cryptsetup luksClose cryptobackup" >> /var/www/$hubzilladaily
	fi
echo "    fi" >> /var/www/$hubzilladaily
echo "fi" >> /var/www/$hubzilladaily
echo "if [ \$device_mounted == 0 ]" >> /var/www/$hubzilladaily
echo "then" >> /var/www/$hubzilladaily
echo "    echo \"device could not be mounted $backup_device_name. No backup written.\"" >> /var/www/$hubzilladaily
echo "fi" >> /var/www/$hubzilladaily
echo "#" >> /var/www/$hubzilladaily
echo "echo \"\$(date) - db size...\"" >> /var/www/$hubzilladaily
echo "du -h /var/lib/mysql/ | grep mysql/hubzilla" >> /var/www/$hubzilladaily
echo "#" >> /var/www/$hubzilladaily
echo "# update" >> /var/www/$hubzilladaily
echo "echo \"\$(date) - updating core and addons...\"" >> /var/www/$hubzilladaily
echo "(cd /var/www/html/ ; util/udall)" >> /var/www/$hubzilladaily
echo "chown -R www-data:www-data /var/www/html/ # make all accessable for the webserver" >> /var/www/$hubzilladaily
echo "chown root:www-data /var/www/html/.htaccess" >> /var/www/$hubzilladaily
echo "chmod 0644 /var/www/html/.htaccess # www-data can read but not write it" >> /var/www/$hubzilladaily
echo "echo \"\$(date) - updating linux...\"" >> /var/www/$hubzilladaily
echo "apt-get -q -y update && apt-get -q -y dist-upgrade && apt-get -q -y autoremove # update linux and upgrade" >> /var/www/$hubzilladaily
echo "echo \"\$(date) - Backup and update finished. Rebooting...\"" >> /var/www/$hubzilladaily
echo "#" >> /var/www/$hubzilladaily
echo "shutdown -r now" >> /var/www/$hubzilladaily

    if [ -z "`grep 'hubzilla-daily.sh' /etc/crontab`" ]
    then
        echo "30 05 * * * root /bin/bash /var/www/$hubzilladaily >> /var/www/html/hubzilla-daily.log 2>&1" >> /etc/crontab
        echo "0 0 1 * * root rm /var/www/html/hubzilla-daily.log" >> /etc/crontab
    fi

    # This is active after either "reboot" or "/etc/init.d/cron reload"
    print_info "configured cron for updates/upgrades"
}

########################################################################
# START OF PROGRAM 
########################################################################
export PATH=/bin:/usr/bin:/sbin:/usr/sbin

check_sanity

# Read config file edited by user
configfile=hubzilla-config.txt
source $configfile

selfhostdir=/etc/selfhost
selfhostscript=selfhost-updater.sh
hubzilladaily=hubzilla-daily.sh
backup_mount_point=/media/hubzilla_backup

#set -x    # activate debugging from here

check_config
stop_hubzilla
update_upgrade
install_curl
install_wget
install_sendmail
install_apache
install_imagemagick
install_php
install_mysql
install_adminer
create_hubzilla_db
run_freedns
install_run_selfhost
ping_domain
configure_cron_freedns
configure_cron_selfhost

if [ "$le_domain" != "localhost" ]
then
    install_letsencrypt
    check_https
else
    print_info "is localhost - skipped installation of letsencrypt and configuration of apache for https"
fi     

install_hubzilla

configure_cron_daily

if [ "$le_domain" != "localhost" ]
then
    install_cryptosetup
    install_rsync
else
    print_info "is localhost - skipped installation of cryptosetup"
fi     


#set +x    # stop debugging from here


