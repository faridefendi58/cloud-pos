<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class StockController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/list', [$this, 'get_list']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['get-list'],
                'users'=> ['@'],
            ]
        ];
    }

    public function get_list($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $result = [];
        $params = $request->getParams();
        if (is_array($params) && in_array('warehouse_name', array_keys($params))) {
            $model = \Model\WarehousesModel::model()->findByAttributes(['title' => $params['warehouse_name']]);
        }

        if (is_array($params) && in_array('warehouse_id', array_keys($params))) {
            $model = \Model\WarehousesModel::model()->findByPk($params['warehouse_id']);
        }

        if ($model instanceof \RedBeanPHP\OODBBean) {
            $ps_model = new \Model\ProductStocksModel();
            $stocks = $ps_model->getQuery(['warehouse_id' => $model->id]);
            $result['status'] = 1;
            $result['data'] = $stocks;
        } else {
            $result['status'] = 0;
            $result['message'] = 'Warehouse tidak ditemukan.';
        }
        
        return $response->withJson($result, 201);
    }
}