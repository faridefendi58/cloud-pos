<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class WarehouseController extends BaseController
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
        $whmodel = new \Model\WarehousesModel();
        $items = $whmodel->getData();
        if (is_array($items)){
            if (is_array($params) && isset($params['simply']) && $params['simply'] == 1) {
                foreach ($items as $i => $item) {
                    $result[$item['id']] = $item['title'];
                }
            } else {
                $result = $items;
            }
        }

        return $response->withJson($result, 201);
    }
}