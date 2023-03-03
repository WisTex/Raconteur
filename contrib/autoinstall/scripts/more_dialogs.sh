#!/bin/bash

function enter_email {
    # A Let's Encrypt certificate will be requested for FQDN or domain name, an e-mail is needed 
    if [ -z "$inputbox_email" ]
    then
        inputbox_email="Please enter the e-mail address that will be use for your Let's Encrypt certificate request (and nothing else):"
    fi
    le_email=$(whiptail \
        --title "E-mail address (for Let's Encrypt)" \
        --inputbox "$inputbox_email" \
        10 60 $le_email 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$le_email" ]
        then
            inputbox_email="The e-mail address is mandatory to obtain a Let's Encrypt certificate, so please enter one:"
            enter_email
        else
            # Validate email address structure (we don't check if it atually exists)
            email_regex="^[[:alnum:]._%+-]+@[[:alnum:].-]+\.[[:alpha:].]{2,12}$"
            if [[ "$le_email" =~ $email_regex ]]
            then
                summary_email="Mail address : $le_email\n"
                webserver_check
            else
                inputbox_email="\"$le_email\" doesn't remotely look like an e-mail address. Please enter something that looks like \"someone@example.com\" or \"somebody@subdomain.example.com\":"
                enter_email
            fi
        fi
    else
        die "Not ready to try yet? Come back when you feel you are!"
    fi
}

function webserver_check {
    # Here we check if a Nginx or Apache webserver is already installed and running
    # We can't have both at the same time
    if [ "$(systemctl is-active nginx)" == "active" ]
    then
        webserver_name="a Nginx"
        webserver=nginx
        summary_webserver="\nWeb server : Nginx\n\n"
    elif [ "$(systemctl is-active apache2)" == "active" ]
    then
        webserver_name="an Apache"
        webserver=apache
        summary_webserver="\nWeb server : Apache\n\n"
    fi

    if [ ! -z "$webserver_name" ]
    then
        # If a running webserver is found, It will be used, there can't be another one installed
        whiptail \
        --title "A web server is already running" \
        --msgbox "You already have $webserver_name web server running on this computer, it will also be used for this install. Or you can press Esc and solve this issue by yourself." \
        10 60

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            ddns_choice
        else
            # In case the user presses the Esc key
            die "Brokay, come back when you feel ready to test this!"
        fi
    else
        # If no running webserver is found, we can choose one
        select_webserver
    fi
}

function select_webserver {
    which_web_server=$(whiptail \
        --title "Choose your web server" \
        --menu "Please choose the webserver you will be using:" \
        18 80 3 \
        "1" "Apache"\
        "2" "Nginx (EXPERIMENTAL - channel import/cloning doesn't work)" 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        case "$which_web_server" in
        1) webserver=apache
           summary_webserver="\nWeb server : Apache\n\n"
           ddns_choice ;;
        2) webserver=nginx
           summary_webserver="\nWeb server : Nginx\n\n"
           # After choosing the Web server, we need to check if Dynamic DNS will be needed
           ddns_choice
        esac
    else
        die "Trokay, come back when you feel ready to test this!"
    fi
}

function ddns_choice {
    # Only useful if we're not doing a local install
    if [ ! -z $local_install ]
    then
        enter_root_db_pass
    fi
    # We can automatically configure Dynamic DNS (DDNS) with a few providers
    # This is of course to be used only with a FQDN of domain name
    provider=$(whiptail \
        --title "Optional - Dynamic DNS configuration" \
        --menu "If you plan to use a Dynamic DNS (DDNS) provider, you may choose one here. Currently supported providers are FreeDNS, Gandi and selHOST.de. You must already have an account with the selected provider and own a domain/subdomain. Please choose one of the following options:"\
        18 80 4 \
        "1" "None, I won't be using a DDNS provider"\
        "2" "FreeDNS (offers free of charge subdomains)"\
        "3" "Gandi (French domain name registrar with a nice API)"\
        "4" "selfHOST.de (German language provider & registrar)" 3>&1 1>&2 2>&3)
        ### "5" "Sorry, what now?" 3>&1 1>&2 2>&3) ### Maybe an explanation short text about DDNS could be useful
    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        case "$provider" in
        # If no Dynamic DNS provider is used
        1) enter_root_db_pass ;;
        2|3|4) ddns_config ;;
            ### 5) ddns_ELIF ;; ### Could link to a short explanation text
        esac
    else
        die "Lost your way? Feel free to try again!"
    fi
}

