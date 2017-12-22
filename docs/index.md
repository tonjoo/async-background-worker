# What is it ?
WordPress background worker plugin that enable WordPress to interact with beanstalkd work queue. 

# Why we need a worker ?
We can run a very long task in the background, for example we need to import 100.000 row into WordPress databases. Instead of doing the 100.000 import in one job, we can separate the job into many smaller job which is safer.

# WP-CLI
Make sure you have WP CLI installed on your system

## Add job to queue

1. Add new job to new worker queue using `add_async_job` command 
    ```
    $job = new stdClass();  
    // the function to run  
    $job->function = 'function_to_execute_on_background';  
    // our user entered data  
    $job->user_data = array('data'=>'some_data');
    
    add_async_job($job);
    ```
2. Implement function 
    ```
    function function_to_execute_on_background($data) {
        //do something usefull
        echo "Background job executed successfully\n";
    }
    ```
3. Run `wp background-worker listen`

## Command

###  `wp background-worker`

Run WordPress Background Worker once. 

###  `wp background-worker listen`

Run WordPress Background Worker in loop (contiously), this is what you want for background worker. WordPress framework is restart in each loop.


###  `wp background-worker listen-loop`

Run WordPress Background Worker in loop (contiously) without restart the WordPress framework. **NOTE** if you use this mode, any code change will not be reflected. You must restart the Wordpress Background Worker each time you change code. This save memory and speed up thing. 

## Production Mode

1. Install supervisord on your server
2. Put this config on the supervisord `/etc/supervisor/conf.d/wp_worker.conf` :
    ```
    [program:wp_worker]
    # Add --allow-root if run as root (not recomended)
    # if you have problem with `listen` you can use `listen-loop` instead 
    command=wp background-worker listen 
    directory=/path/to/wordpress
    stdout_logfile=/path/to/wordpress/logs/supervisord.log
    redirect_stderr=true
    # If you have non root user which share www-data group you can disable --allow-root and put the user here 
    user=[your-user-name]
    autostart=true
    autorestart=true
    ```
3. Run `supervisorctl reread` and `supervisorctl update`
4. Make sure your worker running by run `supervisorctl`

## WP Config Variable


### Queue and log setting
```
define( 'ABW_DEBUG' , false );
define( 'ABW_QUEUE_NAME', 'WP_QUEUE' );
// Sleep between each background worker call, default value is 0.75 second
define( 'ABW_SLEEP', 750000 );
define( 'BACKGROUND_WORKER_LOG', '/path/to/supervisord/worker.log' );
// Timeout for worker execution, best to not set time to unlimited
define( 'ABW_TIMELIMIT', 60 );
```

### Memory Limit

You might want to increase memory limit, only on CLI

```
if( php_sapi_name() === 'cli' )
    define('WP_MAX_MEMORY_LIMIT', '1024M');
else
    define('WP_MAX_MEMORY_LIMIT', '512M');

```

## Changelog
> 0.3
- Updated table name
- Force output buffer 
- Add 'listen-loop' mode

> 0.2
- Updated with database backed queue

> 0.1
- Initial Releas
- Based on beanstalkd

## Todo
1. Create dashboard to show job progress / result
