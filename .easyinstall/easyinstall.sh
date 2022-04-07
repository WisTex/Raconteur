#!/bin/bash
function script_debut {
    whiptail \
        --title "Start your website installation" \
        --msgbox "So, you're ready to install your website? Very little information is required to start the configuration, this should take 2-3 minutes before the proper install can start." \
        10 60

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        beginner_advanced
    else
        die "Wokay, come back when you feel ready to test this!"
    fi
}

function beginner_advanced {
    # We define two skill levels, "Advanced" will allow the user to access more features
    # like installing with a local domain for testing or using previously saved settings
    whiptail \
        --title "Define skill level" \
        --yesno "How would you describe your computer skills?\n(You need to choose \"Advanced\" for a local test install or to use previously saved settings)" \
        --yes-button "Beginner" --no-button "Advanced" \
        10 80

    exitstatus=$?
    if [ $exitstatus = 0 ]
    # Beginner users go straight to domain configuration
    then
        level=beginner
        enter_domain
    elif [ $exitstatus = 1 ]
    # For advanced we can add an extra step
    then
        # If a file containing saved settings exists we import them immediatly
        # (we can get rid of them later if they are not to be used)
        if [ -f saved-config.txt ]
        then
            # If saved settings are found we import them and display them
            source saved-config.txt
            show_saved=yes
            summary
        else
            # No file found => we go to domain configuration
            enter_domain
        fi
    else
        die "Wokay, come back when you feel ready to test this!"
    fi
}

function enter_domain {
    # This is where the domain name is choosed
    if [ -z "$inputbox_domain" ]
    then
        if [ "$level" != "beginner" ]
        then
            inputbox_domain="What is your website's address/FQDN (Fully Qualified Domain Name)?\n(i.e. mywebsite.example.com, mywesbsite.net)\nYou can also use a local domain for testing\n(i.e. \"localhost\", \"testing\"...)"
        else
            inputbox_domain="Please enter your website's address/domain name\n(i.e. \"mywebsite.example.com\", \"mywebsite.net\")"
        fi
    fi
    le_domain=$(whiptail \
        --title "Domain name" \
        --inputbox "$inputbox_domain" \
        12 80 $le_domain 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$le_domain" ]
        then
            inputbox_domain="You need to put something here otherwise you won't be able to have a website.\nPlease enter the domain name you plan to use for your website:"
            enter_domain
        else
            # Validate domain name (we check if the input looks like a FQDN of a domain name not that it is an actual one)
            if [[ "$le_domain" =~ $domain_regex ]]
            then
                summary_domain="Website address : https://$le_domain/\n"
                # edit_mode is set only when saved settings are used AND edited
                if [ -z "$edit_mode" ]
                then
                    enter_email
                else
                    if [ -z "$le_email" ]
                    then
                        enter_email
                    else
                        summary
                    fi
                fi
            else
                # A beginner can only use a FQDN or domain name, not a local domain
                if [ "$level" != "beginner" ]
                then
                    # Only accepted local domain names accepted are simple ones (it's only for testing!)
                    if [[ "$le_domain" =~ $local_regex ]]
                    then
                        summary_domain="Local site address : http://$le_domain/\n"
                        if [ -z "$edit_mode" ]
                        then
                            webserver_check
                        else
                            # If saved settings are used but theres a change from FQDN to local domain, some variables need to be unset
                            unset le_email summary_email ddns_provider summary_ddns_provider ddns_id summary_ddns_id ddns_password summary_ddns_password ddns_key summary_ddns_key
                            summary
                        fi
                    else
                        # We change the message in the dialog box if there's no valid input
                        inputbox_domain="\"$le_domain\" is not a valid FQDN or valid local domain for your test install. Please enter one of those now:"
                        enter_domain
                    fi
                else
                    # Beginner intended new dialog box message
                    inputbox_domain="\"$le_domain\" is not a valid address/domain name for your website. Please enter something that looks like \"example.com\" or \"subdomain.example.com\":"
                    enter_domain
                fi
            fi
        fi
    else
        # In case the user has a change of mind and presses Esc key
        die "Run the script again when you're ready to enter a valid domain name"
    fi
}

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
                if [ -z "$edit_mode" ]
                then
                    webserver_check
                else
                    summary
                fi
            else
                inputbox_email="\"$le_email\" doesn't remotely look like an e-mail address. Please enter something that looks like \"someone@example.com\" or \"somebody@subdomain.example.com\":"
                enter_email
            fi
        fi
    else
        prod_or_local
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
            if [ -z "$edit_mode" ]
            then
                ddns_choice
            else
                summary
            fi
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
        "1" "Nginx (recommended for small servers)"\
        "2" "Apache (famous but heavier web server) " 3>&1 1>&2 2>&3)
        # Only two options here. An extra one with some explanations could be added later

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        # After choosing the Web server, we need to check if Dynamic DNS will be needed
        case "$which_web_server" in
        1) webserver=nginx
           summary_webserver="\nWeb server : Nginx\n\n"
           ddns_choice ;;
        2) webserver=apache
           summary_webserver="\nWeb server : Apache\n\n"
           ddns_choice
        esac
    else
        die "Trokay, come back when you feel ready to test this!"
    fi
}

