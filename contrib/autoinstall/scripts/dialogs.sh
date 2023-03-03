#!/bin/bash
function script_debut {
    # First we check if we're running the script on a freshly installed Debian 11 server
    if [[ $os == "debian" ]]
    then
        if [[ ! -z "$(which php)" ]] || [[ ! -z "$(which mysql)" ]] || [[ ! -z "$(which apache)" ]] || [[ ! -z "$(which nginx)" ]]
        then
            warning_no_fresh
        fi
    fi

    whiptail \
        --title "Start your website installation" \
        --msgbox "So, you're ready to install your website? Very little information is required to start the configuration, this should take 2 minutes tops before the proper install can start." \
        10 60

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        enter_domain
    else
        die "Wokay, come back when you feel ready to test this!"
    fi
}

function warning_no_fresh {
    if (whiptail \
        --title "WARNING: Not a fresh Debian install" \
        --yesno "Hi there, you are not running this script on a freshly installed Debian 11 server. If you choose to continue, this might break your system to the point that you won't be able to fix it, and this would be entirely your fault because, you know, we told you so. Do you want to continue anyway?" \
        --yes-button "Yes" --no-button "No" \
        12 80)
    then
        print_info "Running the script on your server"
    else
        print_info "Nothing was installed on your server"
        exit 0
    fi
}

function enter_domain {
    # This is where the domain name is choosed
    if [ -z "$inputbox_domain" ]
    then
        if [ -z $local_install ]
        then
            inputbox_domain="Please enter your website's address/domain name\n(i.e. \"mywebsite.example.com\", \"example.com\")"
        else
            inputbox_domain="Please enter a local domain for testing\n(i.e. \"localhost\", \"testing\"...)"
        fi
    fi
    domain_name=$(whiptail \
        --title "Domain name" \
        --inputbox "$inputbox_domain" \
        12 80 $domain_name 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$domain_name" ]
        then
            inputbox_domain="You need to put something here otherwise you won't be able to configure a website.\nPlease enter the domain name you plan to use for your website:"
            enter_domain
        else
            source scripts/more_dialogs.sh
            if [ -z $local_install ]
            then
                # Validate domain name (we check if the input looks like a FQDN of a domain name not that it is an actual one)
                if [[ "$domain_name" =~ $domain_regex ]]
                then
                    enter_email
                else
                    inputbox_domain="\"$domain_name\" is not a valid address/domain name for your website. Please enter something that looks like \"example.com\" or \"subdomain.example.com\":"
                    enter_domain
                fi
            else
                if [[ "$domain_name" =~ $local_regex ]]
                then
                    webserver_check
                else
                    # We change the message in the dialog box if there's no valid input
                    inputbox_domain="\"$domain_name\" is not a valid local domain for your test install. Please enter one now:"
                    enter_domain
                fi
            fi
        fi
    else
        # In case the user has a change of mind and presses Esc key
        die "Run the script again when you're ready to enter a valid domain name"
    fi
}

function enter_db_pass {
    # Here we enter the MariaDB main password (will also be used as the default website's DB password)
    website_db_pass=$(whiptail \
        --title "Set your database password" \
        --passwordbox "Enter your website database password and choose \"OK\"continue. If you leave the field empty a random password will be generated, you will be able to retrieve it later." \
        --cancel-button "Go Back" \
        10 60 $website_db_pass 3>&1 1>&2 2>&3)
    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$website_db_pass" ]
        then
            # If no password is entered, we generate a random one
            website_db_pass=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c${1:-16};echo;)
        fi
        advanced_db
    else
        die "Okay, come back when when you feel like going a little further."
    fi
}

function advanced_db {
    if (whiptail \
        --title "Advanced DB settings" \
        --yesno "Default setting is to use the installation folder's name as database name and user (i.e. if your website is installed in /var/www/social, database name and user will be \"social\". Do you wish to keep it that way or to customize those settings (database name and database user)?" \
        --yes-button "Keep it simple" --no-button "Customize" \
        10 80)
    then
        website_db_name=$install_folder
        website_db_user=$install_folder
        db_name_check
        db_user_check
        summary
    else
        advanced_db_name
    fi
}

function advanced_db_name {
    # Here we can set the website database name (if left empty, main script will name it after the install folder's name)
    if [ -z "$inputbox_db_name" ]
    then
        inputbox_db_name="Please enter your website database name, you can only use letters, numbers and \"_\" (do not use spaces). If left empty it will be named as the install folder (here \"$install_folder\" as your install path is \"$install_path\"):"
    fi
    website_db_name=$(whiptail \
            --title "Website database name" \
            --inputbox "$inputbox_db_name" \
            10 80 $website_db_name 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ ! -z "$website_db_name" ]
        then
            # Validate database name (we want it to be rather simple)
            db_name_regex="^([a-zA-Z0-9_]){2,25}$"
            if [[ "$website_db_name" =~ $db_name_regex ]]
            then
                db_name_check
                if [ ! -z "$website_db_user" ]
                    db_user_check
                then
                    advanced_db_user
                fi
            else
                unset website_db_name
                inputbox_db_name="Please enter a usable database name (or leave empty to use \"$install_folder\":"
                advanced_db_name
            fi
        else
            website_db_name=$install_folder
            db_name_check
            advanced_db_user
        fi
    else
        # If Esc key is pressed we go back to choosing if advanced DB settings are needed or not
        advanced_db
    fi
}

