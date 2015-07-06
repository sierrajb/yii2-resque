<?php namespace resque\lib;

use \yii\BaseYii;

/**
 * This file part of RResque
 *
 * Autoloader for Resque library
 *
 * For license and full copyright information please see main package file
 * @package       yii2-resque
 */
class ResqueAutoloader
{

    /**
     * Registers Raven_Autoloader as an SPL autoloader.
     */
    static public function register()
    {
        spl_autoload_unregister(['Yii', 'autoload']);
        spl_autoload_register([new self, 'autoload']);
        spl_autoload_register(['\yii\BaseYii', 'autoload'], true, true);
    }

    /**
     * Handles autoloading of classes.
     *
     * @param  string  $class  A class name.
     *
     * @return boolean Returns true if the class has been loaded
     */
    static public function autoload($class)
    {
        $workerPath = \yii\BaseYii::$app->basePath . '/../console/resque/';
        $class = basename(str_replace('\\', '/', $class));
        if (is_file($file = dirname(__FILE__) . '/lib/' . str_replace(array('_', "\0"), array('/', ''), $class) . '.php')) {
            require $file;
        } else if (is_file($file = dirname(__FILE__) . '/lib/ResqueScheduler/' . str_replace(array('_', "\0"), array('/', ''), $class) . '.php')) {
            require $file;
        } else if (is_file($file = dirname(__FILE__) . '/' . str_replace(array('_', "\0"), array('/', ''), $class) . '.php')) {
            require $file;
        } else if (is_file($file = dirname(__FILE__) . '/lib/' . str_replace(array('\\', "\0"), array('/', ''), $class) . '.php')) {
            require $file;
        } else if (is_file($file = ($workerPath . str_replace(array('_', "\0"), array('/', ''), $class)) . '.php')) {
            require $file;
        }
    }
}
