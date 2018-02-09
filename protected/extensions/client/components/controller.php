<?php

namespace Extensions\Components;

class ClientBaseController
{
    protected $_container;
    protected $_settings;
    protected $_user;
    protected $_login_url = '/client/login';

    public function __construct($app, $client)
    {
        $container = $app->getContainer();
        $this->_container = $container;
        $this->_settings = $container->get('settings');
        $this->_user = $client;

        $this->register($app);
    }
}