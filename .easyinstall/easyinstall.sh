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
    whiptail \
        --title "Define skill level" \
        --yesno "How would you describe your computer skills?\n(You need to choose \"Advanced\" for a local test install)" \
        --yes-button "Beginner" --no-button "Advanced" \
        10 80

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        level=beginner
        enter_domain
    elif [ $exitstatus = 1 ]
    then
        if [ -f saved-config.txt ]
        then
            source saved-config.txt
            summary
        else
            enter_domain
        fi
    else
        die "Wokay, come back when you feel ready to test this!"
    fi
}

function show_saved {
    saved_summary="$summary_domain$summary_email$summary_webserver$summary_ddns_provider$summary_ddns_key$summary_ddns_id$summary_ddns_password$summary_db_pass$summary_db_namery_db_user$summary_db_name$summary_db_custompass"
    whiptail \
        --title "Saved configuration file was found" \
        --yesno "A previously saved configuration file was found in the .easyinstall folder, that contains the following settings:\n\n$saved_summary\n\nWould you like to use those settings (you can edit some of them)?" \
        --yes-button "Yes, Please" --no-button "No, Thanks" \
        20 80

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        source saved-config.txt
        using_saved=yes
        edit_settings
    elif [ $exitstatus = 1 ]
    then
        source server-config.txt.template
        beginner_advanced
    else
        die "Wokay, come back when you feel ready to test this!"
    fi
}

function enter_domain {
    if [ -z "$inputbox_domain" ]
    then
        if [ "$level" != "beginner" ]
        then
            inputbox_domain="What is your website's address/FQDN (Fully Qualified Domain Name)?\n(i.e. mywebsite.example.com, mywesbsite.net)\nYou can also use a local domain for testing\n(i.e. \"localhost\", \"testing\"...)"
        else
            inputbox_domain="Please enter your website's address/domain name\n(i.e. \"mywebsite.example.com\", \"mywesbsite.net\")"
        fi
    fi
    le_domain=$(whiptail \
        --title "Domain name" \
        --inputbox "$inputbox_domain" \
        12 80 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$le_domain" ]
        then
            inputbox_domain="You understand that if you put nothing here, you won't be able to have a website, right?\nPlease enter the domain name you plan to use for your website:"
            enter_domain
        else
            # Validate domain name
            if [[ "$le_domain" =~ $domain_regex ]]
            then
                summary_domain="Website address : https://$le_domain/\n"
                if [ -z "$using_saved" ]
                then
                    enter_email
                else
                    edit_settings
                fi
            else
                if [ "$level" != "beginner" ]
                then
                    if [[ "$le_domain" =~ $local_regex ]]
                    then
                        summary_domain="Local site address : http://$le_domain/\n"
                        if [ -z "$using_saved" ]
                        then
                            webserver_check
                        else
                            edit_settings
                        fi
                    else
                        inputbox_domain="\"$le_domain\" is not a valid FQDN or valid local domain for your test install. Please enter one of those now:"
                        enter_domain
                    fi
                else
                    inputbox_domain="\"$le_domain\" is not a valid address/domain name for your website. Please enter something that looks like \"example.com\" or \"subdomain.example.com\":"
                    enter_domain
                fi
            fi
        fi
    else
        die "Run the script again when you're ready to enter a valid domain name"
    fi
}

function enter_email {
    if [ -z "$inputbox_email" ]
    then
        inputbox_email="Please enter the e-mail address that will be use for your Let's Encrypt certificate request (and nothing else):"
    fi
    le_email=$(whiptail \
        --title "E-mail address (for Let's Encrypt)" \
        --inputbox "$inputbox_email" \
        10 60 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$le_email" ]
        then
            inputbox_email="The e-mail address is mandatory to obtain a Let's Encrypt certificate, so please enter one:"
            enter_email
        else
            # Validate email address structure
            email_regex="^[[:alnum:]._%+-]+@[[:alnum:].-]+\.[[:alpha:].]{2,4}$"

            if [[ "$le_email" =~ $email_regex ]]
            then
                summary_email="Mail address : $le_email\n"
                if [ -z "$using_saved" ]
                then
                    webserver_check
                else
                    edit_settings
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
    if [ "$(systemctl is-active nginx)" != "active" ]
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
        whiptail \
        --title "A web server is already running" \
        --msgbox "You already have $webserver_name web server running on this computer, it will also be used for this install. Or you can press Esc and solve this issue by yourself." \
        10 60

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            if [ -z "$using_saved" ]
            then
                ddns_choice
            else
                edit_settings
            fi
        else
            die "Wokay, come back when you feel ready to test this!"
        fi
    else
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

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        case "$which_web_server" in
        1) webserver=nginx
           summary_webserver="\nWeb server : Nginx\n\n"
           ddns_choice ;;
        2) webserver=apache
           summary_webserver="\nWeb server : Apache\n\n"
        esac
            ddns_choice
    else
        echo "vous avez annulé"
    fi
}

