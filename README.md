Yii2 Resque
===========
Resque is a Redis-backed library for creating background jobs, placing those jobs on one or more queues, and processing them later.

Requirement
------------
    - php pcntl extension.
    - Redis.io
    - phpredis extension for better performance, otherwise it'll automatically use Credis as fallback.
    - Yii 2


Installation
------------

1.  The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

    Add

    ```
    "repositories":[
            {
                "type": "git",
                "url": "https://github.com/sierrajb/yii2-resque.git"
            }
        ],
    ```

    Either run

    ```
    php composer.phar require --prefer-dist resque/yii2-resque "*"
    ```

    or add

    ```
    "resque/yii2-resque": "dev-master"
    ```

    to the require section of your `composer.json` file.

2.  Create a file `ResqueController.php` in app/commands folder(in case of yii2-basic) or console/controllers(in case of yii2-
 advance) and write the following code in it.
    ```php
        namespace app\commands;
        use Yii;
        use yii\console\Controller;
        use resque\lib\ResqueAutoloader;
        use resque\lib\Resque\Resque_Worker;
        /**
         *
         * @author sierrajb
         * @since 2.0
         */
        class ResqueController extends Controller
        {
            /**
             * Time to kill a worker, in minutes.
             *
             * @const
             */
            const DELAY_AUTO_CLOSE_WORK = 5;

            /**
             * Initialize Resque workers.
             * @see http://php.net/manual/pt_BR/function.pcntl-fork.php
             *
             * @param int $logLevel
             * @param int $workerCount
             * @param string $queue
             * @param int $interval
             * @return void
             */
            public function actionStart(
                $logLevel = 1,
                $workerCount = 1,
                $queue = '*',
                $interval = 5
            ) {
                $resque = Yii::$app->resque;
                $this->setYiiConfiguration();

                for ($i = 0; $i < $workerCount; ++$i) {
                    // Forks this process as it's child.
                    // For the forked process 0 will be returned.
                    // For the parent process the child proccess id will be returned.
                    $pid = $this->createResqueFork();

                    if ($pid == -1) {
                        die('Could not create fork ' . $i . PHP_EOL);
                    }

                    // if this is a fork, so lets start to work!
                    if ($pid == 0) {
                        return $this->startWorker($queue, $logLevel, $interval);
                    }

                    // this is the parent, so we have a child pid
                    // we call nohup to create a non-blocking process to kill
                    // the child after a couple of minutes.
                    print('PID: ' . $pid . PHP_EOL);
                    $time = self::DELAY_AUTO_CLOSE_WORK * 60;
                    $this->executeCommand(implode([' ',
                        'nohup',
                        './yii queue/service/stop',
                        $pid,
                        $time
                    ]));
                }
            }

            /**
             * Stop a Worker or all workers.
             * If the pid is null, all workers will be killed.
             *
             * @param int|null $pid process id
             * @param int $delay
             * @return void
             */
            public function actionStop($pid = null, $delay = 0)
            {
                if ($delay) {
                    print('Waiting ' . $delay . ' seconds.');
                    sleep($delay);
                }

                Yii::info(Yii::t('resque', 'labels.stop_workers'), 'resque');

                // lists all proccesses.
                $processList = 'ps uxe';

                // filters those that belongs to this system path.
                $path = 'grep ' . escapeshellarg(Yii::$app->basePath);

                // filters the start proccess.
                $command = 'grep \'queue/service/start\'';

                // removes this search from the list.
                $removeGrep = 'grep -v grep';

                // get the pid.
                $pidFilter = 'awk {\'print $2\'}';

                if ($pid) {
                    // filters the choosen pid and kills it.
                    $kill = 'grep ' . $pid . ' | xargs kill -s QUIT';
                } else {
                    // kill them all.
                    $kill = 'xargs kill -9';
                }

                $this->executeCommand(implode(' | ', [
                    $processList,
                    $path,
                    $command,
                    $removeGrep,
                    $pidFilter,
                    $kill
                ]));
            }

            /**
             * Start Workers to proccess the queue.
             *
             * @param string $queue
             * @param int $logLevel
             * @param int $interval
             * @return void
             */
            protected function startWorker($queue, $logLevel, $interval)
            {
                $queues = explode(',', $queue);
                $worker = new Resque_Worker($queues);

                Yii::info(Yii::t('resque', 'labels.start_worker', [
                    'worker' => $worker
                ]), 'resque');

                $worker->logLevel = $logLevel;
                $worker->work($interval);
            }

            /**
             * Prepares the system to handle all requests.
             * Loads the the needed modules.
             *
             * @return void
             */
            protected function setYiiConfiguration()
            {
                $app = Yii::getAlias('@app');
                if (file_exists($app . '/config/console.php')) {
                    // Yii2-Basic
                    $config = require($app . '/config/console.php');
                } else {
                    // Yii2-Advance
                    $config = require($app . '/config/main.php');
                }
                $application = new \yii\console\Application($config);

                # Turn off our amazing library autoload
                spl_autoload_unregister(array('Yii','autoload'));

                $vendor = Yii::getAlias('@vendor');
                if (file_exists($vendor . '/resque/yii2-resque/ResqueAutoloader.php')) {
                    // Yii2-Basic
                    require_once($vendor . '/resque/yii2-resque/ResqueAutoloader.php');
                } else {
                    // Yii2-Advance
                    require_once($app . '/../vendor/resque/yii2-resque/ResqueAutoloader.php');
                }

                ResqueAutoloader::register();

                # Give back the power to Yii
                spl_autoload_register(array('Yii','autoload'));
            }

            /**
             * Forks this process and returns it's process id.
             * If this is the child, zero will be returned.
             * If this is creator process, the child process id will be returned.
             *
             * @return integer
             */
            protected function createResqueFork()
            {
                return Resque::fork();
            }

            /**
             * Executes the command.
             *
             * @param string $command
             * @return void
             */
            protected function executeCommand($command)
            {
                exec($command);
            }
        }

    ```

