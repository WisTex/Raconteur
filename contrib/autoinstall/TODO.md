# TODO LIST

Here is the list of the improvements we need to work on. Any contribution will be greatly appreciated.

## Most important bugs

### Channel import/cloning when Nginx is the webserver

We need to find out what's wrong with Nginx and the channel import/cloning. Right now we have a 504 Timeout error whenever we try. There's probably something to do with php.ini and the website's Nginx conf file.

## Important improvements

### php.ini custom settings

For the moment, php.ini settings are global both with Apache & Nginx (respectively in /etc/php/8.2/apache2/php.ini and nano /etc/php/8.2/fpm/php.ini). There should be a way to have a it configured for each website individually.

Also, if php gets an update while the global php.ini was "manually" modified (i.e. with our setup script), it interrupts the daily-update script. Not cool.

## More improvement ideas

### Handling errors during the install

This autoinstall folder is mainly intended for people that are not used to managing their own server. If we could have error messages as explicit as possible, it would be very cool. Also, it would be nice to make sure that in case the install fails, we can easily rerun the script without having to manually clean things up or to reinstall the server.

### Using script on other distros that use *.deb packages (not only Debian)

We should probably be able to modify the check_sanity function and make it possible to use the script with Debian derivatives such as Ubuntu it an its flavors or Linux Mint (as long as requirements such as php 8.* available.

### Using script on other distros that use other packages (*.rpm for instance)

If we created a scripts/rpm.sh file, where we'd just have to create functions such as update_upgrade, check_install, nocheck_install and php_version usable with *.rpm packages, we could veryh easily support RedHat its derivative distros.
