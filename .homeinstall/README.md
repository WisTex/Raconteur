
# How to use

## Disclaimers

- This script does work with Debian 10 or 11 only.
- This script has to be used on a fresh debian install only (it does not take account for a possibly already installed and configured webserver or sql implementation). You may use it to ins$

## Preconditions

Hardware

+ Internet connection and router at home
+ Mini-pc connected to your router (a Raspberry 3 will do for very small Hubs)
+ USB drive for backups

Software

+ Fresh installation of Debian 11 (Bullseye) or Debian 10 (Buster)
+ Router with open ports 80 and 443 for your web server

You can of course run the script on a VPS or any distant server as long as the above sotfware requirements are satisfied.


## How to run the script

+ Register your own domain (for example at selfHOST) or a free subdomain (for example at freeDNS)
+ Log on to your fresh Debian
  - apt-get install git
  - mkdir -p /var/www
  - cd /var/www
  - git clone https://codeberg.org/streams/streams.git mywebsite (you can replace "mywebsite" with any name you like, which you'll have to do if you plan to have more than one hub/instance running on your server); if you plan to install a test server using "localhost" rather that a domain name, be sure to replace "mywebsite" with "html"
  - cd website/.homeinstall
  - cp server-config.txt.template server-config.txt
  - nano server-config.txt
    - Read the comments carefully
    - Enter your values: db pass, domain, values for dyn DNS
    - Prepare your external disk for backups
  - ./server-setup.sh as root
    - ... wait, wait, wait until the script is finished
+ Open your domain with a browser and step throught the initial configuration of your hub/instance.

## Optional - Set path to imagemagick

In Admin settings of your hub/server or via terminal

    cd /var/www/html
    util/config system.imagick_convert_path /usr/bin/convert

## Optional - Switch verification of email on/off

Do this just before you register the first user.

In Admin settings of your hub/instance or via terminal

    cd /var/www/html

Check the current setting 

    util/config system verify_email

Switch the verification on/off (1/0)

    util/config system verify_email 0

## What the script will do for you...

+ install everything required by your website, basically a web server (Apache or Nginx), PHP, a database (MySQL), certbot,...
+ create a database
+ run certbot to have everything for a secure connection (httpS)
+ create a script for daily maintenance
  - backup to external disk (certificates, database, /var/www/)
  - renew certfificate (letsencrypt)
  - update of your hub/instance (git)
  - update of Debian (it will also add sury repository for PHP 8.*)
  - restart
+ create cron jobs for
  - DynDNS (selfHOST.de or freedns.afraid.org) every 5 minutes
  - Run.php for your hub/instance every 10 minutes
  - daily maintenance script every day at 05:30

The script is known to work without adjustments with a Mini-PC or a VPS with Debian 11 (bullseye) or Debian 10 (buster) installed. It probably works but needs testing with the following:

+ Hardware
  - Rapberry 3 with Raspbian,
  - Rapberry 4 with Raspbian,
+ DynDNS
  - selfHOST.de
  - freedns.afraid.org

# Step-by-Step - some Details

## Preparations

## Configure your Router

Your webserver has to be visible in the internet.

Open the ports 80 and 443 on your router for your Debian. Make sure your web server is marked as "exposed host".

## Preparations Dynamic IP Address

Follow the instructions in .homeinstall/server-config.txt.

In short...

Your server must be reachable by a domain that you can type in your browser

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

It is recommended to run the Raspi without graphical frontend (X-Server). Use...

    sudo raspi-config

to boot the Raspi to the client console.

DO NOT FORGET TO CHANGE THE DEFAULT PASSWORD FOR USER PI!

## Reminder for Different Web Wervers

For those of you who feel adventurous enough to use a different web server (i.e. Lighttpd...), don't forget that this script will install Apache or Nginx and that you can only have one web server listening to ports 80 & 443. Also, don't forget to tweak your daily shell script in /var/www/ accordingly.
