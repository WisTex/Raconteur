# How to install your website

## Disclaimer

- This script does work with Debian GNU/Linux 11 only.
- This script has to be used on a fresh debian install only (it does not take account for a possibly already installed and configured webserver or sql implementation). You may use it to install more than one website on the same computer as long as you use a single webserver.

## First step: setting up your system

After logging into your brand new Debian you will need to run the following commands (as root):

    apt-get install git

Git will allow you to download the necessary software from the Streams repository.

    mkdir -p /var/www
    cd /var/www

This first creates the directory where your web server will find the install folder of your website, then goes to the directory.

    git clone https://codeberg.org/streams/streams.git mywebsite

This will download all the software you need in /var/www/mywebsite. You can replace "mywebsite" with any name you like (which you'll have to do if you plan to have more than one website running on your system)

    cd mywebsite/contrib/autoinstall

This brings you to the subfolder where the tools are available, i.e. the setup script.

## Next step: installing your website

Simply run the setup script:

    ./autoinstall.sh

A series of dialog boxes will appear, in which you can enter the necessary information.

Using Nginx as the webserver is not the best choice if you plan to clone or import an existing channel hosted on another website: it will most likely not work.

On a freshly installed Debian server, there are only four mandatory settings you need to provide: your domain name, your e-mail address, the webserver your will be using (Apache or Nginx), a password for your website's database (if you choose to use a randomly generated password, remember you'll have to use it a little later, so take note of it when the install summary is displayed). Once everything is ready, the actual install process will begin. You should not have anything else to do until your website is installed.

## Final step 

Open your domain with a browser and step throught the initial configuration of your website. You will need to re-enter a few settings (database name, user and password, admin e-mail…). You will then create your first user, starting with the admin is a great idea.

And that’s it, you can now log in your website and start adding content to it!

## Local install

It is possible to install your website locally (i.e. for testing purposes). You just have to add an option when lauching the command:

    ./autoinstall --local

There will be no https or ddns stuff in the dialogs. If you don't use "localhost" but want a custom local domain, don't forget to add it in /etc/hosts.
