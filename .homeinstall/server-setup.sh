#!/bin/bash
#
# How to use
# ----------
#
# This file automates the installation of your website using the Streams repository
# (https://codeberg.org/streams/streams)
# under Debian Linux "Bullseye" or "Buster"
#
# 1) Copy the file "server-config.txt.template" to "server-config.txt"
#       Follow the instuctions there
#
# 2) Switch to user "root" by typing "su -"
#
# 3) Run with "./server-setup.sh"
#       If this fails check if you can execute the script.
#       - To make it executable type "chmod +x server-setup.sh"
#       - or run "bash server-setup.sh"
#
#
# What does this script do basically?
# -----------------------------------
#
# This file automates the installation of a Nomad/ActivityPub federation capable website
# under Debian Linux. It will:
# - install
#        * apache or nginx webserver,
#        * php (adding sury repository to get php 8.* on Debian 11 or 10),
#        * composer
#        * mariadb - the database your website,
#        * adminer,
#        * git to download and update addons
# - configure cron
#        * "Run.php" for regular background processes of your website
#        * "apt-get update" and "apt-get dist-upgrade" and "apt-get autoremove" to keep linux up-to-date
#        * optionally run command to keep the IP up-to-date > DynDNS provided by selfHOST.de or freedns.afraid.org
#        * optionally backup your server's database and files (rsync)
# - run letsencrypt to create, register and use a certifacte for https
#
#
# Discussion
# ----------
#
# Security - password  is the same for mysql-server, phpmyadmin and your hub/instance db
# - The script runs into installation errors for phpmyadmin if it uses
#   different passwords. For the sake of simplicity one single password.
#
# Daily backup
# ------------
#
# The installation
# - writes a shell script in /var/www/
# - creates a daily cron that runs this script
#
# The script makes a (daily) backup of all relevant files
# - /var/lib/mysql/ > database
# - /var/www/ > your websites
# - /etc/letsencrypt/ > certificates
#
# The backup will be written on an external disk compatible to LUKS+ext4 (see server-config.txt)
#
# How to restore from backup
# --------------------------
#
# (Some explanations here would certainly be useful)
#
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
    if [ -z "$(grep 'Linux 10\|Linux 11' /etc/issue)" ]
    then
        die "Debian 11 (bullseye) or Debian 10 (buster) are supported only"
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
	            if mount /dev/mapper/cryptobackup /media/server_backup
	            then
                    device_mounted=1
	                print_info "ok - could encrypt and mount external backup device"
                	umount /media/server_backup
	            else
            		print_warn "backup to external device will fail because encryption failed"
	            fi
                cryptsetup luksClose cryptobackup
            else
	            if mount $backup_device_name /media/server_backup
	            then
                    device_mounted=1
	                print_info "ok - could mount external backup device"
                	umount /media/server_backup
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

function stop_server {
    # If another website was already installed on this computer we stop the webserver
    if [ $webserver = "nginx" ] && [ -d /etc/nginx ]
    then
        print_info "stopping nginx webserver..."
        systemctl stop nginx
    elif [ $webserver = "apache" ] && [ -d /etc/apache2 ]
    then
        print_info "stopping apache webserver..."
        systemctl stop apache2
    fi
    # We probably don't need this wether we hav a db server installed or not
    # if [ -d /etc/mysql ]
    # then
    #     print_info "stopping mysql db..."
    #     systemctl stop mariadb
    # fi
}

function install_apache {
    print_info "installing apache..."
    nocheck_install "apache2 apache2-utils"
    a2enmod rewrite
    systemctl restart apache2
}

function install_nginx {
    print_info "installing nginx..."
    nocheck_install "nginx"
    systemctl restart nginx
}

function add_vhost {
    print_info "adding apache vhost"
    echo "<VirtualHost *:80>" >> "/etc/apache2/sites-available/${le_domain}.conf"
    echo "ServerName ${le_domain}" >> "/etc/apache2/sites-available/${le_domain}.conf"
    echo "DocumentRoot $install_path" >> "/etc/apache2/sites-available/${le_domain}.conf"
    echo "</VirtualHost>"  >> "/etc/apache2/sites-available/${le_domain}.conf"
    a2ensite $le_domain
}

