=== Async Background Worker ===
Contributors: Todi.Adiatmo, gamaup
Description: Async Background Worker for WordPress. 
Tags: background worker, async background worker
Requires at least: 4.4
Tested up to: 4.9
Version: 1.0
License: LGPL
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Async Background Worker, more information please visit this page [https://tonjoo.github.io/async-background-worker/](https://tonjoo.github.io/async-background-worker/ "Documentation")

== Sample Usage ==

= What is it ? =
WordPress background worker plugin that enable WordPress to interact with beanstalkd work queue. 

= Why we need a worker ? =
We can run a very long task in the background, for example we need to import 100.000 row into WordPress databases. Instead of doing the 100.000 import in one job, we can separate the job into many smaller job which is safer.

= WP-CLI =
Make sure you have WP CLI installed on your system

= Support Forum =
[https://forum.tonjoostudio.com/thread-category/async-background-worker/](https://forum.tonjoostudio.com/thread-category/async-background-worker/ "Support")

== Add job to queue ==

1. Add new job to new worker queue using `add_async_job` command 
    `
$job = new stdClass();  
// the function to run  
$job->function = 'function_to_execute_on_background';  
// our user entered data  
$job->user_data = array('data'=>'some_data');
add_async_job($job);
    `
2. Implement function 
    `
function function_to_execute_on_background($data) {
    //do something usefull
    echo "Background job executed successfully\n";
}
    `
3. Run `wp background-worker listen`

== Command ==

* `wp background-worker`
Run Async Background Worker once. 

* `wp background-worker listen`
Run Async Background Worker in loop (contiously), this is what you want for background worker. WordPress framework is restart in each loop.

* `wp background-worker listen-daemon`
Run Async Background Worker in loop (contiously) without restart the WordPress framework. **NOTE** if you use this mode, any code change will not be reflected. You must restart the Async Background Worker each time you change code. This save memory and speed up thing. 

== Changelog ==
= 1.0.0 =
* Initial Release