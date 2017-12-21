# Hubzilla at Home next to your Router

Run hubzilla-setup.sh for an unattended installation of hubzilla.

The script is known to work without adjustments with

+ Hardware
  - Mini-PC with Debian-9.2-amd64, or
  - Rapberry 3 with Raspbian, Debian-9.3
+ DynDNS
  - selfHOST.de
  - freedns.afraid.org

## Disclaimers

- This script does work with Debian 9 only.
- This script has to be used on a fresh debian install only (it does not take account for a possibly already installed and configured webserver or sql implementation).

# Step-by-Step Overwiew

## Preconditions

Hardware

+ Internet connection and router at home
+ Mini-pc connected to your router (a Raspberry 3 will do for very small Hubs)
+ USB drive for backups

Software

+ Fresh installation of Debian 9 (Stretch) on your mini-pc
+ Router with open ports 80 and 443 for your Debian

## The basic steps (quick overview)

+ Register your own domain (for example at selfHOST) or a free subdomain (for example at freeDNS)
+ Install Debian 9
+ On your router: Open the ports 80 and 443
+ Log on to your fresh Debian
  - apt-get install git
  - mkdir -p /var/www
  - cd /var/www
  - git clone https://github.com/redmatrix/hubzilla.git html
  - cp .homeinstall/hubzilla-config.txt.template .homeinstall/hubzilla-config.txt
  - nano .homeinstall/hubzilla-config.txt
    - Read the comments carefully
    - Enter your values: db pass, domain, values for dyn DNS
  - hubzilla-setup.sh as root
    - ... wait, wait, wait until the script is finised
  - reboot
+ Open your domain with a browser and step throught the initial configuration of hubzilla.

# Step-by-Step in Detail

## Preparations Hardware

### Mini-PC

### Recommended: USB Drive for Backups

The installation will create a daily backup.

If the backup process does not find an external device than the backup goes to
the internal disk.

The USB drive must be compatible with the filesystems

- ext4 (if you do not want to encrypt the USB) 
- LUKS + ext4 (if you want to encrypt the USB) 

## Preparations Software

### Install Debian Linux on the Mini-PC

Download the stable Debian 9 at https://www.debian.org/  
(Debian 8 is no longer supported.)

Create bootable USB drive with Debian on it. You could use

- unetbootin, https://en.wikipedia.org/wiki/UNetbootin
- or simply the linux command "dd"

Example for command dd...

    su -
    dd if=2017-11-29-raspbian-stretch.img of=/dev/mmcblk0

Do not forget to unmount the SD card before and check if unmounted like in this example...

    su -
    umount /dev/mmcblk0*
    df -h


Switch off your mini pc, plug in your USB drive and start the mini pc from the
stick. Install Debian. Follow the instructions of the installation.

### Configure your Router

Open the ports 80 and 443 on your router for your Debian

## Preparations Dynamic IP Address

Your Hubzilla must be reachable by a domain that you can type in your browser

    cooldomain.org

You can use subdomains as well

    my.cooldomain.org

There are two ways to get a domain

- buy a domain, or
- register a free subdomain

### Method 1: Buy an own Domain 

...for example buy at selfHOST.de  

The cost are around 10,- € once and 1,50 € per month (2017).

### Method 2 Register a (free) Subdomain

...for example register at freeDNS

Follow the instructions in .homeinstall/hubzilla-config.txt.  


## Install Hubzilla on your Debian

Login to your Debian
(Provided your username is "you" and the name of the mini pc is "debian". You
could take the IP address instead of "debian")

    ssh -X you@debian

Change to root user

    su -l

Install git

    apt-get install git

Make the directory for apache and change diretory to it

    mkdir /var/www
    cd /var/www/

Clone hubzilla from git ("git pull" will update it later)

    git clone https://github.com/redmatrix/hubzilla html

Change to the install script

    cd html/.homeinstall/
    
Copy the template file
    
    cp hubzilla-config.txt.template hubzilla-config.txt

Modify the file "hubzilla-config.txt". Read the instructions there carefully and enter your values.

    nano hubzilla-config.txt

Run the script

     ./hubzilla-setup.sh

Wait... The script should not finish with an error message.

In a webbrowser open your domain.
Expected: A test page of hubzilla is shown. All checks there should be
successfull. Go on...
Expected: A page for the Hubzilla server configuration shows up.

Leave db server name "127.0.0.1" and port "0" untouched.

Enter

- DB user name = hubzilla
- DB pass word = This is the password you entered in "hubzilla-config.txt"
- DB name = hubzilla

Leave db type "MySQL" untouched.

Follow the instructions in the next pages.

## Note for the Rasperry 

The script was tested with a Raspberry 3 under Raspian (Debian 9.3, 2017-11-29-raspbian-stretch.img).

Be patient when a page is loaded by your Raspi-Hub for the very first time. Especially the config pages after the install will load very slowly.

It is recommended to deinstall these programms to avoid endless updates. Use...

    sudo apt-get purge wolfram-engine sonic-pi
    sudo apt-get autoremove

It is recommended to run the Raspi without graphical frontend (X-Server). Use...

    sudo raspi-config

There choose "3 Boot Options" > "31 Desktop / CLI" > "B1 Console". Reboot.

**DO NOT FORGET TO CHANGE THE DEFAULT PASSWORD FOR USER PI!**


