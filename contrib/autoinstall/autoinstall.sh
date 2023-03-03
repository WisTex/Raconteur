#!/bin/bash
#
# How to use
# ----------
#
# This file automates the installation of your website using the Streams repository
# (https://codeberg.org/streams/streams), on a freshly installed Debian GNU/Linux 11
# ("Bullseye") server.
#
# 1) Switch to user "root" by typing "su -"
#
# 2) Run with "./autoinstall.sh"
#       If this fails check if you can' execute the script:
#       - To make it executable type "chmod +x autoinstall.sh"
#       - or run "bash autoinstall.sh"
#
# 3) You will be asked you to provide the domain name of your website to be installed and
# database settings you wish to use for it.
#
# On a freshly installed Debian GNU/Linux server, you will be asked for an email address
# (for your Let's Encrypt certificate). You will also be able to configure a Dynamic DNS
# provider (three choices available for the moment).
#
# Once all the necessary info is  provided, the install will begin.
#
#
# What does this script do basically?
# -----------------------------------
#
# This file automates the installation of a Nomad/ActivityPub federation capable website
# under Debian GNU/Linux. It will:
# - install (if needed)
#        * php8.2,
#        * MariaDB (mysql),
#        * composer,
#        * Nginx or Apache as webserver
#        * Let's Encrypt certbot
# - add and configure a certificate for your website domain name
# - add and configure a mysql/MariaDB database for your website
# - configure cron
#        * "Run.php" for regular background processes of your website
#        * Updates of your website using git to pull from the Streams repository
#        * optionally run command to keep the IP up-to-date (if you use a Dynamic DNS provider)
#
#
#
# More information
# ----------------
#
# - You may run the script to install more than one website on the same server.
# - You can try to run the script on a Debian server with stuff already installed
#   on it. It might break EVERYTHING though, so use at you own risk.
#
# Credits
# -------
#
# The script is derived from the easyinstall script of the Streams repository, which is based on
# - Tom Wiedenhöfts (OJ Random) script homeinstall (for Hubzilla, ZAP,...) that was based on
# - Thomas Willinghams script "debian-setup.sh" which he used to install the red#matrix.
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
    if [[ -z "$(grep 'Linux 11' /etc/issue)" ]]
    then
        die "You can only run this script on a Debian GNU/Linux 11 server"
    else
        system=debian
        print_info "Running the autoinstall script on a Debian GNU/Linux 11 server"
    fi
}

