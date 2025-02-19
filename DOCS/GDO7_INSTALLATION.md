# GDOv7 Installation

You can either install GDOv7 via the Web Install Wizard or the gdo_adm.php CLI utility.
Note that a webserver is not required to operate GDOv7.
All methods can be operated via CLI or chat systems.


## GDOv7 Installation: Requirements

Clone the phpgdo core with

    git clone --recursive https://github.com/gizmore/phpgdo
    

GDOv7 core requires:

 - PHP8 (in your PATH)
 - php-bcmath
 - php-fileinfo
 - php-mbstring
 
Optionally:
 
 - nodejs and yarn (very recommended)
 - php-curl
 - php-memcached
 - php-openssl
 
 
An up-to-date requirements check should be in the installers.


## GDOv7 Installation: Web

Make your webserver point to the phpgdo root folder.
Open `yourhost/install/wizard.php` in your web browser.

Please note that you manually have to edit the protected/config.php file in any case.


## GDOv7 Installation: CLI

    git clone --recursive https://github.com/gizmore/phpgdo
    cd phpgdo
    ./gdo_adm.sh systemtest
    ./gdo_adm.sh configure
    # NOW edit protected/config.php manually (@TODO: write a repl configurator)
    ./gdo_adm.sh install_all
    # MAKE your PATH point to phpgdo/bin (OPTIONAL)
    gdo core.version # test version method


## GDOv7 Installation: Modules

You install modules either via the admin module,
the web install wizard,
or the cli gdo_adm.sh utility.

To install any module, you have to clone it under the correct folder name.

    cd GDO/
    git clone --recursive https://github.com/gizmore/phpgdo-font-awesome FontAwesome
    
Then you can install the module via web or cli.

    ./gdo_adm.sh install fontawesome
    
But... the ./gdo_adm.sh utility is quite convinient.

For example the following command clones and installs a whole website project with around 50 module dependencies.

    ./gdo_adm.sh provide KassiererCard
    
    
An overview of official modules is given by.

    ./gdo_adm.sh modules
    
    
    
   