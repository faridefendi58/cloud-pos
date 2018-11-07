<?php

namespace Components;

class CTranslate
{
    protected $_container;
    protected $_user;

    public function __construct($container, $user)
    {
        $this->_container = $container;
        $this->_user = $user;
    }

    public function get($mod, $string)
    {
        $settings = $this->_container->get('settings');
        $lang = $settings['params']['language'];
        if (!$this->_user->isGuest()) {
            $lang = $this->_user->language;
        }

        $dir = __DIR__ . '/../messages/' . $lang;

        if (is_dir($dir) && file_exists($dir. '/' . $mod . '.php')) {
            $arrs = require $dir. '/' . $mod . '.php';

            if (is_array($arrs) && array_key_exists($string, $arrs)) {
                return $arrs[$string];
            }
        }

        return $string;
    }
}