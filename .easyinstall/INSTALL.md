# How to install your website

## Disclaimer

- This script does work with Debian 10 or 11 only.
- This script has to be used on a fresh debian install only (it does not take account for a possibly already installed and configured webserver or sql implementation). You may use it to install more than one website on the same computer as long as you use a single webserver.

## First step : setting up your system

After logging into your brand new Debian you will need to run the following commands (as root) :

    apt-get install git

Git will allow you to download the necessary software from the Streams repository.

    mkdir -p /var/www
    cd /var/www

This first creates the directory where your web server will find the install folder of your website, then goes to the directory.

    git clone https://codeberg.org/streams/streams.git mywebsite

This will download all the software you need in /var/www/mywebsite. You can replace "mywebsite" with any name you like (which you'll have to do if you plan to have more than one website running on your system)

    cd website/.easyinstall

This brings you to the subfolder where the tools are available, i.e. the setup script.

## Next step : installing your website

### a. The beginner-friendly way

Simply run the setup script :

    ./server-setup.sh

A series of dialog boxes will appear, in which you can enter the necessary information. There is a « Beginner » and an « Advanced » mode. The second one will allow you to access a few extra settings.

There are only four mandatory settings you need to provide : your domain name, your e-mail address, the webserver your will be using (Apache or Nginx), a password for your database. Once everything is ready, the actual install process will begin and you won’t have to do anything during the install.

### b. The (little) more advanced way

You can enter all your installation settings in a configuration file :

cp server-config.txt.template server-config.txt
nano server-config.txt

First be sure to read all the comments carefully. Then enter your values: database password, domain, e-mail, webserver, etc.. Then you can run the setup script :

./server-setup.sh

Then simply wait until the script is finished.

## Final step : 

Open your domain with a browser and step throught the initial configuration of your website. You will need to re-enter a few settings (database & password, admin e-mail…). You will then create your first user, starting with the admin is a great idea.

And that’s it, you can now log in your website and adding content to it!
