<?php

namespace Cashier\Controllers;

use Components\BaseController as BaseController;

class SaleController extends BaseController
{
    protected $_login_url = '/cashier/default/login';

    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
        $app->map(['GET'], '/cart', [$this, 'cart']);
        $app->map(['GET'], '/customer', [$this, 'customer']);
        $app->map(['GET'], '/get-customer', [$this, 'get_customer']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'create', 'cart', 'get-customer'
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
            'items_belanja' => $items_belanja,
            'sub_total' => $this->getSubTotal(),
            'tot_qty' => $this->getTotalQty()
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

    public function customer($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        /*$model = new \Model\CustomersModel();
        $params = [
            'limit' => 100,
            'status' => \Model\CustomersModel::STATUS_ACTIVE
        ];

        $customers = $model->getData($params);*/

        return $this->_container->module->render(
            $response,
            'sale/_customer.html',
            [
                //'customers' => $customers
            ]);
    }

    public function get_customer($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\CustomersModel();
        $params = [
            'name' => $request->getParams()['name'],
            'status' => \Model\CustomersModel::STATUS_ACTIVE,
            'field' => 't.name, t.telephone, t.address'
        ];

        $items = [];
        //if (!empty($params['name'])) {
            $customers = $model->getData($params);

        foreach ($customers as $i => $customer) {
            $items['data'][] = [
                $customer['name'],
                $customer['phone'],
                $customer['address'],
            ];
        }

        return $response->withJson($items, 201);
    }

    private function getSubTotal($items_belanja = null)
    {
        if (empty($items_belanja))
            $items_belanja = $_SESSION['items_belanja'];
        $tot_price = 0;
        foreach ($items_belanja as $i => $item) {
            $tot_price = $tot_price + ($item['qty']*$item['unit_price']);
        }

        return $tot_price;
    }

    private function getTotalQty($items_belanja = null)
    {
        if (empty($items_belanja))
            $items_belanja = $_SESSION['items_belanja'];
        $tot_qty = 0;
        foreach ($items_belanja as $i => $item) {
            $tot_qty = $tot_qty + $item['qty'];
        }

        return $tot_qty;
    }
}