function ddns_choice {
    # We can automatically configure Dynamic DNS (DDNS) with a few providers
    # This is of course to be used only with a FQDN of domain name
    if [[ "$le_domain" =~ $domain_regex ]]
    then
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
            1) if [ -z "$edit_mode" ]
               then
                   enter_db_pass
               else
                   # We unset a few variables in case we use saved settings that included DDNS configuration
                   unset ddns_provider ddns_key ddns_id ddns_password summary_ddns_provider summary_ddns_key summary_ddns_id summary_ddns_password
                   edit_settings
               fi ;;
            2|3|4) ddns_config ;;
            ### 5) ddns_ELIF ;; ### Could link to a short explanation text
            esac
        else
            die "Lost your way? Feel free to try again!"
        fi
    else
        # If don't use a FQDN, we can skip Dynamic DNS configuration
        enter_db_pass
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
                        if [ -z "$edit_mode" ]
                        then
                            enter_db_pass
                        else
                            summary
                        fi
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
                if [ -z "$edit_mode" ]
                then
                    enter_db_pass
                else
                    summary
               fi
            fi
        else
            # If Esc key is pressed
            die "Run the script again when you're ready"
        fi
    fi
}

function enter_db_pass {
    # Here we enter the MariaDB main password (will also be used as the default website's DB password)
    db_pass=$(whiptail \
        --title "Set your database server main password" \
        --passwordbox "Enter your database server main password  and choose Ok to continue. If you leave the field empty a random password will be generated, you will be able to retrieve it later." \
        --cancel-button "Go Back" \
        10 60 $db_pass 3>&1 1>&2 2>&3)
    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$db_pass" ]
        then
            # If no password is entered, we generate a random one
            db_pass=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c${1:-16};echo;)
        fi
        mysqlpass="$db_pass"
        website_db_pass="$db_pass"
        summary_db_pass="Database main password : $db_pass\n"
        if [ "$level" != "beginner" ]
        # Advanced users can access custom website's DB settings
        then
            advanced_db
        else
            # Is the following relevant? Not sure...
            unset website_db_name summary_db_name website_db_user summary_db_user website_db_pass summary_db_custompass
            website_db_pass="$db_pass"
            summary
        fi
    else
        die "Okay, come back when when you feel like going a little further."
    fi
}

function advanced_db {
    if (whiptail \
        --title "Advanced DB settings" \
        --yesno "Default setting is to use a single password for anything database related and to use the installation folder's name as database name and user. Do you wish to keep it that way or to customize those settings (database username, database name, database password)?" \
        --yes-button "Keep it simple" --no-button "Customize" \
        10 80)
    then
        # Just in case we are in saved-settings mode, we unset a few custom website DB variables here
        unset website_db_name summary_db_name website_db_user summary_db_user website_db_pass summary_db_custompass
        summary
    else
        advanced_db_name
    fi
}

