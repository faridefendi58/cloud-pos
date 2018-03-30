<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class ProductController extends BaseController
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
        $pmodel = new \Model\ProductsModel();
        $items = $pmodel->getData();
        if (is_array($items)){
            $result['success'] = 1;
            if (is_array($params) && isset($params['simply']) && $params['simply'] == 1) {
                $result['data'] = [];
                foreach ($items as $i => $item) {
                    array_push($result['data'], ['id' => $item['id'], 'title' => $item['title'], 'unit' => $item['unit']]);
                }
            } else {
                $result['data'] = $items;
            }
        } else {
            $result = [
                'success' => 0,
                'message' => "Data product tidak ditemukan.",
            ];
        }

        return $response->withJson($result, 201);
    }
}