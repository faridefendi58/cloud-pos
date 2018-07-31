<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class SupplierController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/list', [$this, 'get_list']);
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['POST'], '/update', [$this, 'update']);
        $app->map(['POST'], '/delete', [$this, 'delete']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['list'],
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
        $spmodel = new \Model\SuppliersModel();
        $items = $spmodel->getData(['status' => \Model\SuppliersModel::STATUS_ENABLED]);
        if (is_array($items)){
            $result['success'] = 1;
            if (is_array($params) && isset($params['simply']) && $params['simply'] == 1) {
                $result['data'] = [];
                foreach ($items as $i => $item) {
                    array_push($result['data'], ['id' => $item['id'], 'title' => $item['name']]);
                }
            } else {
                $result['data'] = $items;
            }
        } else {
            $result = [
                'success' => 0,
                'message' => "Data supplier tidak ditemukan.",
            ];
        }

        return $response->withJson($result, 201);
    }

    public function create($request, $response, $args)
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
        if (isset($params['admin_id'])) {
            $model = new \Model\SuppliersModel();
            if (!empty($params['name'])) {
                $model->name = $params['name'];
            }

            if (!empty($params['address'])) {
                $model->address = $params['address'];
            }

            if (!empty($params['phone'])) {
                $model->phone = $params['phone'];
            }

            if (!empty($params['notes'])) {
                $model->notes = $params['notes'];
            }

            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $params['admin_id'];
            $save = \Model\SuppliersModel::model()->save(@$model);
            if ($save) {
                $result = [
                    "success" => 1,
                    "id" => $model->id,
                    'message' => 'Data berhasil disimpan.'
                ];
            } else {
                $result['message'] = 'Data gagal disimpan';
            }
        }

        return $response->withJson($result, 201);
    }

    public function update($request, $response, $args)
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
            $model = \Model\SuppliersModel::model()->findByPk($params['id']);
            if (!empty($params['name'])) {
                $model->name = $params['name'];
            }

            if (!empty($params['address'])) {
                $model->address = $params['address'];
            }

            if (!empty($params['phone'])) {
                $model->phone = $params['phone'];
            }

            if (!empty($params['notes'])) {
                $model->notes = $params['notes'];
            }

            $model->updated_at = date("Y-m-d H:i:s");
            $save = \Model\SuppliersModel::model()->update(@$model);
            if ($save) {
                $result = [
                    "success" => 1,
                    "id" => $model->id,
                    'message' => 'Data berhasil disimpan.'
                ];
            } else {
                $result['message'] = 'Data gagal disimpan';
            }
        }

        return $response->withJson($result, 201);
    }

    public function delete($request, $response, $args)
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
            $model = \Model\SuppliersModel::model()->findByPk($params['id']);
            $po_model = \Model\PurchaseOrdersModel::model()->findByAttributes(['supplier_id' => $model->id]);
            if ($po_model instanceof \RedBeanPHP\OODBBean) {

                $model->active = \Model\SuppliersModel::STATUS_DISABLED;
                $model->updated_at = date("Y-m-d H:i:s");
                $save = \Model\SuppliersModel::model()->update(@$model);
                if ($save) {
                    $result = [
                        "success" => 1,
                        "id" => $model->id,
                        'message' => 'Data berhasil dihapus.'
                    ];
                } else {
                    $result['message'] = 'Data gagal dihapus';
                }

            } else {
                $delete = \Model\SuppliersModel::model()->delete($model);
                if ($delete) {
                    $result = [
                        "success" => 1,
                        'message' => 'Data berhasil dihapus.'
                    ];
                } else {
                    $result['message'] = 'Data gagal dihapus';
                }
            }
        }

        return $response->withJson($result, 201);
    }
}