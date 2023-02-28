#!/bin/bash

function update_upgrade {
    print_info "updated and upgrade..."
    # Run through the apt-get update/upgrade first. This should be done before
    # we try to install any package
    apt-get -q -y update && apt-get -q -y dist-upgrade
    print_info "updated and upgraded linux"
}

function install_curl {
    if [[ -z "$(which curl)" ]]
    then
        print_info "installing curl..."
        nocheck_install "curl"
    fi
}

function install_wget {
    if [[ -z "$(which wget)" ]]
    then
        print_info "installing wget..."
        nocheck_install "wget"
    fi
}

function install_sendmail {
    if [[ -z "$(which sendmail)" ]]
    then
        print_info "installing sendmail..."
        nocheck_install "sendmail sendmail-bin"
    fi
}

function install_sury_repo {
    # With Debian 11 (bullseye) we need an extra repo to install php 8.*
    if [[ -z $(grep -R "deb https://packages.sury.org/php/" /etc/apt/) ]]
    then
        print_info "installing sury-php repository..."
        apt-get -y install apt-transport-https
        curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
        sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury-php.list'
        apt-get update -y
    else
        print_info "sury-php repository is already installed."
    fi
}

function install_apache {
    if [[ -z "$(which apache2)" ]]
    then
        print_info "installing apache..."
        nocheck_install "apache2 apache2-utils"
        a2enmod rewrite
        systemctl restart apache2
    fi
}

function install_nginx {
    if [[ -z "$(which nginx)" ]]
    then
        print_info "installing nginx..."
        nocheck_install "nginx"
        systemctl restart nginx
    fi
}

function add_vhost {
    print_info "adding apache vhost"
    echo "<VirtualHost *:80>" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "ServerName ${domain_name}" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "DocumentRoot $install_path" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "   <Directory $install_path>" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "       AllowOverride All" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "   </Directory>" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "</VirtualHost>"  >> "/etc/apache2/sites-available/${domain_name}.conf"
    a2ensite $domain_name
    vhost_added=yes
}

function install_letsencrypt {
    if [[ -z "$(which certbot)" ]]
    then
        print_info "installing let's encrypt ..."
        # installing certbot via snapd is the preferred method (10/2022) https://certbot.eff.org/instructions
        nocheck_install "snapd"
        print_info "ensure that version of snapd is up to date..."
        snap install core
        snap refresh core
        print_info "install certbot via snap..."
        snap install --classic certbot
        ln -s /snap/bin/certbot /usr/bin/certbot
    fi
}

function nginx_conf_le {
    print_info "run certbot..."
    certbot certonly --nginx -d $domain_name -m $le_email --agree-tos --non-interactive
    cert="/etc/letsencrypt/live/$domain_name/fullchain.pem"
    cert_key="/etc/letsencrypt/live/$domain_name/privkey.pem"
}

function vhost_le {
    print_info "run certbot ..."
    certbot --apache -w $install_path -d $domain_name -m $le_email --agree-tos --non-interactive --redirect --hsts --uir
    service apache2 restart
    vhost_le_confgured=yes
}

function install_mysql {
    if [ ! -z $(which mysql) ]
    then
        print_info "MariaDB (or MySQL) is already installed"
    else
        print_info "we install mariadb-server"
        nocheck_install "mariadb-server"
        systemctl is-active --quiet mariadb && echo "MariaDB is running"
    fi
}


# We need to install basic stuff on a fresh install
update_upgrade
install_curl
install_wget
install_sendmail

if [ ! -z $ddns_provider ]
then
    source scripts/ddns/$ddns_provider.sh
    if [ ! -f dns_cache_fail ]
    then
        nocheck_install "dnsutils"
        install_run_$ddns_provider
    fi
    if [ -z $(dig -4 $domain_name +short | grep $(curl ip4.me/ip/)) ]
    then
        touch dns_cache_fail
        die "There seems to be a DNS cache issue here, you need to wait a few minutes before running the script again"
    fi
fi
ping_domain
# add something here to remove dns_cache_fail ?
if [ ! -z $ddns_provider ]
then
    scripts source ddns/$ddns_provider.sh
    configure_cron_$ddns_provider
fi

# We install our webserver
if [[ $webserver = "nginx" ]]
then
    install_nginx
elif [[ $webserver = "apache" ]]
then
    install_apache
fi

# We install the required PHP version
install_sury_repo
php_version
install_php

install_letsencrypt

# We configure our webserver for our website
if [[ $webserver = "nginx" ]]
then
    nginx_conf_le
    add_nginx_conf
elif [[ $webserver = "apache" ]]
then
    add_vhost
    vhost_le
fi

# We install our MariaDB server
install_mysql

