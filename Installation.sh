#!/bin/bash

# Update systeem
dnf update -y

# Installeer vereiste pakketten voor NGINX, Composer, en MariaDB
dnf install -y epel-release wget curl git unzip

# NGINX installeren
dnf install -y nginx
systemctl enable nginx
systemctl start nginx

# Installeer specifieke PHP versie (8.1) en noodzakelijke extensies voor OroPlatform en BLEU Proton
dnf install -y php php-cli php-fpm php-mysqlnd php-json php-opcache php-xml php-mbstring php-zip php-gd php-bcmath php-intl php-soap php-xsl php-pdo php-pdo_mysql php-tokenizer php-process

# PHP-FPM configureren voor NGINX
sed -i 's/user = apache/user = nginx/g' /etc/php-fpm.d/www.conf
sed -i 's/group = apache/group = nginx/g' /etc/php-fpm.d/www.conf
systemctl enable php-fpm
systemctl start php-fpm

# Composer installeren
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f composer-setup.php

# Installeer MariaDB (versie 10.4+ zoals vereist door OroPlatform)
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

# Installeer Node.js (vereist door OroPlatform voor frontend assets)
dnf module install -y nodejs:18

# Installeer OroPlatform vereisten (Symfony- en Oro-specifieke)
dnf install -y php-imagick redis

# Configureer MariaDB database en gebruiker voor OroPlatform
mysql -uroot -pstrongpassword -e "CREATE DATABASE oro_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -pstrongpassword -e "CREATE USER 'oro_user'@'localhost' IDENTIFIED BY 'oro_password';"
mysql -uroot -pstrongpassword -e "GRANT ALL PRIVILEGES ON oro_db.* TO 'oro_user'@'localhost';"
mysql -uroot -pstrongpassword -e "FLUSH PRIVILEGES;"

# BLEU Proton en OroPlatform installeren
cd /var/www/
git clone https://github.com/bleuproton/proton.git
cd proton
composer install

# OroPlatform setup uitvoeren
# Configureer de .env bestand met database en andere instellingen
cp .env .env.local
sed -i 's/DATABASE_URL=.*/DATABASE_URL="mysql:\/\/oro_user:oro_password@127.0.0.1:3306\/oro_db"/g' .env.local

# Installeer de database en assets van OroPlatform
php bin/console oro:install --env=prod --timeout=0 --user-name=admin --user-email=admin@example.com --user-firstname=Admin --user-lastname=User --user-password=admin --application-url=http://example.com

# Configureer firewall om NGINX-verkeer toe te staan
firewall-cmd --permanent --zone=public --add-service=http
firewall-cmd --permanent --zone=public --add-service=https
firewall-cmd --reload

# NGINX configuratie voor OroPlatform
cat > /etc/nginx/conf.d/example.com.conf <<EOL
# /etc/nginx/conf.d/example.com.conf
server {
    server_name example.com www.example.com;
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
        # when using PHP-FPM as a unix socket
        fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;

        # when PHP-FPM is configured to use TCP
        # fastcgi_pass 127.0.0.1:9000;

        fastcgi_split_path_info ^(.+\.php)(/.*)\$;
        include fastcgi_params;

        # optionally set the value of the environment variables used in the application
        # fastcgi_param APP_ENV prod;
        # fastcgi_param APP_SECRET <app-secret-id>;
        # fastcgi_param DATABASE_URL "mysql://db_user:db_pass@host:3306/db_name";

        # When you are using symlinks to link the document root to the
        # current version of your application, you should pass the real
        # application path instead of the path to the symlink to PHP
        # FPM.
        # Otherwise, PHP's OPcache may not properly detect changes to
        # your PHP files (see https://github.com/zendtech/ZendOptimizerPlus/issues/126
        # for more information).
        # Caveat: When PHP-FPM is hosted on a different machine from nginx
        #         \$realpath_root may not resolve as you expect! In this case try using
        #         \$document_root instead.
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        # Prevents URIs that include the front controller. This will 404:
        # http://example.com/index.php/some-path
        # Remove the internal directive to allow URIs like this
        internal;
    }

    # return 404 for all other php files not matching the front controller
    # this prevents access to other php files you don't want to be accessible.
    location ~ \.php\$ {
        return 404;
    }

    error_log /var/log/nginx/project_error.log;
    access_log /var/log/nginx/project_access.log;
}
EOL

# Start NGINX opnieuw om wijzigingen toe te passen
systemctl restart nginx

# Stel permissies in voor de noodzakelijke directories van OroPlatform
chown -R nginx:nginx /var/www/proton
chmod -R 755 /var/www/proton

# Zorg ervoor dat de volgende directories schrijfbaar zijn door zowel de webserver als de command-line user
chmod -R 775 /var/www/proton/var/sessions
chmod -R 775 /var/www/proton/var/cache
chmod -R 775 /var/www/proton/var/data
chmod -R 775 /var/www/proton/var/logs
chmod -R 775 /var/www/proton/public/media
chmod -R 775 /var/www/proton/public/js

# Cronjobs instellen voor OroPlatform (nodig voor zoekindex en e-mail verwerking)
echo "* * * * * nginx /usr/bin/php /var/www/proton/bin/console oro:cron --env=prod" >> /etc/crontab

echo "LEMP stack met BLEU Proton en OroPlatform is succesvol geïnstalleerd op CentOS 9!"
