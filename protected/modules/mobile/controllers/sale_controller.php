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
        $app->map(['GET'], '/cart', [$this, 'cart']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'create', 'cart'
                ],
                'users'=> ['@'],
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function create($request, $response, $args)
    {
        if ($this->_user->isGuest()) {
            return $response->withRedirect($this->_login_url);
        }

        $items_belanja = $_SESSION['items_belanja'];

        return $this->_container->module->render($response, 'sale/create.html', [
            'items_belanja' => $items_belanja
        ]);
    }

    public function cart($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $items_belanja = $_SESSION['items_belanja'];

        return $this->_container->module->render(
            $response,
            'sale/_items.html',
            [
                'items_belanja' => $items_belanja
            ]);
    }
}