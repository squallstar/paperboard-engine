# Cronycle HHVM APIs



## Workers

To install the scripts, copy the content of the **application/workers** folder into **/etc/init** directory of your Unix machine.

To start the workers:

    sudo service cronycle start
    
To kill them:

    sudo initctl emit cronycle-stop
    
    
View the running processes

    ps -aux | grep php
    

    root      3118  0.2  0.3 271288 14092 ?        Ss   11:03   0:00 /usr/bin/php /home/httpd/squallstar/hhvm/index.php service start_tweets_downloader
    
    root      3120  0.2  0.3 271288 14028 ?        Ss   11:03   0:00 /usr/bin/php /home/httpd/squallstar/hhvm/index.php service start_followers_updater
    
    root      3122  0.1  0.3 271288 14032 ?        Ss   11:03   0:00 /usr/bin/php /home/httpd/squallstar/hhvm/index.php service start_downloader


To kill a single process (downloader, tweets, followers)

    sudo stop service.cronycle.com service="downloader"