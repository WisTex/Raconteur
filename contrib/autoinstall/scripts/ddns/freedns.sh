#!/bin/bash
#
#
#########################################################
#            WHAT DOES THIS SCRIPT DO ?                 #
#########################################################

# This script will do two things :
# - Configure your freedns subdomain so that if points to your server's IP address
# - Create a cron job which will change you freedns IP configuration when needed
#
#########################################################
#                   INSTRUCTIONS                        #
#########################################################
#
# Get a free subdomain from freedns and use it for your dynamic ip address
#
# - Register for a Free domain at http://freedns.afraid.org/signup/
# - WATCH THIS: Make sure you choose a domain with as less subdomains as
#   possible. Why? Let's encrpyt issues a limited count of certificates each
#   day. Possible other users of this domain will try to issue a certificate
#   at the same day.
# - Logon to FreeDNS (where you just registered)
# - Goto http://freedns.afraid.org/dynamic/
# - Right click on "Direct URL" and copy the URL and paste it somewhere.
# - You should notice a large and unique alpha-numeric key in the URL
#   (after the question mark)
#
#       http://freedns.afraid.org/dynamic/update.php?alpha-numeric-key
#
#   Provided your url from freedns is
#
#	http://freedns.afraid.org/dynamic/update.php?U1Z6aGt2R0NzMFNPNWRjbWxxZGpsd093OjE1Mzg5NDE5
#
#   Then you have to provide
#
#       freedns_key=U1Z6aGt2R0NzMFNPNWRjbWxxZGpsd093OjE1Mzg5NDE5
#
#
#########################################################
#          THIS IS WHERE YOU ADD YOUR KEY               #
#########################################################

freedns_key=$ddns_key

##########################################################
#                DO NOT EDIT AFTER THIS                  #
##########################################################


function install_run_freedns {
    print_info "run freedns (dynamic IP)..."
    if [ -z "$freedns_key" ]
    then
        die "freedns was not started because 'freedns_key' is empty in ddns/freedns.sh"
        exit 0
    else
        wget --no-check-certificate -O - http://freedns.afraid.org/dynamic/update.php?$freedns_key
    fi
}

function configure_cron_freedns {
    print_info "configure cron for freedns..."
    # Use cron for dynamich ip update
    #   - at reboot
    #   - every 30 minutes
    grep $freedns_key /etc/crontab
    if [ $? != 0 ]
    then
        echo "@reboot root http://freedns.afraid.org/dynamic/update.php?$freedns_key > /dev/null 2>&1" >> /etc/crontab
        echo "*/30 * * * * root wget --no-check-certificate -O - http://freedns.afraid.org/dynamic/update.php?$freedns_key > /dev/null 2>&1" >> /etc/crontab
    else
        print_info "cron for freedns was configured already"
    fi
}
