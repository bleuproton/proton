---

Proton ERP Unified Commerce Management**  
-----------

Proton ERP provides powerful capabilities to meet and exceed the evolving demands of modern commerce. It enables businesses to efficiently manage multiple sales channels, offering real-time visibility into B2C and B2B orders, inventory, fulfillment, customer data, and more. Proton ERP's unified approach allows seamless shopping experiences, letting customers buy anywhere, fulfill anywhere, and return anywhere—all within one integrated system that serves as a single source of truth for your business.

This document outlines how to download, install, and start using Proton ERP.

## Requirements

Proton ERP is built on Symfony 5.4 and has the following requirements:

* **PHP 8.2** or above, with the command-line interface
* PHP Extensions:
    * `ctype`
    * `curl`
    * `fileinfo`
    * `gd`
    * `intl` (ICU library 4.4 and above)
    * `json`
    * `mbstring`
    * `sodium`
    * `openssl`
    * `pcre`
    * `simplexml`
    * `tokenizer`
    * `xml`
    * `zip`
    * `imap`
    * `soap`
    * `bcmath`
    * `ldap`
    * `pgsql`
* **PostgreSQL 15.1**

## Installation Instructions

Since both Symfony and Proton ERP use [Composer][1] to manage dependencies, it’s recommended to use Composer for the installation process.

1. **Clone the Proton ERP application repository:**

```bash
git clone -b x.y.z https://github.com/bleuproton/proton.git
```

Replace `x.y.z` with the latest [release tag](https://github.com/bleuproton/proton/releases), or use the latest master branch:

```bash
git clone https://github.com/bleuproton/proton.git
```

2. **Install [Composer][1] globally** by following the official Composer installation documentation.

3. **Ensure that you have [Node.js][4]** (>=18.14.0, <19) and NPM (>=9.3.1, <10) installed.

4. **Install Proton ERP dependencies** using Composer. If the installation is slow, you can use the `--prefer-dist` option. In the Proton ERP application folder, run the following command:

```bash
composer install --prefer-dist --no-dev
```

5. **Create the database** using the name you specified during setup (default: "proton_application").

6. **Increase PHP memory limit (temporarily, if needed)** to 1GB in the `php.ini` file for the installation process:

```bash
memory_limit=1024M
```

> After the installation, you can revert the `memory_limit` to the recommended 512MB or more.

7. **Install Proton ERP and create the admin user** using the Installation Wizard by opening `install.php` in the browser or by running the following from the CLI:

```bash  
php bin/console oro:install --env prod
```

> If the installation times out, add the `--timeout=0` argument to the command.

8. **Enable WebSockets messaging** by running:

```bash
php bin/console gos:websocket:server --env prod
```

9. **Configure crontab or scheduled tasks** to execute the following command every minute:

```bash
php bin/console oro:cron --env prod
```

10. **Start the message queue processing** with the following command:

```bash
php bin/console oro:message-queue:consume --env=prod
```

> We recommend using a supervisor to run the `oro:message-queue:consume` command continuously, as many background tasks rely on the message queue to operate. For more details on configuration, check the [Oro documentation][6] or visit the [Supervisord website][7].

> **Note**: `bin/console` is relative to the project root folder. Ensure you're using the full path in crontab configuration or when running the command from another location.

## Additional Installation Notes

Installed PHP Accelerators must be compatible with Symfony and Doctrine (support for DOCBLOCKs is required).  
Ensure that the WebSocket port is open in your firewall for incoming and outgoing connections.  
For additional performance optimization, consult the [Oro performance documentation][2].

## Web Server Configuration

Since Proton ERP is based on the Symfony standard, you can follow the same [web server configuration recommendations][5].

## More Detailed Installation Guide

For a more comprehensive guide on installing Proton ERP, including advanced configurations and troubleshooting tips, refer to the official [Oro Inc. installation documentation for version 5.1][8].

## Package Manager Configuration

Ensure that a GitHub OAuth token is configured in the package manager settings.

[1]: https://getcomposer.org/  
[2]: https://doc.oroinc.com/backend/setup/system-requirements/performance-optimization/  
[4]: https://nodejs.org/en/download/package-manager  
[5]: https://symfony.com/doc/5.4/setup/web_server_configuration.html  
[6]: https://doc.oroinc.com/backend/setup/dev-environment/community-edition/  
[7]: https://supervisord.org/  
[8]: https://doc.oroinc.com/5.1/backend/setup/installation/

---

This version includes the link to the Oro installation guide for version 5.1, providing a more complete reference for anyone setting up Proton ERP. Let me know if you need further adjustments!
