<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class TransactionController extends BaseController
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
            if ($model2->status == \Model\InvoicesModel::STATUS_PAID)
                $model2->paid_at = date(c);
            $model2->config = json_encode(
                [
                    'items_belanja' => $params['items_belanja'],
                    'payment' => $params['payment'],
                    'customer' => $params['customer'],
                    'promocode' => $params['promocode'],
                ]
            );
            $model2->currency_id = 1;
            $model2->change_value = 1;
            if (!empty($params['notes'])) {
                $model2->notes = $params['notes'];
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
                    $model3->product_id = $data['id'];
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
                        $model4->price = $model3->quantity * ($model3->price - $model3->discount);
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