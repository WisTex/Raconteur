# How to use

## Disclaimers

- This script does work with Debian 10 only.
- This script has to be used on a fresh debian install only (it does not take account for a possibly already installed and configured webserver or sql implementation).

## Preconditions

Hardware

+ Internet connection and router at home
+ Mini-pc connected to your router (a Raspberry 3 will do for very small Hubs)
+ USB drive for backups

Software

+ Fresh installation of Debian 10 (Stretch)
+ Router with open ports 80 and 443 for your web server

## How to run the script

+ Register your own domain (for example at selfHOST) or a free subdomain (for example at freeDNS)
+ Log on to your fresh Debian
  - apt-get install git
  - mkdir -p /var/www
  - cd /var/www
  - git clone https://framagit.org/zot/zap.git html
  - cd html/.homeinstall
  - cp hubzilla-config.txt.template hubzilla-config.txt
  - nano hubzilla-config.txt
    - Read the comments carefully
    - Enter your values: db pass, domain, values for dyn DNS
    - Prepare your external disk for backups
  - hubzilla-setup.sh as root
    - ... wait, wait, wait until the script is finised
+ Open your domain with a browser and step throught the initial configuration of hubzilla.

## Optional - Set path to imagemagick

In Admin settings of hubzilla or via terminal

    cd /var/www/html
    util/config system.imagick_convert_path /usr/bin/convert

## Optional - Switch verification of email on/off

Do this just befor you register the first user.

In Admin settings of hubzilla or via terminal

    cd /var/www/html

Check the current setting 

    util/config system verify_email

Switch the verification on/off (1/0)

    util/config system verify_email 0

## What the script will do for you...

+ install everything required by Hubzilla, basically a web server (Apache), PHP, a database (MySQL), certbot,...
+ create a database
+ run certbot to have everything for a secure connection (httpS)
+ create a script for daily maintenance
  - backup to external disk (certificates, database, /var/www/)
  - renew certfificate (letsencrypt)
  - update of Hubzilla
  - update of Debian
  - restart
+ create cron jobs for
  - DynDNS (selfHOST.de or freedns.afraid.org) every 5 minutes
  - Run.php for Zap/Hubzilla every 10 minutes
  - daily maintenance script every day at 05:30

The script is known to work without adjustments with

+ Hardware
  - Mini-PC with Debian 10 (stretch), or
  - Rapberry 3 with Raspbian, Debian 10
  - Rapberry 4 with Raspbian, Debian 10
+ DynDNS
  - selfHOST.de
  - freedns.afraid.org

The script can install both [Hubzilla](https://zotlabs.org/page/hubzilla/hubzilla-project) and [Zap](https://zotlabs.com/zap/). Make sure to use the correct GIT repositories.  


# Step-by-Step - some Details

## Preparations

## Configure your Router

Your webserver has to be visible in the internet.  

Open the ports 80 and 443 on your router for your Debian. Make sure your web server is marked as "exposed host".

## Preparations Dynamic IP Address

Follow the instructions in .homeinstall/hubzilla-config.txt.  

In short...  

Your Hubzilla must be reachable by a domain that you can type in your browser

    cooldomain.org

You can use subdomains as well

    my.cooldomain.org

There are two ways to get a domain...

### Method 1: Buy a Domain 

...for example buy at selfHOST.de  

The cost is 1,50 € per month (2019).

### Method 2: Register a free subdomain

...for example register at freedns.afraid.org

## Note on Rasperry 

The script was tested with a Raspberry 3 and a Raspberry 4 both with Raspian, Debian 10.

It is recommended to run the Raspi without graphical frontend (X-Server). Use...

    sudo raspi-config

to boot the Rapsi to the client console.

DO NOT FORGET TO CHANGE THE DEFAULT PASSWORD FOR USER PI!