function add_nginx_conf {
    print_info "adding nginx conf files"
    sed "s|SERVER_NAME|${le_domain}|g;s|INSTALL_PATH|${install_path}|g;s|SERVER_LOG|${le_domain}.log|;" nginx-server.conf.template >> /etc/nginx/sites-available/${le_domain}.conf
    ln -s /etc/nginx/sites-available/${le_domain}.conf /etc/nginx/sites-enabled/
    if [ ! -f /etc/nginx/snippets/adminer-nginx.inc ]
    then
        cp adminer-nginx.inc.template /etc/nginx/snippets/adminer-nginx.inc
    fi
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

function install_sury_repo {
    # With Debian 11 (bullseye) we need an extra repo to install php 8.*
    if [ ! -f /etc/apt/sources.list.d/sury-php.list ]
    then
        print_info "installing installing sury-php repository..."
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/sury-php.list
        wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add -
        apt-get update -y
    else
        print_info "sury-php repository is already installed."
    fi
}

function php_version {
    # We need to be able to find php version and use  it with install_php
    phpversion=$(php -v|grep --only-matching --perl-regexp "(PHP )\d+\.\\d+\.\\d+"|cut -c 5-7)
}

function install_php {
    # openssl and mbstring are included in libapache2-mod-php
    print_info "installing php..."
    if [ $webserver = "nginx" ]
    then
        nocheck_install "php-fpm php php-mysql php-pear php-curl php-gd php-mbstring php-xml php-zip"
        php_version
        sed -i "s/^upload_max_filesize =.*/upload_max_filesize = 100M/g" /etc/php/$phpversion/fpm/php.ini
        sed -i "s/^post_max_size =.*/post_max_size = 100M/g" /etc/php/$phpversion/fpm/php.ini
        systemctl reload php${phpversion}-fpm
    elif [ $webserver = "apache" ]
    then
        nocheck_install "libapache2-mod-php php php-mysql php-pear php-curl php-gd php-mbstring php-xml php-zip"
        php_version
        sed -i "s/^upload_max_filesize =.*/upload_max_filesize = 100M/g" /etc/php/$phpversion/apache2/php.ini
        sed -i "s/^post_max_size =.*/post_max_size = 100M/g" /etc/php/$phpversion/apache2/php.ini
    fi
    print_info "we'll be using PHP ${phpversion}"
}

function install_composer {
    print_info "We check if Composer is already installed"
    if [ ! -f /usr/local/bin/composer ]
    then
        EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
        if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]
        then
            >&2 echo 'ERROR: Invalid installer checksum'
            rm composer-setup.php
            exit 1
        fi
        php composer-setup.php --quiet
        RESULT=$?
        rm composer-setup.php
        # exit $RESULT
        # We install Composer globally
        mv composer.phar /usr/local/bin/composer
        print_info "Composer was successfully installed."
    else
        print_info "Composer is already installed on this system."
    fi
}

function install_mysql {
    print_info "installing mysql..."
    if [ -z "$mysqlpass" ]
    then
        die "mysqlpass not set in $configfile"
    fi
        if type mysql
        then
            echo "mysql is already installed"
        else
            echo "we install mariadb-server"
            nocheck_install "mariadb-server"
            systemctl is-active --quiet mariadb && echo "MariaDB is running"
            # We can probably find a more elegant solution like in create_website_db function
            mysql -u root <<_EOF_
ALTER USER 'root'@'localhost' IDENTIFIED BY '${mysqlpass}';
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
    if [ $webserver = "apache" ]
    then
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
    fi
}

