#!/bin/bash

function update_upgrade {
    print_info "updated and upgrade..."
    # Run through the apt-get update/upgrade first. This should be done before
    # we try to install any package
    apt-get -q -y update && apt-get -q -y dist-upgrade
    print_info "updated and upgraded linux"
}

function check_install {
    if [ -z "`which "$1" 2>/dev/null`" ]
    then
        # export DEBIAN_FRONTEND=noninteractive ... answers from the package
        # configuration database
        # - q ... without progress information
        # - y ... answer interactive questions with "yes"
        # DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends -q -y install $2
        DEBIAN_FRONTEND=noninteractive apt-get -q -y install $2
        print_info "installed $2 installed for $1"
    else
        print_warn "$2 already installed"
    fi
}

function nocheck_install {
    declare DRYRUN=$(DEBIAN_FRONTEND=noninteractive apt-get install --dry-run $1 | grep Remv | sed 's/Remv /- /g')
    if [ -z "$DRYRUN" ]
    then
        # export DEBIAN_FRONTEND=noninteractive ... answers from the package configuration database
        # - q ... without progress information
        # - y ... answer interactive questions with "yes"
        # DEBIAN_FRONTEND=noninteractive apt-get --no-install-recommends -q -y install $2
        # DEBIAN_FRONTEND=noninteractive apt-get --install-suggests -q -y install $1
        DEBIAN_FRONTEND=noninteractive apt-get -q -y install $1
        print_info "installed $1"
    else
        print_info "Did not install $1 as it would require removing the following:"
        print_info "$DRYRUN"
        die "It seems you are not running this script on a fresh Debian GNU/Linux install. Please consider another installation method."
    fi
}

function install_sury_repo {
    # With Debian 11 (bullseye) we need an extra repo to install php 8.*
    if [[ -z $(grep -R "deb https://packages.sury.org/php/" /etc/apt/) ]]
    then
        print_info "installing sury-php repository..."
        apt-get -y install apt-transport-https
        curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
        sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury-php.list'
        apt-get update -y
    else
        print_info "sury-php repository is already installed."
    fi
}

function php_version {
    # We check that we can install the required version (8.2),
    print_info "checking that we can install the required PHP version (8.2)..."
    check_php=$(apt-cache show php8.2 | grep 'No packages found')
    if [ -z "$check_php" ]
    then
        print_info "We're good!"
    else
        die "something  went wrong, we can't install php8.2."
    fi
}

if [[ $os == "debian" ]]
then
    install_sury_repo
if

