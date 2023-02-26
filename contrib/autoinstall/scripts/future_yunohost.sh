#!/bin/bash
#
#
####################################################################################################
####                         THIS SCRIPT IS NOT USED FOR THE MOMENT                             ####
####                                                                                            ####
#### BEING ABLE TO LET YUNOHOST HANDLE THE NGINX/LET'S ENCRYPT CONFIGURATION WOULD SURE BE COOL ####
####                   BUT AS OF TODAY, A WAY OF DOING IT IT WAS NOT FOUND                      ####
####                                                                                            ####
####                 IF SOMEONE WANTS TO WORK ON THIS, IT WOULD SURE BE NICE.                   ####
####  OR MAYBE SOMEONE WILL WORK ON AN OFFICIAL YUNOHOST PACKAGE, WHICH WOULD EVEN BE BETTER.   ####
####################################################################################################

function ynh_domain {
    # We check if the domain name is configured in YunoHost
    if [[ ! -z $(yunohost domain list | grep $domain_name) ]]
    then
        print_info "$domain_name is present in your YunoHost server configuration"
        ynh_add_le
    else
        # If it is not we add the domain in YunoHost and the Let's Encrypt certificate
        print_info "Adding $domain_name in your YunoHost server configuration"
        yunohost domain install $domain_name
        ynh_add_le
    fi
}

function ynh_add_le {
    if [[ -z $(yunohost domain cert status $domain_name | grep letsencrypt) ]]
    then
        print_info "Adding a Lets Encrypt certificate for $domain_name"
        yunohost domain cert install --no-checks $domain_name
        ynh_add_le
    else
        print_info "$domain_name has a Let's Encrypt certificate configured on your YunoHost server"
        cert="/etc/yunohost/certs/$domain_name/crt.pem"
        cert_key="/etc/yunohost/certs/$domain_name/key.pem"
    fi
}

function yhn_nginx_conf {
    die "We don't know how to use the YunoHost Nginx configuration for the moment..."

    ################################################################################
    ########################### SOME WORK TO DO HERE ###############################
    ################################################################################
}

# We configure our domain in YunoHost
ynh_domain

ping_domain

# We install the required PHP version
php_version
install_php
ynh_nginx_conf
