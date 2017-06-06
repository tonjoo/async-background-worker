# What is it ?
WordPress background worker plugin that enable WordPress to interact with beanstalkd work queue. 

# Why we need a worker ?
We can run a very long task in the background, for example we need to import 100.000 row into WordPress databases. Instead of doing the 100.000 import in one job, we can separate the job into many smaller job which is safer.

## Add job to queue

1. Add new job to new worker queue using `wp_background_job` command 
    ```
    $job = new stdClass();  
    // the function to run  
    $job->function = 'find_matches';  
    // our user entered data  
    $job->user_data = $_POST;
    
    wp_background_add_job($job);
    ```
2. Run `wp background-worker listen`

## Production Mode

1. Install beanstalkd on your server
2. Install supervisord on your server
3. Put this config on the supervisord `/etc/supervisor/conf.d/wp_worker.conf` :
    ```
    [program:wp_worker]
    command=wp background-worker
    directory=/path/to/wordpress
    stdout_logfile=/path/to/wordpress/logs/supervisord.log
    redirect_stderr=true
    autostart=true
    autorestart=true
    ```
4. Run `supervisorctl reread` and `supervisorctl update`
5. Make sure your worker running by run `supervisorctl`

## WP Config Variable

```
define( 'WP_BACKGROUND_WORKER_QUEUE_NAME', 'WP_QUEUE' );
define( 'WP_BACKGROUND_WORKER_HOST', '127.0.0.1' );
```