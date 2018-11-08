<?php

namespace Mobile\Controllers;

use Components\BaseController as BaseController;

class SaleController extends BaseController
{
    protected $_login_url = '/mobile/default/login';

    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
    }

    public function create($request, $response, $args)
    {
        if ($this->_user->isGuest()) {
            return $response->withRedirect($this->_login_url);
        }

        return $this->_container->module->render($response, 'sale/create.html', [

        ]);
    }
}