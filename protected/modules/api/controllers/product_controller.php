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
        $pmodel = new \Model\ProductsModel();

        $warehouse_id = 0;
        if (is_array($params) && isset($params['warehouse_id'])) {
            $warehouse_id = $params['warehouse_id'];
        }

        if (is_array($params) && isset($params['warehouse_name']) && ($warehouse_id == 0)) {
            $whmodel = \Model\WarehousesModel::model()->findByAttributes(['title' => $params['warehouse_name']]);
            if ($whmodel instanceof \RedBeanPHP\OODBBean) {
                $warehouse_id = $whmodel->id;
            }
        }

        if ($warehouse_id > 0) {
            $wpmodel = new \Model\WarehouseProductsModel();
            $psmodel = new \Model\ProductStocksModel();
            $ppmodel = new \Model\ProductPricesModel();
            $items = $wpmodel->getData(['warehouse_id' => $warehouse_id]);
            if (is_array($items) && count($items) > 0) {
                $result['success'] = 1;
                $result['data'] = [];
                $result['discount'] = [];
                foreach ($items as $i => $item) {
                    // geting the prices
                    $prices = json_decode($item['configs'], true);
                    //$base_price = $prices[0]['price']; // wrong if there is no 1-4 qty config price
                    $base_price = $ppmodel->getPrice($item['product_id']); // default to base config price
                    foreach ($prices as $i => $price) {
                        if ($price['quantity'] == 1) {
                            $base_price = $price['price'];
                        }
                        $prices[$i]['product_id'] = $item['product_id'];
                        $prices[$i]['unit'] = $item['product_unit'];
                    }

                    $the_stock = 0;
                    if (isset($params['with_stock']) && $params['with_stock'] > 0) {
                        $stock = $psmodel->getStock(['warehouse_id' => $warehouse_id, 'product_id' => $item['product_id']]);
                        if ($stock > 0) {
                            $the_stock = $stock;
                        }
                    }

                    $config = [];
                    if (!empty($item['product_config'])) {
                        $config = json_decode($item['product_config'], true);
                    }
                    array_push($result['data'], [
                            'id' => $item['product_id'],
                            'title' => $item['product_name'],
                            'unit' => $item['product_unit'],
                            'price' => $base_price,
                            'priority' => $item['priority'],
                            'stock' => $the_stock,
                            'config' => $config
                        ]);

                    if (isset($params['with_discount']) && $params['with_discount'] > 0) {
                        array_push(
                            $result['discount'], $prices
                        );
                    }

                    /*if (isset($params['with_stock']) && $params['with_stock'] > 0) {
                        $stock = \Model\ProductStocksModel::model()->findByAttributes(['warehouse_id' => $warehouse_id, 'product_id' => $item['product_id']]);
                        $the_stock = [$item['product_id'] => 0];
                        if ($stock instanceof \RedBeanPHP\OODBBean) {
                            $the_stock[$item['product_id']] = $stock->quantity;
                        }
                        array_push(
                            $result['stock'], $the_stock
                        );
                    }*/
                }
            }

            return $response->withJson($result, 201);
        }

        $items = $pmodel->getData(['status' => \Model\ProductsModel::STATUS_ENABLED]);
        if (is_array($items)){
            $result['success'] = 1;
            $ppmodel = new \Model\ProductPricesModel();
            if (is_array($params) && isset($params['simply']) && $params['simply'] == 1) {
                $result['data'] = [];
                foreach ($items as $i => $item) {
                    array_push(
                        $result['data'], [
                            'id' => $item['id'],
                            'title' => $item['title'],
                            'unit' => $item['unit'],
                            'price' => $ppmodel->getPrice($item['id'])
                            ]
                    );
                }
            } else {
                $result['data'] = $items;
            }

            if (is_array($params) && isset($params['with_discount'])) {
                foreach ($items as $i => $item) {
                    $result['discount'][] = $ppmodel->getData($item['id']);
                }
            }
        } else {
            $result = [
                'success' => 0,
                'message' => "Data product tidak ditemukan.",
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
            $model = new \Model\ProductsModel();
            if (!empty($params['title'])) {
                $model->title = $params['title'];
            }

            if (!empty($params['code'])) {
                $model->code = $params['code'];
            }

            if (!empty($params['unit'])) {
                $model->unit = $params['unit'];
            }

            if (!empty($params['description'])) {
                $model->description = $params['description'];
            }

            if (!empty($params['product_category'])) {
                $cmodel = \Model\ProductCategoriesModel::model()->findByAttributes(['title' => $params['product_category']]);
                if ($cmodel instanceof \RedBeanPHP\OODBBean) {
                    $model->product_category_id = $cmodel->id;
                }
            } else {
                $model->product_category_id = 1;
            }

            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $params['admin_id'];
            $save = \Model\ProductsModel::model()->save(@$model);
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
            $model = \Model\ProductsModel::model()->findByPk($params['id']);
            if (!empty($params['title'])) {
                $model->title = $params['title'];
            }

            if (!empty($params['code'])) {
                $model->code = $params['code'];
            }

            if (!empty($params['unit'])) {
                $model->unit = $params['unit'];
            }

            if (!empty($params['description'])) {
                $model->description = $params['description'];
            }

            $model->updated_at = date("Y-m-d H:i:s");
            $save = \Model\ProductsModel::model()->update(@$model);
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
            $model = \Model\ProductsModel::model()->findByPk($params['id']);
            $po_model = \Model\PurchaseOrderItemsModel::model()->findByAttributes(['product_id' => $model->id]);
            $ti_model = \Model\TransferIssueItemsModel::model()->findByAttributes(['product_id' => $model->id]);
            $ii_model = \Model\InventoryIssueItemsModel::model()->findByAttributes(['product_id' => $model->id]);
            if ($po_model instanceof \RedBeanPHP\OODBBean
                || $ti_model instanceof \RedBeanPHP\OODBBean
                || $ii_model instanceof \RedBeanPHP\OODBBean) {

                $model->active = \Model\ProductsModel::STATUS_DISABLED;
                $model->updated_at = date("Y-m-d H:i:s");
                $save = \Model\ProductsModel::model()->update(@$model);
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
                $delete = \Model\ProductsModel::model()->delete($model);
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