function advanced_db_name {
    # Here we can set the website's database name (if left empty, main script will name it after the install folder's name)
    if [ -z "$inputbox_db_name" ]
    then
        inputbox_db_name="Please enter your website database name, do not use spaces. If left empty it will be named as the install folder (here \"$install_folder\" as your install path is \"$install_path\"):"
    fi
    website_db_name=$(whiptail \
            --title "Website database name" \
            --inputbox "$inputbox_db_name" \
            10 60 $website_db_name 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ ! -z "$website_db_name" ]
        then
            # Validate database name (we want it to be rather simple)
            db_name_regex="^([a-zA-Z0-9_]){2,25}$"
            if [[ "$website_db_name" =~ $db_name_regex ]]
            then
                summary_db_name="Website database name : $website_db_name\n"
                advanced_db_user
            else
                inputbox_db_name="Please enter a usable database name, or leave empty to use \"$install_folder\":"
                advanced_db_name
            fi
        else
            unset summary_db_name
            advanced_db_user
        fi
    else
    # If Esc key is pressed we go back to choosing if advanced DB settings are needed or not
    advanced_db
    fi
}

function advanced_db_user {
    # Here we can set the website's database user (if left empty, main script will name it after the install folder's name)
    if [ -z "$inputbox_db_user" ]
    then
        inputbox_db_user="Please enter your website database username, do not use spaces. If left empty it will be named after the install folder (here \"$install_folder\" as your install path is \"$install_path\"):"
    fi
    website_db_user=$(whiptail \
            --title "Website database username" \
            --inputbox "$inputbox_db_user" \
            10 60 $website_db_user 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ ! -z "$website_db_user" ]
        then
            # Validate database username
            db_user_regex="^([a-zA-Z0-9_]){2,25}$"
            if [[ "$website_db_user" =~ $db_user_regex ]]
            then
                summary_db_user="Website database username : $website_db_user\n"
                advanced_db_pass
            else
                inputbox_db_user="Please enter a usable database username, or leave empty to use \"$install_folder\":"
                advanced_db_user
            fi
        else
            unset summary_db_user
            advanced_db_pass
        fi
    else
        advanced_db
    fi
}

function advanced_db_pass {
    # Here we can set the website's database password (if left empty, main script will name it after the install folder's name)
    website_db_pass=$(whiptail \
        --title "Set your database custom password" \
        --passwordbox "Enter your database custom password  and choose Ok to continue. If you leave the field empty the database server main password will be used." \
        10 60 $website_db_pass 3>&1 1>&2 2>&3)
    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ ! -z "$website_db_pass" ]
        then
            summary_db_custompass="Website database password : $website_db_pass\n"
        else
            unset summary_db_custompass
        fi
        summary
    else
        # If Esc key is pressed
        advanced_db
    fi
}


function summary {
    # This will be used to display the settings, both new ones and/or previously saved ones
    summary_display="$summary_domain$summary_email$summary_webserver$summary_ddns_provider$summary_ddns_key$summary_ddns_id$summary_ddns_password$summary_db_pass$summary_db_user$summary_db_name$summary_db_custompass"
    # We will use this dialog box only once, to show previously saved settings
    if [ ! -z "$show_saved" ]
    then
        unset show_saved
        # Here we choose if we use the previously saved settings or not
        whiptail \
            --title "Saved configuration file was found" \
            --yesno "A previously saved configuration file was found in the .easyinstall folder, that contains the following settings:\n\n$summary_display\n\nWould you like to use those settings (you can edit some of them)?" \
            --yes-button "Yes, Please" --no-button "No, Thanks" \
            24 80

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            # Saved settings are already imported, we just need to ask if some need to be edited
            edit_settings
        elif [ $exitstatus = 1 ]
        then
            # If saved settings are not to be used, we unset every variable that could have been imported
            source server-config.txt.template
            for summary_item in ${summary_index[@]}; do
                unset $summary_item
            done
            # And then go back to the configuring everuthing from scratch
            enter_domain
        else
            # If Esc key is pressed
            die "Wokay, come back when you feel ready to test this!"
        fi
    else
        # At the end or the easyinstall script, of after editing a previously saved setting we display all settings
        if (whiptail \
            --title "Check your settings" \
            --yesno "$summary_display" \
            --yes-button "Continue" --no-button "Edit" \
            20 80)
        then
            save_settings
        else
            edit_settings
        fi
    fi
}

