<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class ShipmentController extends BaseController
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
        $shmodel = new \Model\ShipmentsModel();
        $items = $shmodel->getData();
        if (is_array($items)){
            $result['success'] = 1;
            if (is_array($params) && isset($params['simply']) && $params['simply'] == 1) {
                $result['data'] = [];
                foreach ($items as $i => $item) {
                    array_push($result['data'], ['id' => $item['id'], 'title' => $item['title']]);
                }
            } else {
                $result['data'] = $items;
            }
        } else {
            $result = [
                'success' => 0,
                'message' => "Data cara pengiriman tidak ditemukan.",
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
            $model = new \Model\ShipmentsModel();
            if (!empty($params['title'])) {
                $model->title = $params['title'];
            }

            if (!empty($params['description'])) {
                $model->description = $params['description'];
            }

            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $params['admin_id'];
            $save = \Model\ShipmentsModel::model()->save(@$model);
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
            $model = \Model\ShipmentsModel::model()->findByPk($params['id']);
            if (!empty($params['title'])) {
                $model->title = $params['title'];
            }

            if (!empty($params['description'])) {
                $model->description = $params['description'];
            }

            $model->updated_at = date("Y-m-d H:i:s");
            $save = \Model\ShipmentsModel::model()->update(@$model);
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
            $model = \Model\ShipmentsModel::model()->findByPk($params['id']);
            $po_model = \Model\PurchaseOrdersModel::model()->findByAttributes(['shipment_id' => $model->id]);
            if ($po_model instanceof \RedBeanPHP\OODBBean) {

                $model->active = \Model\ShipmentsModel::STATUS_DISABLED;
                $model->updated_at = date("Y-m-d H:i:s");
                $save = \Model\ShipmentsModel::model()->update(@$model);
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
                $delete = \Model\ShipmentsModel::model()->delete($model);
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