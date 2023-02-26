#!/bin/bash

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

if [ ! -z $ddns_provider ]
then
    source scripts/ddns/$ddns_provider.sh
    if [ ! -f dns_cache_fail ]
    then
        nocheck_install "dnsutils"
        install_run_$ddns_provider
    fi
    if [ -z $(dig -4 $le_domain +short | grep $(curl ip4.me/ip/)) ]
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

# We install the required PHP version
php_version
install_php

# We install Let's Encrypt
install_letsencrypt
nginx_conf_le

# We configure our webserver for our website
add_nginx_conf
