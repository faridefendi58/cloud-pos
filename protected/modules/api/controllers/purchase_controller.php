<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class PurchaseController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['POST'], '/create', [$this, 'create']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['create'],
                'users'=> ['@'],
            ]
        ];
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

        $result = [];
        $params = $request->getParams();
        if (isset($params['items'])) {
            $purchase_items = [];
            $items = explode("-", $params['items']);
            if (is_array($items)) {
                foreach ($items as $i => $item) {
                    $p_count = explode(",", $item);
                    if (is_array($p_count)) {
                        $purchase_items[$p_count[0]] = (int) $p_count[1];
                    }
                }
            }

            $purchase_prices = [];
            if (isset($params['prices'])) {
                $prices = explode("-", $params['prices']);
                if (is_array($prices)) {
                    foreach ($prices as $i => $price) {
                        $pr_count = explode(",", $price);
                        if (is_array($pr_count)) {
                            $purchase_prices[$pr_count[0]] = (int) $pr_count[1];
                        }
                    }
                }
            }

            if (count($purchase_items) <= 0) {
                $result = ["success" => 0, "message" => "Pastikan pilih item sebelum disimpan."];
                return $response->withJson($result, 201);
            }

            if (isset($params['supplier_name'])) {
                $spmodel = \Model\SuppliersModel::model()->findByAttributes(['name' => $params['supplier_name']]);
                if ($spmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['supplier_id'] = $spmodel->id;
                }
            }

            if (isset($params['shipment_name'])) {
                $shmodel = \Model\ShipmentsModel::model()->findByAttributes(['title' => $params['shipment_name']]);
                if ($shmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['shipment_id'] = $shmodel->id;
                }
            }

            if (empty($params['supplier_id']) || empty($params['shipment_id'])) {
                $result = ["success" => 0, "message" => "Supplier atau Cara pengiriman tidak boleh kosong."];
                return $response->withJson($result, 201);
            }

            if (isset($params['wh_group_name'])) {
                $whgmodel = \Model\WarehouseGroupsModel::model()->findByAttributes(['title' => $params['wh_group_name']]);
                if ($whgmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['wh_group_id'] = $whgmodel->id;
                }
            }

            $model = new \Model\PurchaseOrdersModel();
            $po_number = \Pos\Controllers\PurchasesController::get_po_number();
            $model->po_number = $po_number['serie_nr'];
            $model->po_serie = $po_number['serie'];
            $model->po_nr = $po_number['nr'];
            $model->price_netto = 0;
            if (isset($params['supplier_id']))
                $model->supplier_id = $params['supplier_id'];
            $model->date_order = date("Y-m-d H:i:s");
            if (isset($params['shipment_id']))
                $model->shipment_id = $params['shipment_id'];
            if (isset($params['wh_group_id']))
                $model->wh_group_id = $params['wh_group_id'];
            $model->status = \Model\PurchaseOrdersModel::STATUS_ON_PROCESS;
            if (isset($params['notes']))
                $model->notes = $params['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
            $save = \Model\PurchaseOrdersModel::model()->save(@$model);
            if ($save) {
                $tot_price = 0;
                foreach ($purchase_items as $product_id => $quantity) {
                    $product = \Model\ProductsModel::model()->findByPk($product_id);
                    $imodel[$product_id] = new \Model\PurchaseOrderItemsModel();
                    $imodel[$product_id]->po_id = $model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->available_qty = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    if (isset($purchase_prices[$product_id]))
                        $imodel[$product_id]->price = $purchase_prices[$product_id];
                    else
                        $imodel[$product_id]->price = $product->current_cost;
                    $imodel[$product_id]->created_at = date("Y-m-d H:i:s");
                    $imodel[$product_id]->created_by = $model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\PurchaseOrderItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($imodel[$product_id]->price * $quantity);
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $pomodel = \Model\PurchaseOrdersModel::model()->findByPk($model->id);
                    $pomodel->price_netto = $tot_price;
                    $update = \Model\PurchaseOrdersModel::model()->update($pomodel);

                    $result = [
                        "success" => 1,
                        "id" => $model->id,
                        'message' => 'Data berhasi disimpan.',
                        "issue_number" => $model->po_number
                    ];
                } else {
                    $result = ["success" => 0, "message" => "Tidak ada item yang dapat disimpan."];
                }
            } else {
                $result = [
                    "success" => 0,
                    "message" => \Model\PurchaseOrdersModel::model()->getErrors(false, false, false)
                ];
            }
        }

        return $response->withJson($result, 201);
    }
}