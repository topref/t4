<?php

namespace T4\Console;


use T4\Core\Exception;
use T4\Core\TSingleton;

class Application
{

    use TSingleton;

    const CMD_PATTERN = '~^(\/?)([^\/]*?)(\/([^\/]*?))?$~';
    const OPTION_PATTERN = '~^--(.+)=(.*)$~';
    const DEFAULT_ACTION = 'default';

    public function run()
    {

        try {

            $route = $this->parseCmd($_SERVER['argv']);
            $commandClassName = $route['namespace'] . '\\Commands\\' . ucfirst($route['command']);
            $command = new $commandClassName;
            $command->action($route['action']);

        } catch (Exception $e) {
            die($e->getMessage());
        }

    }

    protected function parseCmd($argv)
    {

        $argv = array_slice($argv, 1);
        $cmd = array_shift($argv);
        preg_match(self::CMD_PATTERN, $cmd, $m);
        $commandName = $m[2];
        $actionName = isset($m[4]) ? $m[4] : self::DEFAULT_ACTION;
        $rootCommand = !empty($m[1]);

        $options = [];
        foreach ($argv as $arg) {
            if (preg_match(self::OPTION_PATTERN, $arg, $m)) {
                $options[$m[1]] = $m[2];
            }
        }

        return [
            'namespace' => $rootCommand ? 'T4' : 'App',
            'command' => $commandName,
            'action' => $actionName,
            'params' => $options,
        ];

    }

}