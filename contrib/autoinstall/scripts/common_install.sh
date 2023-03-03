#!/bin/bash

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

function install_apache {
    if [[ -z "$(which apache2)" ]] && if [[ -z "$(which nginx)" ]]
    then
        print_info "installing apache..."
        nocheck_install "apache2 apache2-utils"
        a2enmod rewrite
        systemctl restart apache2
    fi
    if [ "$(systemctl is-active apache2)" == "failed" ]
    then
        die "Something went wrong with the installation of Apache"
    fi
}

function install_nginx {
    if [[ -z "$(which nginx)" ]] && if [[ -z "$(which apache2)" ]]
    then
        print_info "installing nginx..."
        nocheck_install "nginx"
        systemctl restart nginx
    fi
    if [ "$(systemctl is-active nginx)" == "failed" ]
    then
        die "Something went wrong with the installation of Nginx"
    fi
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

function install_webserver {
    if [[ $webserver = "nginx" ]]
    then
        install_nginx
    elif [[ $webserver = "apache" ]]
    then
        install_apache
    fi
}

function install_php {
    if [[ -z "$(which php-fpm8.2)" ]]
        then
        print_info "installing php8.2..."
        if [[ $webserver == "nginx" ]]
        then
            nocheck_install "php8.2-fpm php8.2 php8.2-mysql php-pear php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip"
            sed -i "s/^upload_max_filesize =.*/upload_max_filesize = 100M/g" /etc/php/8.2/fpm/php.ini
            sed -i "s/^post_max_size =.*/post_max_size = 100M/g" /etc/php/8.2/fpm/php.ini
            systemctl restart php8.2-fpm
            print_info "php8.2 was installed."
        elif [[ $webserver == "apache" ]]
        then
            nocheck_install "libapache2-mod-php php php-mysql php-pear php-curl php-gd php-mbstring php-xml php-zip"
            phpversion=$(php -v|grep --only-matching --perl-regexp "(PHP )\d+\.\\d+\.\\d+"|cut -c 5-7)
            sed -i "s/^upload_max_filesize =.*/upload_max_filesize = 100M/g" /etc/php/$phpversion/apache2/php.ini
            sed -i "s/^post_max_size =.*/post_max_size = 100M/g" /etc/php/$phpversion/apache2/php.ini
            print_info "php ${phpversion} was installed"
        fi
    fi
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
            die 'ERROR: Invalid installer checksum'
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
    if [ ! -z $(which mysql) ]
    then
        print_info "MariaDB (or MySQL) is already installed"
    else
        print_info "we install mariadb-server"
        nocheck_install "mariadb-server"
        systemctl is-active --quiet mariadb && echo "MariaDB is running"
    fi
}

function install_imagemagick {
    if [[ -z "$(which convert)" ]]
    then
        print_info "installing imagemagick..."
        nocheck_install "imagemagick"
    fi
}

function install_run_ddns {
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
        else
            if [ -f dns_cache_fail ]
            then
                rm -f dns_cache_fail
            fi
        fi
    fi
}

function configure_cron_ddns {
    if [ ! -z $ddns_provider ]
    then
        scripts source ddns/$ddns_provider.sh
        configure_cron_$ddns_provider
    fi
}