function die {
    # We remove the website's apache conf files if they exist
    if [ ! -z $vhost_added ]
    then
        a2dissite $domain_name.conf*
        rm -f /etc/apache2/sites-available/$domain_name*
        systemctl reload apache2
        print_info "We delete apache conf files"
    fi
    # We remove the website's nginx conf files if they exist 
    if [ ! -z $nginx_conf ]
    then
        rm -f /etc/nginx/sites-available/$domain_name* /etc/nginx/sites-enabled/$domain_name*
        systemctl reload nginx
        print_info "We delete nginx conf files"
    fi
    # We delete database and database user if they exist
    if [ ! -z $db_installed ] || [[ ! -z $(mysql -h localhost -u root $opt_mysqlpass -e "SHOW DATABASES;" | grep -w "$website_db_name") ]]
    then
        mysql -h localhost -u root $opt_mysqlpass -e "DROP DATABASE $website_db_name; DROP USER $website_db_user@localhost;"
        print_info "We delete the \"$website_db_name\" database and \"$website_db_user\" database user"
    fi
    # We remove the addons if they were downloaded
    if [ ! -z $addons_installed ]
    then
        rm -rf $install_path/extend/addon/zaddons
        rm -rf $install_path/addon/*
        print_info "We delete the addons installed during the install attempt"
    fi
    # We remove the website's daily update script if it exists
    if [ ! -z $daily_update_exists ]
    then
        rm -f /var/www/$daily_update
        print_info "We delete the daily update script"
    fi
    # We remove .htconfig.php if it exists
    if [ -f $install_path/.htconfig.php ]
    then
        rm -f $install_path/.htconfig.php ]
        print_info "We delete .htconfig.php"
    fi
    # We change ownership of the directory back to root so we can try another install
    chown -R root:root $install_path

    echo -n -e '\e[1;31m'
    echo "ERROR: $1" > /dev/null 1>&2
    echo -e '\e[0m'
    exit 1
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
    # Make sure we don't write over an already existing database if we install more one website
    if [[ -z $(mysql -h localhost -u root $opt_mysqlpass -e "SHOW DATABASES;" | grep -w "$website_db_name") ]]
    then
        if [[ -z $(mysql -h localhost -u root $opt_mysqlpass -e "use mysql; SELECT user FROM user;" | grep -w "$website_db_user") ]]
        then
            Q1="CREATE DATABASE IF NOT EXISTS $website_db_name;"
            Q2="GRANT USAGE ON *.* TO $website_db_user@localhost IDENTIFIED BY '$website_db_pass';"
            Q3="GRANT ALL PRIVILEGES ON $website_db_name.* to $website_db_user@localhost identified by '$website_db_pass';"
            Q4="FLUSH PRIVILEGES;"
            SQL="${Q1}${Q2}${Q3}${Q4}"
            mysql -h localhost -uroot $opt_mysqlpass -e "$SQL"
            db_installed=yes
        else
            die "database user named \"$website_db_user\" already exists..."
        fi
    else
        die "database named \"$website_db_name\" already exists..."
    fi
}

function ping_domain {
    print_info "ping domain $domain..."
    # Is the domain resolved? Try to ping 6 times à 10 seconds
    COUNTER=0
    for i in {1..6}
    do
        print_info "loop $i for ping -c 1 $domain_name ..."
        if ping -c 4 -W 1 $domain_name
        then
            print_info "$domain_name resolved"
            break
        else
            if [ $i -gt 5 ]
            then
                die "Failed to: ping -c 1 $domain_name not resolved."
            fi
        fi
        sleep 10
    done
    sleep 5
}

function check_https {
    print_info "checking httpS > testing ..."
    url_https=https://$domain_name
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
        if [ ! -d $install_path/extend/addon/zaddons ]
        then
            util/add_addon_repo https://codeberg.org/streams/streams-addons.git zaddons
        else
            print_warn "Streams addons already present, we'll remove them"
            rm -rf $install_path/extend/addon/zaddons
            rm -rf $install_path/addon/*
            util/add_addon_repo https://codeberg.org/streams/streams-addons.git zaddons
        fi
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
    print_info "installed addons"
    addons_installed=yes
}

function configure_daily_update {
    echo "#!/bin/sh" >> /var/www/$daily_update
    echo "#" >> /var/www/$daily_update
    echo "# update of $domain_name federation capable website" >> /var/www/$daily_update
    echo "echo \"\$(date) - updating core and addons...\"" >> /var/www/$daily_update
    echo "echo \"reaching git repository for $domain_name $repository hub/instance...\"" >> /var/www/$daily_update
    echo "(cd $install_path ; util/udall)" >> /var/www/$daily_update
    echo "chown -R www-data:www-data $install_path # make all accessible for the webserver" >> /var/www/$daily_update
    if [[ $webserver == "apache" ]]
    then
        echo "chown root:www-data $install_path/.htaccess" >> /var/www/$daily_update
        echo "chmod 0644 $install_path/.htaccess # www-data can read but not write it" >> /var/www/$daily_update
    fi
    chmod a+x /var/www/$daily_update
    daily_update_exists=yes
}

function configure_cron_daily {
    print_info "configuring cron..."
    # every 10 min for Run.php
    if [[ -z $(grep "/var/www/$install_folder; php Code/Daemon/Run.php" /etc/crontab) ]]
    then
        echo "*/10 * * * * www-data cd $install_path; php Code/Daemon/Run.php Cron >> /dev/null 2>&1" >> /etc/crontab
    fi

    # Run external script daily at 05:30 to  update repository core and addon
    echo "#!/bin/sh" > /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "echo \" \"" >> /var/www/$cron_job
    echo "echo \"+++ \$(date) +++\"" >> /var/www/$cron_job
    echo "echo \" \"" >> /var/www/$cron_job
    echo "echo \"\$(date) - renew certificate...\"" >> /var/www/$cron_job
    echo "certbot renew --noninteractive" >> /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "echo \"\$(date) - db size...\"" >> /var/www/$cron_job
    echo "du -h /var/lib/mysql/ | grep mysql/" >> /var/www/$cron_job
    echo "#" >> /var/www/$cron_job
    echo "cd /var/www" >> /var/www/$cron_job
    echo "for f in *-daily.sh; do \"./\${f}\"; done" >> /var/www/$cron_job
    if [[ $system == "debian" ]]
    then
        echo "echo \"\$(date) - updating Debian GNU/Linux...\"" >> /var/www/$cron_job
        echo "apt-get -q -y update && apt-get -q -y dist-upgrade && apt-get -q -y autoremove # update Debian GNU/Linux and upgrade" >> /var/www/$cron_job
        echo "echo \"\$(date) - Update finished. Rebooting...\"" >> /var/www/$cron_job
        echo "#" >> /var/www/$cron_job
        echo "shutdown -r now" >> /var/www/$cron_job
    else
        echo "echo \"\$(date) - Update finished.\"" >> /var/www/$cron_job
    fi
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

for arg in "$@" ; do
   shift
   case "$arg" in
      --local) local_install=yes
               print "We're doing a local install, option is $local_install"
      ;;
      *) die "not a valid option"
      ;;
   esac
done

check_sanity
repo_name
print_info "We're installing a website using the $repository repository"
install_path="$(dirname $(dirname "$(pwd)"))"
if [ "$install_path" == "/var/www/html" ]
then
    die "Please don't install your website in /var/www/html."
fi
install_folder="$(basename $install_path)"
domain_regex="^([a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]\.)+[a-zA-Z]{2,}$"
local_regex="^([a-zA-Z0-9]){2,25}$"
print_info "Now using scripts/dialogs.sh to obtain all necessary settings for the install"
source scripts/dialogs.sh

#set -x    # activate debugging from here

if [[ $system == "debian" ]]
then
    source scripts/debian.sh
# Scripts for other Debian based distros could be added later
# elif [[ $system == "other_distro" ]]
# then
#     source scripts/other_distro.sh
fi
source scripts/common_install.sh
source scripts/common_conf.sh
# We need to install some basics on a freshly installed system
update_upgrade
install_curl
install_wget
install_sendmail
install_imagemagick
# DNS stuff
install_run_ddns
ping_domain
configure_cron_ddns
# Web server
install_webserver
# PHP
php_version
install_php
# Let's Encrypt
if [ -z $local_install ]
then
    install_letsencrypt
fi
# Webserver configuration
webserver_conf
# We install our MariaDB server
install_mysql
# Composer
install_composer
# Now we the website install
create_website_db
install_website
# Daily maintenance
daily_update="${domain_name}-daily.sh"
cron_job="cron_job.sh"
configure_daily_update
configure_cron_daily
# Final checks
check_https

# Put a nice message here no confirm the website was successfully installed

#set +x    # stop debugging from here