function ddns_choice {
    if [[ "$le_domain" =~ $domain_regex ]]
    then
        provider=$(whiptail \
            --title "Optional - Dynamic DNS configuration" \
            --menu "If you plan to use a Dynamic DNS (DDNS) provider, you may choose one here. Currently supported providers are FreeDNS, Gandi and selHOST.de. You must already have an account with the selected provider and own a domain/subdomain. Please choose one of the following options:"\
            18 80 5 \
            "1" "None, I won't be using a DDNS provider"\
            "2" "FreeDNS (offers free of charge subdomains)"\
            "3" "Gandi (French domain name registrar with a nice API)"\
            "4" "selfHOST.de (German language provider & registrar)"\
            "5" "Sorry, what now?" 3>&1 1>&2 2>&3)

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            case "$provider" in
            1) unset ddns_provider ddns_key ddns_id ddns_password summary_ddns_provider summary_ddns_key summary_ddns_id summary_ddns_password
               enter_db_pass ;;
            2|3|4) ddns_config ;;
            5) echo "Continuer vers des explications" ;;
            esac
        else
            echo "vous avez annulé"
        fi
    else
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
    then
        if [ -z "$inputbox_ddns_id" ]
        then
            inputbox_ddns_id="Please provide your $ddns_provider_name ID :"
        fi
        ddns_id=$(whiptail \
        --title "$ddns_provider_name ID" \
        --inputbox "$inputbox_ddns_id" \
        10 60 3>&1 1>&2 2>&3)

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            if [ -z "$ddns_id" ]
            then
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
                10 60 3>&1 1>&2 2>&3)

                exitstatus=$?
                if [ $exitstatus = 0 ]
                then
                    if [ -z "$ddns_password" ]
                    then
                        inputbox_ddns_password="You need a $ddns_provider_name password to finish your DDNS configuration:"
                        ddns_config
                    else
                        summary_ddns_password="$ddns_provider_name password : $ddns_password\n\n"
                        enter_db_pass
                    fi
                else
                    die "Run the script again when you're ready"
                fi
            fi
        else
            die "Run the script again when you're ready"
        fi
    else
        if [ -z "$inputbox_ddns_key" ]
        then
            inputbox_ddns_key="Please provide your $ddns_provider_name $ddns_key_type :"
        fi
        ddns_key=$(whiptail \
        --title "$ddns_provider_name $ddns_key_type" \
        --inputbox "$inputbox_ddns_key" \
        10 60 3>&1 1>&2 2>&3)

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            if [ -z "$ddns_key" ]
            then
                inputbox_ddns_key="You need a $ddns_provider_name $ddns_key_type to finish your DDNS configuration:"
                ddns_config
            else
                summary_ddns_key="$ddns_provider_name $ddns_key_type : $ddns_key\n\n"
                enter_db_pass
            fi
        else
            die "Run the script again when you're ready"
        fi
    fi
}

function enter_db_pass {
    db_pass=$(whiptail \
        --title "Set your database server main password" \
        --passwordbox "Enter your database server main password  and choose Ok to continue. If you leave the field empty a random password will be generated, you will be able to retrieve it later." \
        --cancel-button "Go Back" \
        10 60 3>&1 1>&2 2>&3)
    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ -z "$db_pass" ]
        then
            db_pass=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c${1:-16};echo;)
        fi
        summary_db_pass="Database main password : $db_pass\n"
        if [ "$level" != "beginner" ]
        then
            advanced_db
        else
            unset website_db_name summary_db_name website_db_user summary_db_user website_db_pass summary_db_custompass
            website_db_pass="$db_pass"
            summary
        fi
    else
        script_debut
    fi
}

