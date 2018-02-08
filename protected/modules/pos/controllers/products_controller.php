<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class ProductsController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET', 'POST'], '/view', [$this, 'view']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'create', 'update', 'delete'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('pos/products/read'),
            ],
            ['allow',
                'actions' => ['create'],
                'expression' => $this->hasAccess('pos/products/create'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('pos/products/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('pos/products/delete'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }
        
        $model = new \Model\ProductsModel();
        $products = $model->getData();

        return $this->_container->module->render($response, 'products/view.html', ['products' => $products]);
    }
}