function db_name_check {
    # Make sure we don't write over an already existing database if we install more than one Streams website with this script
    if [[ ! -z $(mysql -h localhost -u root $opt_mysqlpass -e "SHOW DATABASES;" | grep -w "$website_db_name") ]]
    then
        inputbox_db_name="A database named \"$website_db_name\" already exists, please choose another name:"
        unset website_db_name
        advanced_db_name
    fi
}

function advanced_db_user {
    # Here we can set the website database user (if left empty, main script will name it after the install folder's name)
    if [ -z "$inputbox_db_user" ]
    then
        inputbox_db_user="Please enter your website database username, do not use spaces. If left empty it will be named after the install folder (here \"$install_folder\" as your install path is \"$install_path\"):"
    fi
    website_db_user=$(whiptail \
            --title "Website database username" \
            --inputbox "$inputbox_db_user" \
            10 80 $website_db_user 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ ! -z "$website_db_user" ]
        then
            # Validate database username
            db_user_regex="^([a-zA-Z0-9_]){2,25}$"
            if [[ "$website_db_user" =~ $db_user_regex ]]
            then
                db_user_check
                summary
            else
                unset website_db_user
                inputbox_db_user="Please enter a usable database username, or leave empty to use \"$install_folder\":"
                advanced_db_user
            fi
        else
            website_db_user=$install_folder
            db_user_check
            summary
        fi
    else
        # If Esc key is pressed
        die "Okay, come back when when you feel like going a little further."
    fi
}

function db_user_check {
    # Make sure we don't use an already existing database user if we install more than one website
    if [[ ! -z $(mysql -h localhost -u root $opt_mysqlpass -e "use mysql; SELECT user FROM user;" | grep -w "$website_db_user") ]]
    then
        inputbox_db_user="A mysql user named \"$website_db_user\" already exists, please choose another name:"
        unset website_db_user
        advanced_db_user
    fi
    # - Hey why wouldn't we allow the user to choose an already existing mysql user name?
    # - Because this script is intended for n00bs and we don't want it to break anything
    #   Feel free to comment the db_user_check command if you are sure to know what you're doing,
    #   but don't come crying after that if you break your system.
}

function summary {
    if [ -z $local_install ]
    then
        summary_domain="Website address   :   https://$domain_name/\n\n"
    else
        summary_domain="Website address   :   http://$domain_name/\n\n"
    fi
    summary_db_pass="Website database password   :   $website_db_pass\n"
    summary_db_name="Website database name       :   $website_db_name\n"
    summary_db_user="Website database user       :   $website_db_user\n"
    # This will be used to display the settings for our install
    summary_display="$summary_domain$summary_email$summary_webserver$summary_ddns_provider$summary_ddns_key$summary_ddns_id$summary_ddns_password$summary_db_pass$summary_db_name$summary_db_user"
    # We display all settings
    if (whiptail \
        --title "Check your settings" \
        --yesno "$summary_display" \
        --yes-button "Continue" --no-button "Start over" \
        20 80)
    then
        launch_install
    else
        # Reset all settings before sarting over. We keep domain name, email address for Let's Encrypt
        # and mysql root, which will most likely remain the same
        unset webserver summary_webserver
        unset ddns_provider ddns_provider_name summary_ddns_provider
        unset ddns_key_type ddns_key summary_ddns_key
        unset ddns_id ddns_password summary_ddns_id summary_ddns_password
        unset website_db_pass website_db_name website_db_user
        enter_domain
    fi
}

function launch_install {
    whiptail \
        --title "Launch install" \
        --msgbox "Everything is now ready for the installation of your website. Press \"OK\" to start the automated installation (you can press Esc to cancel)". \
        10 80

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        print_info "Website install will now start"
    else
        die "Too bad, you were all set... Come back when you feel ready to test this!"
    fi
}

function final_message {
    final_summary=$summary_domain$summary_db_user$summary_db_pass$summary_db_name
    whiptail \
        --title "Website successfully installed" \
        --msgbox "Your website was successfully installed. You must now visit https://$domain_name with your web browser to finish the setup. You will need the following:\n\n$final_summary" \
        15 80
    print_info "Website successfully installed"
    print_info "$final_summary"
}


domain_regex="^([a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]\.)+[a-zA-Z]{2,}$"
local_regex="^([a-zA-Z0-9]){2,25}$"

# set -x
script_debut
