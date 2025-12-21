<?php

defined('_JEXEC') or die;

use Vigilant\JoomlaHealthchecks\Plugin\System\VigilantHealthchecksPlugin as BasePlugin;

$autoloaders = [
    __DIR__.'/vendor/autoload.php',
    JPATH_ROOT.'/vendor/autoload.php',
];

foreach ($autoloaders as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

class PlgSystemVigilanthealthchecks extends BasePlugin
{
}
