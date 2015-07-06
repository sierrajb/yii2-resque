<?php namespace resque;

use Yii;
use yii\console\Controller;

class ResqueController extends Controller
{

    public $defaultAction = 'index';

    public function actionIndex()
    {
        echo <<<EOD
Available commands are:
    start --queue=[queue_name | *] --interval=[int] --verbose=[0|1] --count=[int]
    startrecurring --queue=[queue_name | *] --interval=[int] --verbose=[0|1]
    stop --quit=[0|1]
EOD;
        echo PHP_EOL;
    }

    protected function runCommand($queue, $interval, $verbose, $count, $script)
    {
        // $return = null;
        $yiiPath = Yii::$app->basePath;
        $appPath = Yii::getAlias('@console');
        $resquePath = __DIR__;
        $resque = Yii::$app->resque;

        if (!$resque) {
            echo 'resque component cannot be found on your console.php configuration';
            die();
        }
        $server = Yii::$app->resque->server ? : 'localhost';
        $port = Yii::$app->resque->port ? : 6379;
        $host = $server . ':' . $port;
        $db = Yii::$app->resque->database ? : 0;
        $auth = Yii::$app->resque->password ? : '';
        $prefix = Yii::$app->resque->prefix;
        $loghandler = isset(Yii::$app->resque->loghandler) ? Yii::$app->resque->loghandler : null;
        $logtarget = isset(Yii::$app->resque->logtarget) ? Yii::$app->resque->logtarget : null;
        $options = '';

        $command = 'nohup sh -c "LOGHANDLER=' . $loghandler . ' LOGHANDLERTARGET=' . $logtarget . ' PREFIX=' . $prefix . ' QUEUE=' . $queue . ' COUNT=' . $count . ' REDIS_BACKEND=' . $host . ' REDIS_BACKEND_DB=' . $db . ' REDIS_AUTH=' . $auth . ' INTERVAL=' . $interval . ' VERBOSE=' . $verbose . ' YII_PATH=' . $yiiPath . ' APP_PATH=' . $appPath . ' ' . $options . ' php ' . $resquePath . '/bin/' . $script . '" >> ' . $appPath . '/runtime/yii_resque_log.log 2>&1 &';
        exec($command, $return);
        return $return;
    }

    public function actionStart($queue = '*', $interval = 5, $verbose = 1, $count = 1)
    {
        $this->runCommand($queue, $interval, $verbose, $count, 'resque');
    }

    public function actionStartrecurring($queue = '*', $interval = 5, $verbose = 1, $count = 1)
    {
        $this->runCommand($queue, $interval, $verbose, $count, 'resque-scheduler');
    }

    public function actionStop($quit = null)
    {
        $quit_string = $quit ? '-s QUIT' : '-9';
        exec("ps uxe | grep '" . escapeshellarg(Yii::$app->basePath) . "' | grep 'resque' | grep -v grep | awk {'print $2'} | xargs kill $quit_string");
    }
}