function ddns_config {
    case "$provider" in
    2) ddns_provider=freedns
       ddns_provider_name="FreeDNS"
       ddns_key_type="update key" ;;
    3) ddns_provider=gandi
       ddns_provider_name="Gandi LiveDNS"
       ddns_key_type="API key" ;;
    4) ddns_provider=selfhost
       ddns_provider_name="selfHOST.de" ;;
    esac

    summary_ddns_provider="Dynamic DNS provider : $ddns_provider_name\n"

    if [ $provider == 4 ]
    # This is for selfHOST.de
    then
        if [ -z "$inputbox_ddns_id" ]
        then
            inputbox_ddns_id="Please provide your $ddns_provider_name ID :"
        fi
        ddns_id=$(whiptail \
        --title "$ddns_provider_name ID" \
        --inputbox "$inputbox_ddns_id" \
        10 60 $ddns_id 3>&1 1>&2 2>&3)

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            if [ -z "$ddns_id" ]
            then
                # We don't allow an empty ID
                inputbox_ddns_id="You need a $ddns_provider_name ID to finish your DDNS configuration:"
                ddns_config
            else
                summary_ddns_id="$ddns_provider_name ID : $ddns_id\n"
                if [ -z "$inputbox_ddns_password" ]
                then
                    inputbox_ddns_password="Please provide your $ddns_provider_name password :"
                fi
                ddns_password=$(whiptail \
                --title "$ddns_provider_name password" \
                --inputbox "$inputbox_ddns_password" \
                10 60 $ddns_password 3>&1 1>&2 2>&3)

                exitstatus=$?
                if [ $exitstatus = 0 ]
                then
                    if [ -z "$ddns_password" ]
                    then
                        # We don't allow an empty password
                        inputbox_ddns_password="You need a $ddns_provider_name password to finish your DDNS configuration:"
                        ddns_config
                    else
                        # If we swith from another DDNS provider settings , we need to unset some variables
                        unset ddns_key summary_ddns_key
                        summary_ddns_password="$ddns_provider_name password : $ddns_password\n\n"

                        enter_root_db_pass
                    fi
                else
                    # If Esc key is pressed
                    die "Run the script again when you're ready"
                fi
            fi
        else
            # If Esc key is pressed
            die "Run the script again when you're ready"
        fi
    else
    # The following part is for FreeDNS and Gandi which both only need a single key
        if [ -z "$inputbox_ddns_key" ]
        then
            inputbox_ddns_key="Please provide your $ddns_provider_name $ddns_key_type :"
        fi
        ddns_key=$(whiptail \
        --title "$ddns_provider_name $ddns_key_type" \
        --inputbox "$inputbox_ddns_key" \
        10 60 $ddns_key 3>&1 1>&2 2>&3)

       exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            if [ -z "$ddns_key" ]
            then
                inputbox_ddns_key="You need a $ddns_provider_name $ddns_key_type to finish your DDNS configuration:"
                ddns_config
            else
                # If we switch from a selfHOST.de configuration, we unset some variables
                unset ddns_id summary_ddns_id ddns_password summary_ddns_password
                summary_ddns_key="$ddns_provider_name $ddns_key_type : $ddns_key\n\n"

                enter_root_db_pass
            fi
        else
            # If Esc key is pressed
            die "Run the script again when you're ready"
        fi
    fi
}

function enter_root_db_pass {
    # We check if mysql is already installed
    if [ -z $(which mysql) ]
    then
        # If mysql is not installed we can go straight to choosing our website database name and user
        enter_db_pass
    else
        # If mysql is already installed we check if root needs a password to use mysql
        # On first run $opt_mysqlpass is empty
        mysql -h localhost -u root $opt_mysqlpass -e 'quit' &> /dev/null
        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            # 1st function call : if root needs no password we can directly choose our website database name and user
            # Next function calls, we do the same if the password entered is the right one
            enter_db_pass
        else
            # If root has a password configured for mysql it has to be entered here
            root_db_pass=$(whiptail \
            --title "MariaDB root password" \
            --passwordbox "Your MariaDB server has a password configured for root. Please enter it here. If you don't know it, please cancel and try again when you've found it." \
            --cancel-button "Cancel" \
            10 60 $root_db_pass 3>&1 1>&2 2>&3)
            exitstatus=$?
            if [ $exitstatus = 0 ]
            then
                opt_mysqlpass="-p$root_db_pass"
                enter_root_db_pass
            else
                die "Come back when when you've found root password for MariaDB"
            fi
        fi
    fi
}

