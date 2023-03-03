#!/bin/bash

function add_vhost {
    print_info "adding apache vhost"
    echo "<VirtualHost *:80>" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "ServerName ${domain_name}" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "DocumentRoot $install_path" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "   <Directory $install_path>" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "       AllowOverride All" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "   </Directory>" >> "/etc/apache2/sites-available/${domain_name}.conf"
    echo "</VirtualHost>"  >> "/etc/apache2/sites-available/${domain_name}.conf"
    a2ensite $domain_name
    vhost_added=yes
}

function vhost_le {
    print_info "run certbot ..."
    certbot --apache -w $install_path -d $domain_name -m $le_email --agree-tos --non-interactive --redirect --hsts --uir
    service apache2 restart
    vhost_le_configured=yes
}

function nginx_conf_le {
    print_info "run certbot..."
    certbot certonly --nginx -d $domain_name -m $le_email --agree-tos --non-interactive
    cert="/etc/letsencrypt/live/$domain_name/fullchain.pem"
    cert_key="/etc/letsencrypt/live/$domain_name/privkey.pem"
}

function add_nginx_conf {
    print_info "adding nginx conf files"
    sed "s|SERVER_NAME|${domain_name}|g;s|INSTALL_PATH|${install_path}|g;s|SERVER_LOG|${domain_name}.log|;s|DOMAIN_CERT|${cert}|;s|CERT_KEY|${cert_key}|;" nginx-server.conf.template >> /etc/nginx/sites-available/${domain_name}.conf
    ln -s /etc/nginx/sites-available/${domain_name}.conf /etc/nginx/sites-enabled/
    nginx_conf=yes
    systemctl restart nginx
}

function webserver_conf {
    # We configure our webserver for our website
    if [[ $webserver = "nginx" ]]
    then
        if [ -z $local_install ]
        then
            nginx_conf_le
        fi
        add_nginx_conf
    elif [[ $webserver = "apache" ]]
    then
        add_vhost
        if [ -z $local_install ]
        then
            vhost_le
        fi
    fi
}