3.  Add these in your config/web.php and in your config/console.php(in case of yii2-basic) or console/config/main.php and common/config/main-local.php (in case of yii2- advance)

    ```php
    'components' => [
        ...
        'resque' => [
            'class' => '\resque\RResque',
            'server' => 'localhost',     // Redis server address
            'port' => '6379',            // Redis server port
            'database' => 0,             // Redis database number
            'password' => '',            // Redis password auth, set to '' or null when no auth needed
        ],
        ...
    ]
    ```
4.  Make sure you have already installed `Yii2-redis` Extension


Usage
-----

Once the extension is installed  :

1.  Create a folder `components` in your app(yii2-basic).in case of yii2-advance template create this folder inside app(like frontend)
    You can put all your class files into this `components` folder.Change namespace as per your application.

    Example -

    ```php
    namespace app\components;
    class ClassWorker
    {
        public function setUp()
        {
            # Set up environment for this job
        }

        public function perform()
        {

            echo "Hello World";
            # Run task
        }

        public function tearDown()
        {
            # Remove environment for this job
        }
    }
    ```


   Create job and Workers
    ----------------------

    You can put this line where ever you want to add jobs to queue
    ```php
        Yii::$app->resque->createJob('queue_name', 'ClassWorker', $args = []);
    ```
    Put your workers inside `components` folder, e.g you want to create worker with name SendEmail then you can create file inside `components` folder and name it SendEmail.php, class inside this file must be SendEmail

   Create Delayed Job
    ------------------

    You can run job at specific time
    ```php
        $time = 1332067214;
        Yii::$app->resque->enqueueJobAt($time, 'queue_name', 'ClassWorker', $args = []);
    ```
    or run job after n second
    ```php
        $in = 3600;
        $args = ['id' => $user->id];
        Yii::$app->resque->enqueueIn($in, 'email', 'ClassWorker', $args);
    ```
    Get Current Queues
    ------------------

    This will return all job in queue (EXCLUDE all active job)
    ```php
    Yii::$app->resque->getQueues();
    ```
    Start and Stop workers
    ----------------------

    Run this command from your console/terminal :

    Start queue

    `QUEUE=* php yii resque start`

    Stop queue

    `QUEUE=* php yii resque stop`