function advanced_db {
    if (whiptail \
        --title "Advanced DB settings" \
        --yesno "Default setting is to use a single password for anything database related and to use the installation folder's name as database name and user. Do you wish to keep it that way or to customize those settings (database username, database name, database password)?" \
        --yes-button "Keep it simple" --no-button "Customize" \
        10 80)
    then
        summary
    else
        advanced_db_name
    fi
}

function advanced_db_name {
    if [ -z "$inputbox_db_name" ]
    then
        inputbox_db_name="Please enter your website database name, do not use spaces. If left empty it will be named as the install folder (here \"$install_folder\" as your install path is \"$install_path\"):"
    fi
    website_db_name=$(whiptail \
            --title "Website database name" \
            --inputbox "$inputbox_db_name" \
            10 60 3>&1 1>&2 2>&3)

    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ ! -z "$website_db_name" ]
        then
            # Validate database name
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
            advanced_db_user
        fi
    else
    advanced_db
    fi
}

function advanced_db_user {
    if [ -z "$inputbox_db_user" ]
    then
        inputbox_db_user="Please enter your website database username, do not use spaces. If left empty it will be named after the install folder (here \"$install_folder\" as your install path is \"$install_path\"):"
    fi
    website_db_user=$(whiptail \
            --title "Website database username" \
            --inputbox "$inputbox_db_user" \
            10 60 3>&1 1>&2 2>&3)

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
            advanced_db_pass
        fi
    else
        advanced_db
    fi
}

function advanced_db_pass {
    website_db_pass=$(whiptail \
        --title "Set your database custom password" \
        --passwordbox "Enter your database custom password  and choose Ok to continue. If you leave the field empty the database server main password will be used." \
        --cancel-button "Go Back" \
        10 60 3>&1 1>&2 2>&3)
    exitstatus=$?
    if [ $exitstatus = 0 ]
    then
        if [ ! -z "$website_db_pass" ]
        then
            summary_db_custompass="Website database password : $website_db_pass\n"
        fi
        summary
    else
        advanced_db
    fi
}


function summary {
    summary_display="$summary_domain$summary_email$summary_webserver$summary_ddns_provider$summary_ddns_key$summary_ddns_id$summary_ddns_password$summary_db_pass$summary_db_user$summary_db_name$summary_db_custompass"
    if [ ! -f saved-config.txt ]
    then
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
    else
        whiptail \
            --title "Saved configuration file was found" \
            --yesno "A previously saved configuration file was found in the .easyinstall folder, that contains the following settings:\n\n$summary_display\n\nWould you like to use those settings (you can edit some of them)?" \
            --yes-button "Yes, Please" --no-button "No, Thanks" \
            24 80

        exitstatus=$?
        if [ $exitstatus = 0 ]
        then
            using_saved=yes
            edit_settings
        elif [ $exitstatus = 1 ]
        then
            source server-config.txt.template
            for summary_item in ${summary_index[@]}; do
                unset $summary_item
            done
            enter_domain
        else
            die "Wokay, come back when you feel ready to test this!"
        fi
    fi
}

function save_settings {
    if (whiptail \
        --title "Optional - Save your settings" \
        --yesno "Would your like to save your settings?\nIf so, they'll be stored in saved-config.txt\n(You can re-use them later in advanced mode)" \
        --yes-button "Yes please" --no-button "No, thanks" \
        10 80)
    then
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
    echo "Time to edit settings"
}

function launch_setup {
    printf "$summary_domain$summary_local$summary_email$summary_webserver$summary_ddns_provider$summary_ddns_key$summary_db_pass$summary_db_user"
    echo $website_db_pass
}

summary_index=(summary_domain summary_email summary_webserver summary_ddns_provider summary_ddns_key summary_ddns_id summary_ddns_password summary_db_pass summary_db_name summary_db_user summary_db_custompass)

script_debut
