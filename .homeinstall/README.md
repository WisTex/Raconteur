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

+ Fresh installation of Debian 9 (Stretch)
+ Router with open ports 80 and 443 for your Hub

## The basic steps (quick overview)

+ Register your own domain (for example at selfHOST) or a free subdomain (for example at freeDNS)
+ Log on to your fresh Debian
  - apt-get install git
  - mkdir -p /var/www
  - cd /var/www
  - git clone https://github.com/redmatrix/hubzilla.git html
  - cd /html/.homeinstall
  - cp hubzilla-config.txt.template hubzilla-config.txt
  - nano hubzilla-config.txt
    - Read the comments carefully
    - Enter your values: db pass, domain, values for dyn DNS
  - Make sure your external drive (for backups) is mounted
  - hubzilla-setup.sh as root
    - ... wait, wait, wait until the script is finised
  - reboot
+ Open your domain with a browser and step throught the initial configuration of hubzilla.

# Step-by-Step in Detail

## Preparations Hardware

### Mini-PC

### Recommended: USB Drive for Backups

The installation will create a daily backup written to an external drive.

The USB drive must be compatible with the filesystems

- ext4 (if you do not want to encrypt the USB) 
- LUKS + ext4 (if you want to encrypt the USB) 

The backup includes 

- Hubzilla DB
- Hubzilla installation /var/www/html
- Certificates for letsencrypt

## Preparations Software

### Install Debian Linux on the Mini-PC

Download the stable Debian at https://www.debian.org/  
(Debian 8 is no longer supported.)

Create bootable USB drive with Debian on it.You could use

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

There are two ways to get a domain...

### Method 1: Buy a Domain 

...for example buy at selfHOST.de  

The cost are around 10,- € once and 1,50 € per month (2017).

### Method 2 Register a (free) Subdomain

...for example register at freedns.afraid.org

Follow the instructions in .homeinstall/hubzilla-config.txt.  


## Install Hubzilla on your Debian

Login to your debian
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

Make sure your external drive (for backups) is plugged in and can be mounted as configured in "hubzilla-config.txt". Otherwise the daily backups will not work.

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

After the daily script was executed at 05:30 (am)

- look at var/www/html/hubzilla-daily.log
- check your backup on the external drive
- optionally view the daily log under yourdomain.org/admin/logs/
  - set the logfile to var/www/html/hubzilla-daily.log

## Note for the Rasperry 

The script was tested with an Raspberry 3 under Raspian (Debian 9.3, 2017-11-29-raspbian-stretch.img).

It is recommended to deinstall these programms to avoid endless updates. Use...

    sudo apt-get purge wolfram-engine sonic-pi
    sudo apt-get autoremove

It is recommended to run the Raspi without graphical frontend (X-Server). Use...

    sudo raspi-config

to boot the Rapsi to the client console.

DO NOT FORGET TO CHANGE THE DEFAULT PASSWORD FOR USER PI!


