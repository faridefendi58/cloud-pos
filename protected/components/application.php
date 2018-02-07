<?php

namespace Components;

class Application
{
    public function getThemeConfig()
    {
        $hash = md5(__CLASS__.'/themes/');
        $config = substr($hash, 0, 10);

        return $config;
    }

    public function getTheme()
    {
        if (!file_exists(realpath(dirname(__DIR__)).'/data/configs.json'))
            return 'default';

        $content = file_get_contents(realpath(dirname(__DIR__)).'/data/configs.json');
        $configs = json_decode($content, true);
        if (is_array($configs)){
            return $configs['theme'];
        }

        return 'default';
    }

    public function getParams()
    {
        if (!file_exists(realpath(dirname(__DIR__)).'/data/configs.json'))
            return array();

        $content = file_get_contents(realpath(dirname(__DIR__)).'/data/configs.json');
        $configs = json_decode($content, true);

        if (is_array($configs)){
            return $configs;
        }

        return array();
    }
}