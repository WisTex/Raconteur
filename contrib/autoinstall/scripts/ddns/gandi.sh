#!/bin/bash
#
#
#########################################################
#            WHAT DOES THIS SCRIPT DO ?                 #
#########################################################
#
# This script will do two things :
# - Configure your domain (or subdomain) Gandi.net DNS records so that if points to your server's IP address
# - Create a cron job which will change you Gandi.net DNS records configuration when needed
#
#########################################################
#                   INSTRUCTIONS                        #
#########################################################
#
# 1. Register a domain at gandi.net, pricing will depend on the TLD
#    (some domains can cost a few EUR/USD/AUD a year others can cost thousands)
#
# 2. Make sure that your domain is configured with Gandi's LiveDNS nameservers
#    (it's enabled by default and an option easy to configure)
#
# 3. Get your API key
#    * Go to https://account.gandi.net/en/users/_USERNAME_/security
#      (replace _USERNAME_ with your Gandi account username)
#    * Click on the "(Re)generate the API key" link
#    * Copy the API key which will be as pretty as N8Azky2QxZbQhuP6EQXmD58S
#      (IMPORTANT : YOU WON'T BE ABLE TO RETRIEVE IT LATER, ONLY GENERATE A NEW ONE)
#    * Add you API key in this script
#
#         for example: gandi_api_key=N8Azky2QxZbQhuP6EQXmD58S
#
# 4. Set Gandi as your DDNS provider in server-config.txt (.homeinstall folder)
#
#         like this:   dns_provider=gandi
#
#    That way the ddns/gandi.sh (which you're editing) will be run during install
#
# 5. Run server-setup.sh in the .homeinstall folder
#
#########################################################
#          THIS IS WHERE YOU ADD YOUR API KEY           #
#########################################################

gandi_api_key=$ddns_key

##########################################################
#             SECOND LEVEL DOMAIN NAME (SLD)             #
##########################################################
#
# As some people may want to buy a domain name with a SLD
# (for instance ending with *.net.au or *.co.uk) we need to
# make sure that it is recognised as such.
#
# Below is a list of some of the most common sld
sld=".com.au,.net.au,.org.au,.com.br,.net.br,.co.jp,.co.uk,.org.uk,.co.za,.eu.com"
#
# If your use a SLD that's not on the list just put it below
#
#     for example: sld=.emp.br
#     (uncomment the line below if needed)
# sld=
#
##########################################################
#                DO NOT EDIT AFTER THIS                  #
##########################################################

function fqdn_slice {
    # We find the domain name which we'll be needing later in the script
    main_domain=$(echo $domain_name | awk -F. 'END {print $(NF-1)"."$NF}')
    if [ ! -z $(echo $sld | grep .$main_domain) ]
    then
        main_domain=$(echo $domain_name | awk -F. 'END {print $(NF-2)"."$(NF-1)"."$NF}')
    fi

    # The subdomain will also be useful
    subdomain=${domain_name//\.$main_domain/}
    if [ $domain_name == $main_domain ]
    then
        subdomain="@"
    fi
}

function install_run_gandi {
    print_info "install and start Gandi LiveDNS (dynamic IP)..."
    if [ -z "$gandi_api_key" ]
    then
        die "Gandi LiveDNS was not started because 'gandi_api_key' is empty in ddns/gandi.sh"
    else
        # We clone the git repository (if not already present)
        # Repository still exists as of March 2022...
        if [ ! -d /opt/gandi-automatic-dns ]
        then
            git clone https://github.com/brianreumere/gandi-automatic-dns.git /opt/gandi-automatic-dns
        fi
    fi
    print_info "First run of Gandi LiveDNS ddns script..."
    if [ -z $ip4 ]
    then
        die "IP address could not be retrieved. Check your internet connection"
    else
        echo $ip4 | /opt/gandi-automatic-dns/gad -5 -s -a $gandi_api_key -d $main_domain -r "$subdomain"
        if [ $? != 0 ]
        then
            die "Something went wrong, you should check you API key in ddns/gandi.sh"
        fi
        if [ $ip4 != $ip6 ]
        then
        echo $ip6 | /opt/gandi-automatic-dns/gad -5 -6 -s -a $gandi_api_key -d $main_domain -r "$subdomain"
        fi
    fi
}

function configure_cron_gandi {
    print_info "configure cron for Gandi LiveDNS..."
    # Use cron for dynamich ip update
    #   - at reboot
    #   - every 5 minutes
    grep "$main_domain".*"$subdomain" /etc/crontab
    if [ $? != 0 ]
    then
        echo "@reboot root curl ip4.me/ip/ | /bin/bash /opt/gandi-automatic-dns/gad -5 -s -a $gandi_api_key -d $main_domain -r \"$subdomain\" > /dev/null 2>&1" >> /etc/crontab
        echo "*/5 * * * * root curl ip4.me/ip/ | /bin/bash /opt/gandi-automatic-dns/gad -5 -s -a $gandi_api_key -d $main_domain -r \"$subdomain\" > /dev/null 2>&1" >> /etc/crontab
        if [ $ip4 != $ip6 ]
        then
            echo "@reboot root curl ip6.me/ip/ | /bin/bash /opt/gandi-automatic-dns/gad -5 -6 -s -a $gandi_api_key -d $main_domain -r \"$subdomain\" > /dev/null 2>&1" >> /etc/crontab
            echo "*/5 * * * * root curl ip6.me/ip/ | /bin/bash /opt/gandi-automatic-dns/gad -5 -6 -s -a $gandi_api_key -d $main_domain -r \"$subdomain\" > /dev/null 2>&1" >> /etc/crontab
        fi
    else
        print_info "cron for Gandi LiveDNS was configured already"
    fi
}

ip4=$(curl ip4.me/ip/)
ip6=$(curl ip6.me/ip/)

fqdn_slice