function create_website_db {
    print_info "creating website's database..."
    if [ -z "$website_db_name" ]
    then
        website_db_name=$install_folder
    fi
    if [ -z "$website_db_user" ]
    then
        website_db_user=$install_folder
    fi
    if [ -z "$website_db_pass" ]
    then
        die "website_db_pass not set in $configfile"
    fi
    systemctl restart mariadb
    # Make sure we don't write over an already existing database if we install more one website
    if [ -z $(mysql -h localhost -u root -p$mysqlpass -e "SHOW DATABASES;" | grep $website_db_name) ]
    then
        Q1="CREATE DATABASE IF NOT EXISTS $website_db_name;"
        Q2="GRANT USAGE ON *.* TO $website_db_user@localhost IDENTIFIED BY '$website_db_pass';"
        Q3="GRANT ALL PRIVILEGES ON $website_db_name.* to $website_db_user@localhost identified by '$website_db_pass';"
        Q4="FLUSH PRIVILEGES;"
        SQL="${Q1}${Q2}${Q3}${Q4}"
        mysql -uroot -p$mysqlpass -e "$SQL"
    else
        die "Can't write over an already existing database!"
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
    if [ $webserver = "nginx" ]
    then
        nocheck_install "certbot"
        print_info "run certbot..."
        systemctl stop nginx
        certbot certonly --standalone -d $le_domain -m $le_email --agree-tos --non-interactive
        systemctl start nginx
    elif [ $webserver = "apache" ]
    then
        nocheck_install "certbot python-certbot-apache"
        print_info "run certbot ..."
	certbot --apache -w $install_path -d $le_domain -m $le_email --agree-tos --non-interactive --redirect --hsts --uir
        service apache2 restart
    fi
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

function repo_name {
    # We keep this in case the repository is forked in the future
    if git remote -v | grep -i "origin.*streams.*"
    then
        repository=streams
    # elif git remote -v | grep -i "origin.*fork_1.*"
    # then
    #     repository=fork_1
    # elif git remote -v | grep -i "origin.*fork_2.*"
    # then
    #     repository=fork_2
    else
        die "this script is not usable with this repository"
    fi
}

function install_website {
    cd $install_path/
    # Pull in external libraries with composer. Leave off the --no-dev
    # option if you are a developer and wish to install addditional CI/CD tools.
    COMPOSER_ALLOW_SUPERUSER=1 /usr/local/bin/composer install --no-dev

    # We install addons
    # We'll keep stuff here for possible future forks so that the script can be the same
    print_info "installing addons..."
    if [ $repository = "streams" ]
    then
        print_info "Streams"
        util/add_addon_repo https://codeberg.org/streams/streams-addons.git zaddons
    # elif [ $repository = "fork_1" ]
    # then
    #     print_info "Fork_1"
    #     util/add_addon_repo ** REPOSITORY HERE **
    # elif [ $repository = "fork_2" ]
    # then
    #     print_info "Fork_2"
    #     util/add_addon_repo **REPOSITORY HERE **
    else
        die "no addons can be installed for this repository"
    fi
    mkdir -p "cache/smarty3"
    mkdir -p "store"
    chmod -R 700 store cache
    touch .htconfig.php
    chmod ou+w .htconfig.php
    cd /var/www/
    chown -R www-data:www-data $install_path
	chown root:www-data $install_path/
	chown root:www-data $install_path/.htaccess
	chmod 0644 $install_path/.htaccess
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

function configure_daily_update {
    echo "#!/bin/sh" >> /var/www/$daily_update
    echo "#" >> /var/www/$daily_update
    echo "# update of $le_domain federation capable website" >> /var/www/$daily_update
    echo "echo \"\$(date) - updating core and addons...\"" >> /var/www/$daily_update
    echo "echo \"reaching git repository for $le_domain $repository hub/instance...\"" >> /var/www/$daily_update
    echo "(cd $install_path ; util/udall)" >> /var/www/$daily_update
    echo "chown -R www-data:www-data $install_path # make all accessible for the webserver" >> /var/www/$daily_update
    if [ $webserver = "apache" ]
    then
        echo "chown root:www-data $install_path/.htaccess" >> /var/www/$daily_update
        echo "chmod 0644 $install_path/.htaccess # www-data can read but not write it" >> /var/www/$daily_update
    fi
    chmod a+x /var/www/$daily_update
}

function configure_cron_daily {
    print_info "configuring cron..."
    # every 10 min for poller.php
    if [ -z "`grep '$install_path.*Run.php' /etc/crontab`" ]
    then
        echo "*/10 * * * * www-data cd $install_path; php Code/Daemon/Run.php Cron >> /dev/null 2>&1" >> /etc/crontab
    fi
    # Run external script daily at 05:30
    # - stop apache/nginx and mysql-server
    # - renew the certificate of letsencrypt
    # - backup db, files ($install_path), certificates if letsencrypt
    # - update repository core and addon
    # - update and upgrade linux
    # - reboot is done by "shutdown -h now" because "reboot" hangs sometimes depending on the system
    echo "#!/bin/sh" > /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "echo \" \"" >> /var/www/$cron_job
    echo "echo \"+++ \$(date) +++\"" >> /var/www/$cron_job
    echo "echo \" \"" >> /var/www/$cron_job
    echo "echo \"\$(date) - stopping $webserver and mysql...\"" >> /var/www/$cron_job
    if [ $webserver = "nginx" ]
    then
        echo "systemctl stop nginx" >> /var/www/$cron_job
    elif [ $webserver = "apache" ]
    then
        echo "service apache2 stop" >> /var/www/$cron_job
    fi
    echo "/etc/init.d/mysql stop # to avoid inconsistencies" >> /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "echo \"\$(date) - renew certificate...\"" >> /var/www/$cron_job
    echo "certbot renew --noninteractive" >> /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "# backup" >> /var/www/$cron_job
    echo "echo \"\$(date) - try to mount external device for backup...\"" >> /var/www/$cron_job
    echo "backup_device_name=$backup_device_name" >> /var/www/$cron_job
    echo "backup_device_pass=$backup_device_pass" >> /var/www/$cron_job
    echo "backup_mount_point=$backup_mount_point" >> /var/www/$cron_job
    echo "device_mounted=0" >> /var/www/$cron_job
    echo "if [ -n \"\$backup_device_name\" ]" >> /var/www/$cron_job
    echo "then" >> /var/www/$cron_job
    echo "    if blkid | grep $backup_device_name" >> /var/www/$cron_job
    echo "    then" >> /var/www/$cron_job
    if [ -n "$backup_device_pass" ]
    then
        echo "        echo \"decrypting backup device...\"" >> /var/www/$cron_job
        echo "        echo "\"$backup_device_pass\"" | cryptsetup luksOpen $backup_device_name cryptobackup" >> /var/www/$cron_job
    fi
    echo "        if [ ! -d $backup_mount_point ]" >> /var/www/$cron_job
    echo "        then" >> /var/www/$cron_job
    echo "            mkdir $backup_mount_point" >> /var/www/$cron_job
    echo "        fi" >> /var/www/$cron_job
    echo "        echo \"mounting backup device...\"" >> /var/www/$cron_job
    if [ -n "$backup_device_pass" ]
    then
        echo "        if mount /dev/mapper/cryptobackup $backup_mount_point" >> /var/www/$cron_job
    else
        echo "        if mount $backup_device_name $backup_mount_point" >> /var/www/$cron_job
    fi
    echo "        then" >> /var/www/$cron_job
    echo "            device_mounted=1" >> /var/www/$cron_job
    echo "            echo \"device $backup_device_name is now mounted. Starting backup...\"" >> /var/www/$cron_job
    echo "            rsync -a --delete /var/lib/mysql/ /media/repository_backup/mysql" >> /var/www/$cron_job
    echo "            rsync -a --delete /var/www/ /media/repository_backup/www" >> /var/www/$cron_job
    echo "            rsync -a --delete /etc/letsencrypt/ /media/repository_backup/letsencrypt" >> /var/www/$cron_job
    echo "            echo \"\$(date) - disk sizes...\"" >> /var/www/$cron_job
    echo "            df -h" >> /var/www/$cron_job
    echo "            echo \"\$(date) - db size...\"" >> /var/www/$cron_job
    echo "            du -h $backup_mount_point | grep mysql/repository" >> /var/www/$cron_job
    echo "            echo \"unmounting backup device...\"" >> /var/www/$cron_job
    echo "            umount $backup_mount_point" >> /var/www/$cron_job
    echo "        else" >> /var/www/$cron_job
    echo "            echo \"failed to mount device $backup_device_name\"" >> /var/www/$cron_job
    echo "        fi" >> /var/www/$cron_job
    if [ -n "$backup_device_pass" ]
    then
        echo "        echo \"closing decrypted backup device...\"" >> /var/www/$cron_job
        echo "        cryptsetup luksClose cryptobackup" >> /var/www/$cron_job
    fi
    echo "    fi" >> /var/www/$cron_job
    echo "fi" >> /var/www/$cron_job
    echo "if [ \$device_mounted == 0 ]" >> /var/www/$cron_job
    echo "then" >> /var/www/$cron_job
    echo "    echo \"device could not be mounted $backup_device_name. No backup written.\"" >> /var/www/$cron_job
    echo "fi" >> /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "echo \"\$(date) - db size...\"" >> /var/www/$cron_job
    echo "du -h /var/lib/mysql/ | grep mysql/" >> /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "cd /var/www" >> /var/www/$cron_job
    echo "for f in *-daily.sh; do \"./\${f}\"; done" >> /var/www/$cron_job
    echo "echo \"\$(date) - updating linux...\"" >> /var/www/$cron_job
    echo "apt-get -q -y update && apt-get -q -y dist-upgrade && apt-get -q -y autoremove # update linux and upgrade" >> /var/www/$cron_job
    echo "echo \"\$(date) - Backup and update finished. Rebooting...\"" >> /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "shutdown -r now" >> /var/www/$cron_job

    chmod a+x /var/www/$cron_job

    # If global cron job does not exist we add it to /etc/crontab
    if grep -q $cron_job /etc/crontab
    then
        echo "cron job already in /etc/crontab"
    else
        echo "30 05 * * * root /bin/bash /var/www/$cron_job >> /var/www/daily-updates.log 2>&1" >> /etc/crontab
        echo "0 0 1 * * root rm /var/www/daily-updates.log" >> /etc/crontab
    fi

    # This is active after either "reboot" or cron reload"
    systemctl restart cron
    print_info "configured cron for updates/upgrades"
}

########################################################################
# START OF PROGRAM
########################################################################
export PATH=/bin:/usr/bin:/sbin:/usr/sbin
check_sanity

repo_name
print_info "We're installing a website using the $repository repository"
install_path="$(dirname "$(pwd)")"
install_folder="$(basename $install_path)"

# Read config file edited by user
configfile=server-config.txt
source $configfile

selfhostdir=/etc/selfhost
selfhostscript=selfhost-updater.sh
cron_job="cron_job.sh"
daily_update="${le_domain}-daily.sh"
backup_mount_point="/media/repository_backup"

#set -x    # activate debugging from here

check_config
stop_server
update_upgrade
install_curl
install_wget
install_sendmail
install_sury_repo
if [ $webserver = "nginx" ]
then
    install_nginx
elif [ $webserver = "apache" ]
then
    install_apache
else
die "Failed to install a Web server: 'webserver' not set to \"apache\" or \"nginx\" in $configfile" 
fi
install_imagemagick
install_php
if [ $webserver = "nginx" ]
then
    if [ "$install_path" != "/var/www/html" ]
    then
        add_nginx_conf
    fi
elif [ $webserver = "apache" ]
then
    if [ "$install_path" != "/var/www/html" ]
    then
        add_vhost
    fi
fi
install_composer
install_mysql
install_adminer
create_website_db
if [ "$le_domain" != "localhost" ]
then
    if [ ! -z $ddns_provider ]
    source ddns/$ddns_provider.sh
    then
        install_run_$ddns_provider
    fi
    ping_domain
    if [ ! -z $ddns_provider ]
    then
        configure_cron_$ddns_provider
    fi
    install_letsencrypt
    check_https
else
    print_info "domain is localhost - skipped some stuff (https, DynDNS...)"
fi

install_website

configure_daily_update

configure_cron_daily

if [ "$le_domain" != "localhost" ]
then
    install_cryptosetup
    install_rsync
else
    print_info "is localhost - skipped installation of cryptosetup"
fi


#set +x    # stop debugging from here
