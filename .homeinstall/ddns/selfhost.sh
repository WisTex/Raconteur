#!/bin/bash
#
#
#########################################################
#            WHAT DOES THIS SCRIPT DO ?                 #
#########################################################

# This script will do two things :
# - Configure your selfHOST.de domain so that it points to your server's IP address
# - Create a cron job which will change you selfHOST.de IP configuration when needed
#
#########################################################
#                   INSTRUCTIONS                        #
#########################################################
#
# 1. Register a domain at selfhost.de
#    - choose offer "DOMAIN dynamisch" 1,50€/mon at 04/2019
# 2. Get your configuration for dynamic IP update
#    - Log in at selfhost.de
#    - go to "DynDNS Accounte"
#    - klick "Details" of your (freshly) registered domain
#    - You will find the configuration there
#      - Benutzername (user name) > use this for "selfhost_user="
#      - Passwort (password) > use this for "selfhost_pass="
#
#########################################################
#       THIS IS WHERE YOU ADD YOUR CREDENTIALS          #
#########################################################

selfhost_user=
selfhost_pass=

##########################################################
#                DO NOT EDIT AFTER THIS                  #
##########################################################

function install_run_selfhost {
    print_info "install and start selfhost (dynamic IP)..."
    if [ -z "$selfhost_user" ]
    then
        die "selfHOST was not started because 'selfhost_user' is empty in ddns/selfhost.sh"
    elif [ -z "$selfhost_pass" ]
    then
        die "selfHOST was not started because 'selfhost_pass' is empty in ddns/selfhots.sh"
    else
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

function configure_cron_selfhost {
    print_info "configure cron for selfhost..."
    # Use cron for dynamich ip update
    #   - at reboot
    #   - every 5 minutes
    if [ -z "`grep $selfhostscript /etc/crontab`" ]
    then
        echo "@reboot root bash $selfhostdir/$selfhostscript update > /dev/null 2>&1" >> /etc/crontab
        echo "*/5 * * * * root /bin/bash $selfhostdir/$selfhostscript update > /dev/null 2>&1" >> /etc/crontab
    else
        print_info "cron for selfhost was configured already"
    fi
}

selfhostdir=/etc/selfhost
selfhostscript=selfhost-updater.sh
