<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class CustomerController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/list', [$this, 'get_list']);
        $app->map(['GET'], '/detail', [$this, 'get_detail']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['list', 'detail'],
                'users'=> ['@'],
            ]
        ];
    }

	/**
	* ex : http://ucokdurian.local:46/api/customer/list?api-key=ac43724f16e9241d990427ab7c8f4228&limit=3&name=john&telephone=081
	*/
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
        $model = new \Model\CustomersModel();

		$q_params = [];
		if (isset($params['name'])) {
			$q_params['name'] = $params['name'];
		}

		if (isset($params['email'])) {
			$q_params['email'] = $params['email'];
		}

		if (isset($params['telephone'])) {
			$q_params['telephone'] = $params['telephone'];
		}

		if (isset($params['status'])) {
			$q_params['status'] = $params['status'];
		}

		if (isset($params['group_id'])) {
			$q_params['group_id'] = $params['group_id'];
		}

		if (isset($params['order_by'])) {
			$q_params['order_by'] = $params['order_by'];
		}

		if (isset($params['limit'])) {
			$q_params['limit'] = $params['limit'];
		}
        $items = $model->getData($q_params);
        if (is_array($items)){
            $result['success'] = 1;
            $result['data'] = [];
			foreach ($items as $i => $item) {
				array_push($result['data'], $item);
			}
        } else {
            $result = [
                'success' => 0,
                'message' => "Data customer tidak ditemukan.",
            ];
        }

        return $response->withJson($result, 201);
    }

	public function get_detail($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

		$result = ['success' => 0];
        $params = $request->getParams();
        if (isset($params['admin_id']) && isset($params['id'])) {
            $model = new \Model\CustomersModel();
			$detail = $model->getDetail($params['id']);
            if (!empty($detail)) {
                $result = [
                    "success" => 1,
                    "data" => $detail,
                ];
            } else {
                $result['message'] = 'Data tidak ditemukan';
            }
        }

		return $response->withJson($result, 201);
	}
}
