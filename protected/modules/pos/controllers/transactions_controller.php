<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class TransactionsController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['GET'], '/detail/[{id}]', [$this, 'detail']);
        $app->map(['POST'], '/scan', [$this, 'scan']);
        $app->map(['POST'], '/delete-item/[{id}]', [$this, 'delete_item']);
        $app->map(['GET'], '/cart', [$this, 'cart']);
        $app->map(['POST'], '/update-qty', [$this, 'update_qty']);
        $app->map(['POST'], '/cancel-transaction', [$this, 'cancel_transaction']);
        $app->map(['POST'], '/discount', [$this, 'discount']);
        $app->map(['POST'], '/payment-request', [$this, 'payment_request']);
        $app->map(['POST'], '/change-request', [$this, 'change_request']);
        $app->map(['POST'], '/set-customer', [$this, 'set_customer']);
        $app->map(['POST'], '/set-type', [$this, 'set_type']);
        $app->map(['POST'], '/set-warehouse', [$this, 'set_warehouse']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete', 'detail',
                    'scan', 'delete-item', 'cart', 'update-qty',
                    'payment-request', 'change-request', 'set-customer',
                    'set-type', 'set-warehouse'
                ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'detail'],
                'expression' => $this->hasAccess('pos/transactions/read'),
            ],
            ['allow',
                'actions' => ['create', 'cancel-transaction', 'discount'],
                'expression' => $this->hasAccess('pos/transactions/create'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('pos/transactions/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('pos/transactions/delete'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }
        
        $model = new \Model\InvoicesModel();
        $invoices = $model->getData();

        return $this->_container->module->render(
            $response, 
            'transactions/view.html',
            [
                'invoices' => $invoices
            ]
        );
    }

    public function create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $pmodel = new \Model\ProductsModel();
        $products = $pmodel->getData();

        $items_belanja = $_SESSION['items_belanja'];
        $selected_customer = $_SESSION['customer'];
        $customers = \Model\CustomersModel::model()->findAllByAttributes(['status' => \Model\CustomersModel::STATUS_ACTIVE]);
        $transaction_type = $_SESSION['transaction_type'];
        $warehouse_id = $_SESSION['warehouse_id'];

        return $this->_container->module->render(
            $response,
            'transactions/create.html',
            [
                'products' => $products,
                'items_belanja' => $items_belanja,
                'sub_total' => $this->getSubTotal(),
                'customers' => $customers,
                'selected_customer' => (!empty($selected_customer))? $selected_customer : false,
                'transaction_type' => (!empty($transaction_type))? $transaction_type : 1,
                'warehouse_id' => (!empty($warehouse_id))? $warehouse_id : 0
            ]
        );
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $pmodel = new \Model\ProductsModel();
        $products = $pmodel->getData();

        $imodel = \Model\InvoicesModel::model()->findByPk($args['id']);
        $configs = json_decode($imodel->config, true);

        $items_belanja = $configs['items_belanja'];
        if (array_key_exists('customer', $configs)) {
            $selected_customer = $configs['customer'];
        } else {
            $selected_customer = $imodel->customer_id;
        }

        $customers = \Model\CustomersModel::model()->findAllByAttributes(['status' => \Model\CustomersModel::STATUS_ACTIVE]);
        if (array_key_exists('transacation_type', $configs)) {
            $transaction_type = $configs['transaction_type'];
        } else {
            $transaction_type = $imodel->type;
        }

        return $this->_container->module->render(
            $response,
            'transactions/update.html',
            [
                'products' => $products,
                'items_belanja' => $items_belanja,
                'sub_total' => $this->getSubTotal($items_belanja),
                'customers' => $customers,
                'selected_customer' => (!empty($selected_customer))? $selected_customer : false,
                'transaction_type' => (!empty($transaction_type))? $transaction_type : 1
            ]
        );
    }

    public function delete($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\OrdersModel::model()->findByPk($args['id']);
        $delete = \Model\OrdersModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => $this->_trans->get('global', 'Your data has been successfully deleted.'),
                ], 201);
        }
    }

    public function detail($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $pmodel = new \Model\ProductsModel();
        $products = $pmodel->getData();

        $imodel = \Model\InvoicesModel::model()->findByPk($args['id']);
        $configs = json_decode($imodel->config, true);

        $items_belanja = $configs['items_belanja'];
        if (array_key_exists('customer', $configs)) {
            $selected_customer = $configs['customer'];
        } else {
            $selected_customer = $imodel->customer_id;
        }

        $customer = \Model\CustomersModel::model()->findByPk($selected_customer);
        if (array_key_exists('transacation_type', $configs)) {
            $transaction_type = $configs['transaction_type'];
        } else {
            $transaction_type = $imodel->type;
        }

        return $this->_container->module->render(
            $response,
            'transactions/detail.html',
            [
                'products' => $products,
                'items_belanja' => $items_belanja,
                'sub_total' => $this->getSubTotal($items_belanja),
                'customer' => $customer,
                'selected_customer' => (!empty($selected_customer))? $selected_customer : false,
                'transaction_type' => (!empty($transaction_type))? $transaction_type : 1,
                'invoice' => $imodel
            ]
        );
    }

    public function scan($request, $response, $args)
    {
        if (isset($_POST['item'])) {
            $model = \Model\ProductsModel::model()->findByPk($_POST['item']);

            // avoid double execution
            $current_time = time();
            if(isset($_SESSION['Scan']) && !empty($_SESSION['Scan'])) {
                $selisih = $current_time - $_SESSION['Scan'];
                if ($selisih <= 5) {
                    return $response->withJson(
                        [
                            'status' => 'success',
                            'message' => ucfirst(strtolower($model->title)).' '. $this->_trans->get('global', 'is sucessfully added.'),
                            'sub_total' => $this->getSubTotal()
                        ], 201);
                } else {
                    $_SESSION['Scan'] = $current_time;
                }
            } else {
                $_SESSION['Scan'] = $current_time;
            }

            if (!isset($_SESSION['transaction_type']))
                $_SESSION['transaction_type'] = \Model\InvoicesModel::STATUS_PAID;

            if (!$model instanceof \RedBeanPHP\OODBBean)
                $success = false;
            $stmodel = new \Model\ProductStocksModel();
            //$stock = $stmodel->getTotalStock($model->id);
            $prmodel = new \Model\ProductPricesModel();
            $unit_price = $prmodel->getPrice($model->id, 1);
            if ($_SESSION['transaction_type'] == \Model\InvoicesModel::STATUS_REFUND) {
                $unit_price = -1 * $unit_price;
            }
            $items = [
                'id' => $model->id,
                'barcode' => $model->id,
                'name' => $model->title,
                'desc' => $model->description,
                'cost_price' => $model->current_cost,
                'unit_price' => $unit_price,
                'qty' => 1,
                'discount' => 0,
                'currency' => 1,
                'change_value' => 1,
            ];

            $items_belanja = $_SESSION['items_belanja'];
            $new_items_belanja = [];
            if (count($items_belanja) > 0) {
                $any = 0;
                foreach ($items_belanja as $index=>$data) {
                    if ($data['id'] == $items['id']){
                        $data['qty'] = $data['qty']+1;
                        $any = $any+1;
                    }
                    $new_items_belanja[] = $data;
                }
                if ($any <= 0)
                    array_push( $new_items_belanja, $items );
            } else {
                array_push( $new_items_belanja, $items );
            }

            $_SESSION['items_belanja'] = $new_items_belanja;

            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => ucfirst(strtolower($model->title)).' '. $this->_trans->get('global', 'is sucessfully added.'),
                    'sub_total' => $this->getSubTotal()
                ], 201);
        }
    }

    public function delete_item($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        if ($args['id'] != $_POST['id']) {
            return false;
        }

        $items_belanja = $_SESSION['items_belanja'];
        unset($items_belanja[$_POST['id']]);
        $_SESSION['items_belanja'] = $items_belanja;

        return $response->withJson(
            [
                'status' => 'success',
                'message' => $this->_trans->get('global', 'Your data has been successfully deleted.'),
                'sub_total' => $this->getSubTotal()
            ], 201);
    }

    public function cart($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $items_belanja = $_SESSION['items_belanja'];

        return $this->_container->module->render(
            $response,
            'transactions/_items.html',
            [
                'items_belanja' => $items_belanja
            ]);
    }

    public function update_qty($request, $response, $args)
    {
        $params = $request->getParams();

        $items_belanja = $_SESSION['items_belanja'];

        $id = $params['id'];
        $items_belanja[$id]['qty'] = (int)$params['qty'];

        $model = \Model\ProductsModel::model()->findByPk($items_belanja[$id]['id']);

        $prmodel = new \Model\ProductPricesModel();
        $unit_price = $prmodel->getPrice($model->id, $params['qty']);
        if ($_SESSION['transaction_type'] == \Model\InvoicesModel::STATUS_REFUND) {
            $unit_price = -1 * $unit_price;
        }
        $items_belanja[$id]['unit_price'] = $unit_price;

        $_SESSION['items_belanja'] = $items_belanja;

        $discount = $items_belanja[$id]['discount'];
        $discount_percentage = $items_belanja[$id]['discount_percentage'];

        $total = ($unit_price - $discount) * $params['qty'];

        return $response->withJson(
            [
                'status' => 'success',
                'div' => (int)$params['qty'],
                'total' => number_format($total,0,',','.'),
                'subtotal' => number_format($this->getSubTotal(),0,',','.'),
                'discount' => number_format($discount,0,',','.'),
                'discount_percentage' => round($discount_percentage, 2)
            ], 201);
    }

    private function getSubTotal($items_belanja = null)
    {
        if (empty($items_belanja))
            $items_belanja = $_SESSION['items_belanja'];
        $tot_price = 0;
        foreach ($items_belanja as $i => $item) {
            $tot_price = $tot_price + ($item['qty']*$item['unit_price']);
        }

        return $tot_price;
    }

    public function cancel_transaction($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $items_belanja = $_SESSION['items_belanja'];
        if ($items_belanja) {
            unset($_SESSION['items_belanja']);
        }

        return $response->withJson(
            [
                'status' => 'success',
                'sub_total' => $this->getSubTotal()
            ], 201);
    }

    public function discount($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $params = $request->getParams();

        $items_belanja = $_SESSION['items_belanja'];
        $discount = 0;
        if ($items_belanja) {
            $qty = $items_belanja[$params['id']]['qty'];
            $discount = $items_belanja[$params['id']]['unit_price'] * ($params['value']/100);
            $items_belanja[$params['id']]['discount'] = round($discount, 2);
            $items_belanja[$params['id']]['total'] = ($items_belanja[$params['id']]['unit_price'] - $discount)*$qty;
            $items_belanja[$params['id']]['discount_percentage'] = $params['value'];
            $_SESSION['items_belanja'] = $items_belanja;
        }

        return $response->withJson(
            [
                'status' => 'success',
                'discount' => $discount,
                'discount_percentage' => $params['value'],
                'total' => number_format($items_belanja[$params['id']]['total'], 0, ',', '.'),
                'sub_total' => $this->getSubTotal()
            ], 201);
    }

    public function payment_request($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $params = $request->getParams();
        if (isset($params['PaymentForm'])) {
            $model2 = new \Model\InvoicesModel();
            if (isset($_SESSION['customer'])) {
                $customer = $_SESSION['customer'];
                $model2->customer_id = (!empty($customer)) ? $customer->id : 0;
            }
            $model2->status = \Model\InvoicesModel::STATUS_PAID;
            $model2->cash = $this->money_unformat($params['PaymentForm']['amount_tendered']);
            if (isset($_SESSION['transaction_type'])) {
                if ($_SESSION['transaction_type'] == \Model\InvoicesModel::STATUS_REFUND)
                    $model2->status = \Model\InvoicesModel::STATUS_REFUND;
                elseif ($_SESSION['transaction_type'] == \Model\InvoicesModel::STATUS_UNPAID)
                    $model2->status = \Model\InvoicesModel::STATUS_UNPAID;
            }

            if (isset($_SESSION['warehouse_id'])) {
                $model2->warehouse_id = $_SESSION['warehouse_id'];
            } else {
                $model2->warehouse_id = 1;
            }

            $model2->serie = $model2->getInvoiceNumber($model2->status, 'serie');
            $model2->nr = $model2->getInvoiceNumber($model2->status, 'nr');
            if ($model2->status == \Model\InvoicesModel::STATUS_PAID)
                $model2->paid_at = date(c);
            $model2->config = json_encode(
                [
                    'items_belanja' => $_SESSION['items_belanja'],
                    'items_payment' => $_SESSION['items_payment'],
                    'customer' => $_SESSION['customer'],
                    'promocode' => $_SESSION['promocode'],
                ]
            );
            $model2->currency_id = 1;
            $model2->change_value = 1;
            if (!empty($params['PaymentForm']['notes'])) {
                $model2->notes = $params['PaymentForm']['notes'];
            }
            $model2->created_at = date("Y-m-d H:i:s");
            $model2->created_by = $this->_user->id;
            $save = \Model\InvoicesModel::model()->save(@$model2);
            if ($save) {
                $invoice_id = $model2->id;
                $omodel = new \Model\OrdersModel();
                $group_id = $omodel->getNextGroupId();
                $items_belanja = $_SESSION['items_belanja'];
                $success = true;
                foreach ($items_belanja as $index => $data) {
                    $model3 = new \Model\OrdersModel();
                    $model3->product_id = $data['id'];
                    $model3->customer_id = $model2->customer_id;
                    $model3->title = $data['name'];
                    $model3->group_id = $group_id;
                    $model3->group_master = ($index == 0) ? 1 : 0;
                    $model3->invoice_id = $model2->id;
                    $model3->quantity = $data['qty'];
                    $model3->price = $data['unit_price'];
                    $model3->discount = $data['discount'];
                    $model3->cost_price = $data['cost_price'];

                    if ($_SESSION['promocode']) {
                        $model3->promo_id = $_SESSION['promocode'];
                        //$model3->discount = Promo::getDiscountValue(Yii::app()->user->getState('promocode'), $model3->price);
                    }
                    $model3->currency_id = $model2->currency_id;
                    $model3->change_value = $model2->change_value;
                    $model3->type = $params['PaymentForm']['payment_type'];

                    if (isset($_SESSION['warehouse_id'])) {
                        $model3->warehouse_id = $_SESSION['warehouse_id'];
                    }

                    $model3->status = 1;
                    $model3->created_at = date("Y-m-d H:i:s");
                    $model3->created_by = $this->_user->id;
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
                        $model4->created_by = $this->_user->id;
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
                    unset($_SESSION['items_belanja']);
                    unset($_SESSION['items_payment']);
                    unset($_SESSION['customer']);
                    unset($_SESSION['promocode']);
                    unset($_SESSION['transaction_type']);
                    unset($_SESSION['warehouse_id']);
                }
            }

            return $response->withJson(
                [
                    'status' => 'success',
                    'invoice_id' => $invoice_id,
                ], 201);
        }

        return $this->_container->module->render(
            $response,
            'transactions/_payment.html',
            [
                'sub_total' => $this->getSubTotal()
            ]);
    }

    public function change_request($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $params = $request->getParams();
        if (strpos($params['amount_tendered'], ",") > 0) {
            $params['amount_tendered'] = $this->money_unformat($params['amount_tendered']);
        }
        $change = $params['amount_tendered'] - $this->getSubTotal();

        $_SESSION['items_payment'] = [
            'amount_tendered' => $params['amount_tendered'],
            'change' => $change,
        ];

        return $this->_container->module->render(
            $response,
            'transactions/_change.html',
            [
                'change' => $change
            ]);
    }

    public function set_customer($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $params = $request->getParams();
        if (!empty($params['customer_id'])) {
            $_SESSION['customer'] = $params['customer_id'];

            return $response->withJson(
                [
                    'status' => 'success'
                ], 201);
        }

        return $response->withJson(
            [
                'status' => 'failed'
            ], 201);
    }

    public function set_type($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $params = $request->getParams();
        if (!empty($params['type'])) {
            $_SESSION['transaction_type'] = $params['type'];
            $items_belanja = $_SESSION['items_belanja'];
            if (!empty($items_belanja)) {
                foreach ($items_belanja as $id => $item) {
                    $unit_price = $item['unit_price'];
                    if (($unit_price > 0) && ($params['type'] == \Model\InvoicesModel::STATUS_REFUND)) {
                        $unit_price = -1 * $item['unit_price'];
                    } elseif (($unit_price < 0) && ($params['type'] == \Model\InvoicesModel::STATUS_PAID)) {
                        $unit_price = -1 * $item['unit_price'];
                    }
                    $items_belanja[$id]['unit_price'] = $unit_price;
                }
                $_SESSION['items_belanja'] = $items_belanja;
            }

            return $response->withJson(
                [
                    'status' => 'success'
                ], 201);
        }

        return $response->withJson(
            [
                'status' => 'failed'
            ], 201);
    }

    public function set_warehouse($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $params = $request->getParams();
        if (!empty($params['warehouse_id'])) {
            $_SESSION['warehouse_id'] = $params['warehouse_id'];

            return $response->withJson(
                [
                    'status' => 'success'
                ], 201);
        }

        return $response->withJson(
            [
                'status' => 'failed'
            ], 201);
    }
}