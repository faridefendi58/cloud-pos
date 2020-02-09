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
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['POST'], '/update', [$this, 'update']);
        $app->map(['POST'], '/delete', [$this, 'delete']);
        $app->map(['GET'], '/list-transfer', [$this, 'get_list_transfer']);
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
                'message' => "Data warehouse tidak ditemukan.",
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
            $model = new \Model\WarehousesModel();
            if (!empty($params['title'])) {
                $model->title = $params['title'];
            }

            if (!empty($params['address'])) {
                $model->address = $params['address'];
            }

            if (!empty($params['phone'])) {
                $model->phone = $params['phone'];
            }

            if (!empty($params['group_name'])) {
                $whmodel = \Model\WarehouseGroupsModel::model()->findByAttributes(['title' => $params['group_name']]);
                if ($whmodel instanceof \RedBeanPHP\OODBBean) {
                    $model->group_id = $whmodel->id;
                }
            }

            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $params['admin_id'];
            $save = \Model\WarehousesModel::model()->save(@$model);
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
            $model = \Model\WarehousesModel::model()->findByPk($params['id']);
            if (!empty($params['title'])) {
                $model->title = $params['title'];
            }

            if (!empty($params['address'])) {
                $model->address = $params['address'];
            }

            if (!empty($params['phone'])) {
                $model->phone = $params['phone'];
            }

            if (!empty($params['group_name'])) {
                $whmodel = \Model\WarehouseGroupsModel::model()->findByAttributes(['title' => $params['group_name']]);
                if ($whmodel instanceof \RedBeanPHP\OODBBean) {
                    $model->group_id = $whmodel->id;
                }
            }

            $model->updated_at = date("Y-m-d H:i:s");
            $save = \Model\WarehousesModel::model()->update(@$model);
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
            $model = \Model\WarehousesModel::model()->findByPk($params['id']);
            $used = false;
            if ($model->group_id > 0) {
                $po_model = \Model\PurchaseOrdersModel::model()->findByAttributes(['wh_group_id' => $model->group_id]);
                if ($po_model instanceof \RedBeanPHP\OODBBean) {
                    $used = true;
                }
            }
            $ii_model = \Model\InventoryIssuesModel::model()->findByAttributes(['warehouse_id' => $model->id]);
            $ti_model = \Model\TransferIssuesModel::model()->findByAttributes(['warehouse_from' => $model->id]);
            $ti_to_model = \Model\TransferIssuesModel::model()->findByAttributes(['warehouse_to' => $model->id]);
            if ($used || $ii_model instanceof \RedBeanPHP\OODBBean
                || $ti_model instanceof \RedBeanPHP\OODBBean
                || $ti_to_model instanceof \RedBeanPHP\OODBBean) {

                $model->active = \Model\WarehousesModel::STATUS_DISABLED;
                $model->updated_at = date("Y-m-d H:i:s");
                $save = \Model\WarehousesModel::model()->update(@$model);
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
                $delete = \Model\WarehousesModel::model()->delete($model);
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

	public function get_list_transfer($request, $response, $args)
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
        $whmodel = new \Model\WarehouseTransferRelationsModel();

        if (isset($params['rel_type'])) {
            $items = $whmodel->getAllRelatedWarehouses(['warehouse_id' => $params['warehouse_id'], 'rel_type' => $params['rel_type']]);
        } else {
            $items = $whmodel->getAllRelatedWarehouses(['warehouse_id' => $params['warehouse_id']]);
        }

        if (is_array($items)){
            $result['success'] = 1;
            $result['data'] = $items;
        } else {
            $result = [
                'success' => 0,
                'message' => "Data warehouse tidak ditemukan.",
            ];
        }

        return $response->withJson($result, 201);
    }
}
