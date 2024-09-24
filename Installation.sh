#!/bin/bash

# Update systeem
dnf update -y

# Installeer vereiste pakketten voor NGINX, Composer en MariaDB
dnf install -y epel-release wget curl git unzip

# NGINX installeren
dnf install -y nginx
systemctl enable nginx
systemctl start nginx

# Installeer specifieke PHP versie (8.1) en noodzakelijke extensies voor OroPlatform
dnf install -y php php-cli php-fpm php-mysqlnd php-json php-opcache php-xml php-mbstring php-zip php-gd php-bcmath php-intl php-soap php-xsl php-pdo php-pdo_mysql

# PHP-FPM configureren voor NGINX
sed -i 's/user = apache/user = nginx/g' /etc/php-fpm.d/www.conf
sed -i 's/group = apache/group = nginx/g' /etc/php-fpm.d/www.conf
systemctl enable php-fpm
systemctl start php-fpm

# Composer installeren
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f composer-setup.php

# Installeer MariaDB (versie 10.4+)
dnf install -y mariadb-server
systemctl enable mariadb
systemctl start mariadb

# MariaDB beveiligen
mysql_secure_installation <<EOF

y
strongpassword
strongpassword
y
y
y
y
EOF

# Installeer Node.js (vereist door Oro voor frontend assets)
dnf module install -y nodejs:18

# Configureer firewall om NGINX-verkeer toe te staan
firewall-cmd --permanent --zone=public --add-service=http
firewall-cmd --permanent --zone=public --add-service=https
firewall-cmd --reload

# Basis NGINX configuratie
cat > /etc/nginx/conf.d/proton.com.conf <<EOL
server {
    server_name proton.com www.proton.com;
    root /var/www/proton/public;

    location / {
        # try to serve file directly, fallback to index.php
        try_files \$uri /index.php\$is_args\$args;
    }

    # optionally disable falling back to PHP script for the asset directories;
    # nginx will return a 404 error when files are not found instead of passing the
    # request to Symfony (improves performance but Symfony's 404 page is not displayed)
    # location /bundles {
    #     try_files \$uri =404;
    # }

    location ~ ^/index\.php(/|$) {
        # Gebruik PHP-FPM als een unix socket
        fastcgi_pass unix:/var/run/php-fpm/www.sock;

        # Gebruik TCP als PHP-FPM geconfigureerd is met TCP
        # fastcgi_pass 127.0.0.1:9000;

        fastcgi_split_path_info ^(.+\.php)(/.*)\$;
        include fastcgi_params;

        # Optioneel: Stel de waarde in van omgevingsvariabelen gebruikt in de applicatie
        # fastcgi_param APP_ENV prod;
        # fastcgi_param APP_SECRET <app-secret-id>;
        # fastcgi_param DATABASE_URL "mysql://db_user:db_pass@host:3306/db_name";

        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        
        # Voorkomt toegang tot de front controller via URLs met index.php erin
        internal;
    }

    # Retourneer 404 voor alle andere PHP-bestanden die niet overeenkomen met de front controller
    location ~ \.php\$ {
        return 404;
    }

    error_log /var/log/nginx/proton_error.log;
    access_log /var/log/nginx/proton_access.log;
}
EOL

# Start NGINX opnieuw om wijzigingen toe te passen
systemctl restart nginx

# Maak de proton webdirectory
mkdir -p /var/www/proton/public
chown -R nginx:nginx /var/www/proton/public

echo "<?php phpinfo(); ?>" > /var/www/proton/public/index.php

echo "LEMP stack is succesvol geïnstalleerd met Proton Erp vereisten op CentOS 9!"
