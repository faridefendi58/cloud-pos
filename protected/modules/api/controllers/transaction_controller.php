<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;
use Model\InvoicesModel;

class TransactionController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['GET'], '/detail', [$this, 'get_detail']);
        $app->map(['POST'], '/complete', [$this, 'complete']);
        $app->map(['GET'], '/list', [$this, 'get_list']);
        $app->map(['POST'], '/complete-payment', [$this, 'complete_payment']);
        $app->map(['POST'], '/refund', [$this, 'refund']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['create', 'detail', 'complete', 'list', 'complete-payment', 'refund'],
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

        $result = ['success' => 0];
        $params = $request->getParams();
        //$qry = json_decode('{"items_belanja":[{"id":"12","name":"Daging durian","cost_price":"55312.5","unit_price":"53000","qty":1,"discount":0}],"items_payment":{"amount_tendered":"53000","change":0},"customer":{"email":"farid@localhost.com"},"promocode":null}', true);

        if (isset($params['admin_id'])) {
            $model2 = new \Model\InvoicesModel();
            if (isset($params['customer'])) {
                $cust_id = 0;
                if (isset($params['customer']['id']) && !empty(($params['customer']['id']))) {
                    $model2->customer_id = $params['customer']['id'];
                    $cust_id = $params['customer']['id'];
                } else {
                    if (isset($params['customer']['email']) && ($params['customer']['email'])!="-") {
                        $cmodel = \Model\CustomersModel::model()->findByAttributes(['email' => $params['customer']['email']]);
                        if ($cmodel instanceof \RedBeanPHP\OODBBean) {
                            $model2->customer_id = $cmodel->id;
                            $cust_id = $cmodel->id;
                        }
                    }
                    if (($cust_id == 0) && isset($params['customer']['phone'])) {
                        $cmodel = \Model\CustomersModel::model()->findByAttributes(['telephone' => $params['customer']['phone']]);
                        if ($cmodel instanceof \RedBeanPHP\OODBBean) {
                            $model2->customer_id = $cmodel->id;
                            $cust_id = $cmodel->id;
                        }
                    }

                }
                // if still empty
                if ($cust_id == 0) {
                    $cmodel = new \Model\CustomersModel();
                    $cmodel->name = $params['customer']['name'];
                    $cmodel->email = (!empty($params['customer']['email']))? $params['customer']['email'] : "-";
                    $cmodel->telephone = $params['customer']['phone'];
                    $cmodel->address = $params['customer']['address'];
                    $cmodel->status = \Model\CustomersModel::STATUS_ACTIVE;
                    $cmodel->created_at = date("Y-m-d H:i:s");
                    $cmodel->created_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
                    $csave = \Model\CustomersModel::model()->save(@$cmodel);
                    if ($csave) {
                        $model2->customer_id = $cmodel->id;
                        $cust_id = $cmodel->id;
                    }
                }
            }

            $model2->status = \Model\InvoicesModel::STATUS_PAID;
            if (isset($params['payment']) && is_array(($params['payment']))) {
                foreach ($params['payment'] as $ip => $pymnt) {
                    if ($pymnt['type'] == 'cash' && !empty($pymnt['amount_tendered'])) {
                        $model2->cash = $this->money_unformat($pymnt['amount_tendered']);
                    }
                }
            }
            if (isset($params['transaction_type'])) {
                if ($params['transaction_type'] == \Model\InvoicesModel::STATUS_REFUND)
                    $model2->status = \Model\InvoicesModel::STATUS_REFUND;
                elseif ($params['transaction_type'] == \Model\InvoicesModel::STATUS_UNPAID)
                    $model2->status = \Model\InvoicesModel::STATUS_UNPAID;
            } else {
                $model2->status = \Model\InvoicesModel::STATUS_PAID;
            }
            $model2->serie = $model2->getInvoiceNumber($model2->status, 'serie');
            $model2->nr = $model2->getInvoiceNumber($model2->status, 'nr');
            if ($model2->status == \Model\InvoicesModel::STATUS_PAID) {
                $model2->paid_at = date(c);
                $model2->paid_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
            }

            if ($model2->status == \Model\InvoicesModel::STATUS_REFUND) {
                $model2->refunded_at = date(c);
                $model2->refunded_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
            }

            $model2->config = json_encode(
                [
                    'items_belanja' => $params['items_belanja'],
                    'payment' => $params['payment'],
                    'customer' => $params['customer'],
                    //'promocode' => $params['promocode'],
					'discount' => $params['discount'],
					'shipping' => $params['shipping']
                ]
            );
            $model2->currency_id = 1;
            $model2->change_value = 1;
            if (!empty($params['notes'])) {
                $model2->notes = $params['notes'];
            }
            if (!empty($params['warehouse_id'])) {
                $model2->warehouse_id = $params['warehouse_id'];
            }
            if (!empty($params['shipping']) && array_key_exists("pickup_date", $params['shipping'][0]) && !empty($params['shipping'][0]['pickup_date'])) {
				if (strtotime($params['shipping'][0]['pickup_date']) > 0) {
                	$model2->delivered_plan_at = date("Y-m-d H:i:s", strtotime($params['shipping'][0]['pickup_date']));
				} else {
					$model2->delivered_plan_at = date("Y-m-d H:i:s", strtotime($params['shipping'][0]['date_added']));
				}
            } else {
				$model2->delivered_plan_at = date("Y-m-d H:i:s");
			}
            $model2->created_at = date("Y-m-d H:i:s");
            $model2->created_by = (isset($params['admin_id']))? $params['admin_id'] : 1;

            $save = \Model\InvoicesModel::model()->save(@$model2);
            if ($save) {
                $omodel = new \Model\OrdersModel();
                $group_id = $omodel->getNextGroupId();
                $items_belanja = $params['items_belanja'];
                $success = true;
                foreach ($items_belanja as $index => $data) {
                    $model3 = new \Model\OrdersModel();
                    $model3->product_id = $data['barcode'];
                    if (!empty($model2->customer_id)) {
                        $model3->customer_id = $model2->customer_id;
                    }
                    $model3->title = $data['name'];
                    $model3->group_id = $group_id;
                    $model3->group_master = ($index == 0) ? 1 : 0;
                    $model3->invoice_id = $model2->id;
                    $model3->quantity = $data['qty'];
                    $model3->price = $data['unit_price'];
                    $model3->discount = $data['discount'];
                    if (isset($data['cost_price'])) {
                        $model3->cost_price = $data['cost_price'];
                    } else {
                        $model3->cost_price = $data['unit_price'];
                    }

                    if ($params['promocode']) {
                        $model3->promo_id = $params['promocode'];
                    }
                    $model3->currency_id = $model2->currency_id;
                    $model3->change_value = $model2->change_value;
                    $model3->type = (!empty($params['payment_type']))? $params['payment_type'] : 1;
                    if (!empty($params['warehouse_id'])) {
                        $model3->warehouse_id = $params['warehouse_id'];
                    }
                    $model3->status = 1;
                    $model3->created_at = date("Y-m-d H:i:s");
                    $model3->created_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
                    $save2 = \Model\OrdersModel::model()->save(@$model3);
                    if ($save2) {
                        $model4 = new \Model\InvoiceItemsModel();
                        $model4->invoice_id = $model2->id;
                        $model4->type = \Model\InvoiceItemsModel::TYPE_ORDER;
                        $model4->rel_id = $model3->id;
                        $model4->title = $model3->title;
                        $model4->quantity = $model3->quantity;
                        $model4->price = $model3->price;
                        $model4->created_at = date("Y-m-d H:i:s");
                        $model4->created_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
                        $save3 = \Model\InvoiceItemsModel::model()->save(@$model4);
                        if (!$save3) {
                            $success &= false;
                        }
                    } else {
                        $success &= false;
                        $errors = \Model\OrdersModel::model()->getErrors(true, true);
                    }
                }
                if ($success) {
                    $result = [
                        "success" => 1,
                        "id" => $model2->id,
						"invoice_number" => $model2->getInvoiceFormatedNumber(['id' => $model2->id]),
                        'message' => 'Data berhasil disimpan.'
                    ];
                } else {
                    $result['message'] = 'Data gagal disimpan';
                }
            } else {
                $result['message'] = 'Data gagal disimpan';
            }
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
        $config = [];

        if (isset($params['admin_id'])) {
            $inv_model = new \Model\InvoicesModel();

            $inv_data = [];
            if (isset($params['invoice_number'])) {
                $series = $inv_model->getSeries();
                $serie = null;
                foreach ($series as $i => $s_row) {
                    if (strpos($params['invoice_number'], $s_row['serie'])!== false) {
                        $serie = $s_row['serie'];
                    }
                }

                $nr = 0;
                if (!empty($serie)) {
                    $exps = explode($serie, $params['invoice_number']);
                    $nr = (int)$exps[1];
                }

                if ($nr > 0) {
                    $inv_data = $inv_model->getItem(['serie' => $serie, 'nr' => $nr]);
                    if (in_array("config", array_keys($inv_data))) {
                        $config = json_decode($inv_data['config'], true);
                    }
                }
            }

            if (isset($params['invoice_id'])) {
                $inv_data = $inv_model->getItem(['id' => $params['invoice_id']]);
                if (in_array("config", array_keys($inv_data))) {
                    $config = json_decode($inv_data['config'], true);
                }
            }

            if (array_key_exists("serie", $inv_data) && array_key_exists("nr", $inv_data)) {
                $zero = str_repeat('0',4-strlen($inv_data['nr']));
                $inv_data['invoice_number'] = $inv_data['serie'].$zero.$inv_data['nr'];
                unset($inv_data['serie']);
                unset($inv_data['nr']);
            }

            if (is_array($config)) {
                unset($inv_data['config']);
                $inv_data = $inv_data + $config;
                if (array_key_exists("customer", $inv_data)) {
                    $inv_data['customer']['id'] = $inv_data['customer_id'];
                    unset($inv_data['customer_id']);
                    unset($inv_data['customer_name']);
                }

                if (array_key_exists("promocode", $inv_data)) {
                    unset($inv_data['promocode']);
                }
            }

            $result['success'] = 1;
            $result['data'] = $inv_data;
        }

        return $response->withJson($result, 201);
    }

    /**
     * Completing the invoice mean mark as paid and mark as delivered
     * usage curl -d "admin_id=1&invoice_id=13&payment%5B0%5D%5Btype%5D=cash_receive&pay%5D%5Btype%5D=transfer_bri&payment%5B1%5D%5Bamount_tendered%5D=5000" -X POST http://hostname/api/transaction/complete?api-key=[the_api_key]
     * @param $request
     * @param $response
     * @param $args
     * @return mixed
     */
    public function complete($request, $response, $args)
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

        if (!empty($params['invoice_id'])) {
            $model = \Model\InvoicesModel::model()->findByPk($params['invoice_id']);
            $configs = [];
            if (!empty($model->config)) {
                $configs = json_decode($model->config, true);
            }

            $has_new_config = false;
            if (isset($params['payment'])) {
                if (in_array('payment', array_keys($configs))) {
                    if (is_array($configs['payment'])) {
                        $configs['payment'] = array_merge($configs['payment'], $params['payment']);
                        $has_new_config = true;
                    } else {
                        // if current payment null or false
                        $configs['payment'] = $params['payment'];
                        $has_new_config = true;
                    }
                } else {
                    $configs['payment'] = $params['payment'];
                    $has_new_config = true;
                }
            }

            $i_model = new \Model\InvoicesModel();
            if ($model->status != \Model\InvoicesModel::STATUS_PAID) {
                $model->status = \Model\InvoicesModel::STATUS_PAID;
                $model->serie = $i_model->getInvoiceNumber($model->status, 'serie');
                $model->nr = $i_model->getInvoiceNumber($model->status, 'nr');
                $model->paid_at = date("Y-m-d H:i:s");
                $model->paid_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
            }

			if (empty($model->delivered_plan_at)) {
				$model->delivered_plan_at = $model->paid_at;
			}

            $model->delivered = 1;
            $model->delivered_at = date("Y-m-d H:i:s");
            $model->delivered_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
            if ($has_new_config) {
                $model->config = json_encode($configs);
            }
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
            $update = \Model\InvoicesModel::model()->update($model);
            if ($update) {
                // real update the stock
                if (array_key_exists("items_belanja", $configs)) {
                    $smodel = new \Model\ProductStocksModel();
                    foreach ($configs['items_belanja'] as $i => $item_belanja) {
                        $stock = $smodel->getStockByQuantity([
                            'product_id' => $item_belanja['barcode'],
                            'warehouse_id' => $model->warehouse_id,
                            'quantity' => $item_belanja['qty']
                        ]);

                        if ($stock instanceof \RedBeanPHP\OODBBean) {
                            $stock->quantity = $stock->quantity - $item_belanja['qty'];
                            $stock->updated_at = date("Y-m-d H:i:s");
                            $stock->updated_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
                            $update_stock = \Model\ProductStocksModel::model()->update($stock);
                        }
                    }
                }

                $result['success'] = 1;
                $result['message'] = 'Data berhasil disimpan.';
                $result['invoice_number'] = $i_model->getInvoiceFormatedNumber(['id' => $model->id]);
            }
        }

        return $response->withJson($result, 201);
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

        $result = ['success' => 0];
        $params = $request->getParams();
        $i_model = new \Model\InvoicesModel();
		$limit = 20;
		if (isset($params['limit'])) {
			$limit = $params['limit'];
			$params['limit'] = $params['limit'] + 1;
		} else {
			$params['limit'] = 21;
		}
        $items = $i_model->getData($params);
        if (is_array($items)){
            $result['success'] = 1;
			$ids = [];
            foreach ($items as $i => $item) {
                $items[$i]['invoice_number'] = $i_model->getInvoiceFormatedNumber2($item['serie'], $item['nr']);
                $items[$i]['config'] = json_decode($item['config'], true);
                $status_order = 'Lunas';
                if ($item['status'] == 0) {
                    if ($item['delivered'] == 0) {
                        $status_order = 'Belum Lunas';
                    } elseif ($item['delivered'] == 1) {
                        $status_order = 'Hutang Tempo';
                    }
                } else {
                    if ($item['delivered'] == 0) {
                        $status_order = 'Lunas';
                    } elseif ($item['delivered'] == 1) {
                        $status_order = 'Selesai';
                    }
                }
				$items[$i]['status_order'] = $status_order;
				array_push($ids, $item['id']);
            }
            $result['data'] = $items;
			$result['next_id'] = 0;
			if (count($items) > $limit) {
				$result['next_id'] = max($ids);
				unset($items[$limit]);
			}
        } else {
            $result = [
                'success' => 0,
                'message' => "Data transaksi tidak ditemukan.",
            ];
        }

        return $response->withJson($result, 201);
    }

	public function complete_payment($request, $response, $args)
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

        if (!empty($params['invoice_id'])) {
            $model = \Model\InvoicesModel::model()->findByPk($params['invoice_id']);
            $configs = [];
            if (!empty($model->config)) {
                $configs = json_decode($model->config, true);
            }

            $has_new_config = false;
            if (isset($params['payment'])) {
                if (in_array('payment', array_keys($configs))) {
                    if (is_array($configs['payment'])) {
                        $configs['payment'] = array_merge($configs['payment'], $params['payment']);
                        $has_new_config = true;
                    } else {
                        // if current payment null or false
                        $configs['payment'] = $params['payment'];
                        $has_new_config = true;
                    }
                } else {
                    $configs['payment'] = $params['payment'];
                    $has_new_config = true;
                }
            }

            $i_model = new \Model\InvoicesModel();
            if ($model->status != \Model\InvoicesModel::STATUS_PAID) {
                $model->status = \Model\InvoicesModel::STATUS_PAID;
                $model->serie = $i_model->getInvoiceNumber($model->status, 'serie');
                $model->nr = $i_model->getInvoiceNumber($model->status, 'nr');
                $model->paid_at = date("Y-m-d H:i:s");
                $model->paid_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
            }

			if (empty($model->delivered_plan_at)) {
				$model->delivered_plan_at = $model->paid_at;
			}

            if ($has_new_config) {
                $model->config = json_encode($configs);
            }
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
            $update = \Model\InvoicesModel::model()->update($model);
            if ($update) {
                $result['success'] = 1;
                $result['message'] = 'Data berhasil disimpan.';
                $result['invoice_number'] = $i_model->getInvoiceFormatedNumber(['id' => $model->id]);
            }
        }

        return $response->withJson($result, 201);
    }

    public function refund($request, $response, $args)
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
        // just ex
        /*$json = '{"items":[{"id":"12","name":"Daging Durian","total_qty":"2","returned_qty":"1","refunded_qty":"1","price":"90000"}],"payments":{"type":"cash","amount":"90000"},"admin_id":"1","invoice_id":"10"}';
        $qry = json_decode($json, true);
        $http_qry = http_build_query($qry);*/

        if (isset($params['admin_id']) && isset($params['invoice_id'])) {
            $model = \Model\InvoicesModel::model()->findByPk($params['invoice_id']);

            $model2 = new \Model\InvoicesModel();
            $model2->customer_id = $model->customer_id;
            $model2->status = \Model\InvoicesModel::STATUS_REFUND;
            if (isset($params['payments']) && is_array(($params['payments']))) {
                foreach ($params['payments'] as $ip => $pymnt) {
                    if ($pymnt['type'] == 'cash' && !empty($pymnt['amount'])) {
                        $model2->cash = $this->money_unformat($pymnt['amount']);
                    }
                }
            }
            $model2->serie = $model2->getInvoiceNumber($model2->status, 'serie');
            $model2->nr = $model2->getInvoiceNumber($model2->status, 'nr');
            if ($model2->status == \Model\InvoicesModel::STATUS_REFUND) {
                $model2->refunded_at = date(c);
                $model2->refunded_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
            }

            $model2->config = json_encode(
                [
                    'items' => $params['items'],
                    'payments' => $params['payments'],
                ]
            );

            $model2->currency_id = 1;
            $model2->change_value = 1;
            if (!empty($params['notes'])) {
                $model2->notes = $params['notes'];
            }

            $model2->warehouse_id = $model->warehouse_id;
            $model2->created_at = date("Y-m-d H:i:s");
            $model2->created_by = (isset($params['admin_id']))? $params['admin_id'] : 1;

            $save = \Model\InvoicesModel::model()->save(@$model2);
            if ($save) {
                $items = $params['items'];
                $success = true;
                foreach ($items as $index => $data) {
                    $model3 = new \Model\InvoiceItemsModel();
                    $model3->invoice_id = $model2->id;
                    $model3->type = \Model\InvoiceItemsModel::TYPE_REFUND;
                    // find the order
                    $o_model = \Model\OrdersModel::model()->findByAttributes(['invoice_id' => $model->id, 'product_id' => $data['id']]);
					if ($o_model instanceof \RedBeanPHP\OODBBean) {
                    	$model3->rel_id = $o_model->id;
					} else {
						$ii_model = \Model\InvoiceItemsModel::model()->findByAttributes(['invoice_id' => $model->id, 'title' => $data['name']]);
						if ($ii_model instanceof \RedBeanPHP\OODBBean) {
		                	$model3->rel_id = $ii_model->rel_id;
						}
					}
                    $model3->title = $data['name'];
                    $model3->quantity = $data['refunded_qty'];
                    $model3->price = $data['price'];
                    $model3->created_at = date("Y-m-d H:i:s");
                    $model3->created_by = (isset($params['admin_id']))? $params['admin_id'] : 1;
                    $save2 = \Model\InvoiceItemsModel::model()->save(@$model3);
                    if (!$save2) {
                        $success &= false;
                    }
                }
                if ($success) {
                    $result = [
                        "success" => 1,
                        "id" => $model2->id,
                        "invoice_number" => $model2->getInvoiceFormatedNumber(['id' => $model2->id]),
                        'message' => 'Data berhasil disimpan.'
                    ];
                } else {
                    $result['message'] = 'Data gagal disimpan';
                }
            } else {
                $result['message'] = 'Data gagal disimpan';
            }
        }

        return $response->withJson($result, 201);
    }
}
