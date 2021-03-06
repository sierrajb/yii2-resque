<?php namespace resque;

use resque\lib\ResqueAutoloader;
use resque\lib\Resque;
use resque\lib\ResqueScheduler;
use \yii\BaseYii;

class RResque extends \yii\base\Component
{

    /**
     * @var string Redis server address
     */
    public $server = 'localhost';

    /**
     * @var string Redis port number
     */
    public $port = '6379';

    /**
     * @var int Redis database index
     */
    public $database = 0;

    /**
     * @var string Redis password auth
     */
    public $password = '';

    /**
     * @var int Redis database connection timeout
     */
    public $timeout = 5;

    public $prefix = '';

    /**
     * @var mixed include file in daemon (userul for defining YII_DEBUG, etc), may be string or array
     */
    public $includeFiles = '';

    /**
     * Initializes the connection.
     */
    public function init()
    {
        parent::init();

        if (!class_exists('ResqueAutoloader', false)) {

            # Turn off our amazing library autoload
            spl_autoload_unregister(['Yii', 'autoload']);
            # Include Autoloader library
            include_once(dirname(__FILE__) . '/ResqueAutoloader.php');

            # Run request autoloader
            ResqueAutoloader::register();

            # Give back the power to Yii
            spl_autoload_register(array('Yii', 'autoload'));
        }
        Resque::setBackend($this->server . ':' . $this->port, $this->database, $this->password, $this->timeout);
        if ($this->prefix) {
            Resque::redis()->prefix($this->prefix);
        }
    }

    /**
     * Create a new job and save it to the specified queue.
     *
     * @param string $queue The name of the queue to place the job in.
     * @param string $class The name of the class that contains the code to execute the job.
     * @param array $args Any optional arguments that should be passed when the job is executed.
     *
     * @return string
     */
    public function createJob($queue, $class, $args = array(), $track_status = false)
    {

        return Resque::enqueue($queue, $class, $args, $track_status);
    }

    /**
     * Delete a job based on job id or key, if worker_class is empty then it'll remove
     * all jobs within the queue, if job_key is empty then it'll remove all jobs within
     * provided queue and worker_class
     *
     * @param string $queue The name of the queue to place the job in.
     * @param string $worker_class The name of the class that contains the code to execute the job.
     * @param string $job_key Job key
     *
     * @return bool
     */
    public function deleteJob($queue, $worker_class = null, $job_key = null)
    {
        if (!empty($job_key) && !empty($worker_class)) {
            // Remove job with specific job key
            return Resque::dequeue($queue, array($worker_class => $job_key));
        } else if (!empty($worker_class) && empty($job_key)) {
            // Remove all jobs inside specified worker and queue
            return Resque::dequeue($queue, array($worker_class));
        } else {
            // Remove all jobs inside queue
            return Resque::dequeue($queue);
        }
    }

    /**
     * Create a new scheduled job and save it to the specified queue.
     *
     * @param int $delay Second count down to job.
     * @param string $queue The name of the queue to place the job in.
     * @param string $class The name of the class that contains the code to execute the job.
     * @param array $args Any optional arguments that should be passed when the job is executed.
     *
     * @return string
     */
    public function enqueueJobIn($delay, $queue, $class, $args = array())
    {
        return ResqueScheduler::enqueueIn($delay, $queue, $class, $args);
    }

    /**
     * Create a new scheduled job and save it to the specified queue.
     *
     * @param timestamp $scheduleTimestamp UNIX timestamp when job should be executed.
     * @param string $queue The name of the queue to place the job in.
     * @param string $class The name of the class that contains the code to execute the job.
     * @param array $args Any optional arguments that should be passed when the job is executed.
     *
     * @return string
     */
    public function enqueueJobAt($scheduleTimestamp, $queue, $class, $args = array())
    {

        return ResqueScheduler::enqueueAt($scheduleTimestamp, $queue, $class, $args);
    }

    /**
     * Get delayed jobs count
     *
     * @return int
     */
    public function getDelayedJobsCount()
    {
        return (int) Resque::redis()->zcard('delayed_queue_schedule');
    }

    /**
     * Check job status
     *
     * @param string $token Job token ID
     *
     * @return string Job Status
     */
    public function status($token)
    {
        $status = new Resque_Job_Status($token);
        return $status->get();
    }

    /**
     * Return Redis
     *
     * @return object Redis instance
     */
    public function redis()
    {
        return Resque::redis();
    }

    /**
     * Get queues
     *
     * @return object Redis instance
     */
    public function getQueues()
    {
        return $this->redis()->zRange('delayed_queue_schedule', 0, -1);
    }
//    public function getValueByKey($key){
//        return $this->redis()->get($key);
//    }
}