function save_settings {
    # This is where we choose if we save settings prior to running the main setup script
    if (whiptail \
        --title "Optional - Save your settings" \
        --yesno "Would your like to save your settings?\nIf so, they'll be stored in saved-config.txt\n(You can re-use them later in advanced mode)" \
        --yes-button "Yes please" --no-button "No, thanks" \
        10 60)
    then
        # We copy the config template and add the settings we want to save
        cp server-config.txt.template saved-config.txt
        sed -i "s/^db_pass=/db_pass=\"$db_pass\"/" saved-config.txt
        sed -i "s/^le_domain=/le_domain=$le_domain/" saved-config.txt
        sed -i "s/^le_email=/le_email=$le_email/" saved-config.txt
        sed -i "s/^webserver=/webserver=$webserver/" saved-config.txt
        sed -i "s/^ddns_provider=/ddns_provider=$ddns_provider/" saved-config.txt
        sed -i "s/^ddns_key=/ddns_key=$ddns_key/" saved-config.txt
        sed -i "s/^ddns_id=/ddns_id=$ddns_id/" saved-config.txt
        sed -i "s/^ddns_password=/ddns_password=$ddns_password/" saved-config.txt
        sed -i "s/^backup_device_name=/backup_device_name=$backup_device_name/" saved-config.txt
        sed -i "s/^backup_device_pass=/backup_device_pass=$backup_device_pass/" saved-config.txt
        sed -i "s/^website_db_name=/website_db_name=$website_db_name/" saved-config.txt
        sed -i "s/^website_db_user=/website_db_user=$website_db_user/" saved-config.txt
        if [ ! -z "$website_db_pass" ]
        then
            sed -i "s/^website_db_pass=\"\$db_pass\"/website_db_pass=\"$website_db_pass\"/" saved-config.txt
        fi

        # We had some stuff to display a nice summary after importing saved settings
        echo "" >> saved-config.txt
        echo "##########################################################" >> saved-config.txt
        echo "#                   SAVED SUMMARY                        #" >> saved-config.txt
        echo "##########################################################" >> saved-config.txt
        echo "" >> saved-config.txt
        for summary_item in ${summary_index[@]}; do
            printf '%s=\"%s\"\n' "$summary_item" "${!summary_item}" >> saved-config.txt
        done
        launch_setup
    else
        launch_setup
    fi
}

function edit_settings {
    edit_mode=yes
    edit_choice=$(whiptail \
        --title "Edit settings" \
        --menu "Do you want to change anything? Choose from the options below:" \
        15 80 6  \
        "1" "We're good, nothing needs to be changed"\
        "2" "Website domain/address"\
        "3" "E-mail address"\
        "4" "Dynamic DNS" \
        "5" "Database settings"  3>&1 1>&2 2>&3)
        # "6" "Backup settings" 3>&1 1>&2 2>&3) # This needs to be done

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        case "$edit_choice" in
        1) summary ;;
        2) enter_domain ;;
        3) enter_email;;
        4) ddns_choice ;;
        5) enter_db_pass ;;
        6) backup_settings ;;
        esac
    else
        # if Esc key is pressed
        summary
    fi
}

function launch_setup {
    whiptail \
        --title "Launch setup" \
        --msgbox "Everything is now ready for the installation of your website. Press \"OK\" to start the automated installation (you can press Esc to cancel)". \
        10 60

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        echo "Website setup will now continue"
    else
        die "Too bad, you were all set... Come back when you feel ready to test this!"
    fi
}

summary_index=(summary_domain summary_email summary_webserver summary_ddns_provider summary_ddns_key summary_ddns_id summary_ddns_password summary_db_pass summary_db_name summary_db_user summary_db_custompass)

script_debut
