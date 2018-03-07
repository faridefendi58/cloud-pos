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
        $app->map(['POST'], '/scan', [$this, 'scan']);
        $app->map(['POST'], '/delete-item/[{id}]', [$this, 'delete_item']);
        $app->map(['GET'], '/cart', [$this, 'cart']);
        $app->map(['POST'], '/update-qty', [$this, 'update_qty']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete',
                    'scan', 'delete-item', 'cart', 'update-qty'
                ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('pos/transactions/read'),
            ],
            ['allow',
                'actions' => ['create'],
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
        
        $model = new \Model\OrdersModel();
        $orders = $model->getData();

        return $this->_container->module->render(
            $response, 
            'transactions/view.html',
            [
                'orders' => $orders
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

        return $this->_container->module->render(
            $response,
            'transactions/create.html',
            [
                'products' => $products,
                'items_belanja' => $items_belanja,
                'sub_total' => $this->getSubTotal()
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

        $model = \Model\ProductsModel::model()->findByPk($args['id']);
        $cmodel = new \Model\ProductCategoriesModel();
        $categories = $cmodel->getData();

        if (isset($_POST['Products'])){
            $model->title = $_POST['Products']['title'];
            $model->product_category_id = $_POST['Products']['product_category_id'];
            $model->description = $_POST['Products']['description'];
            $model->active = $_POST['Products']['active'];
            $model->updated_at = date("Y-m-d H:i:s");
            $update = \Model\ProductsModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\ProductsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'products/update.html', [
            'model' => $model,
            'categories' => $categories
        ]);
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
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function scan($request, $response, $args)
    {
        if (isset($_POST['item'])) {
            $model = \Model\ProductsModel::model()->findByPk($_POST['item']);
            if (!$model instanceof \RedBeanPHP\OODBBean)
                $success = false;
            $stmodel = new \Model\ProductStocksModel();
            $stock = $stmodel->getTotalStock($model->id);
            if ($stock > 0) {
                $prmodel = new \Model\ProductPricesModel();
                $unit_price = $prmodel->getPrice($model->id, 1);
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
                        'message' => 'Data berhasil disimpan.',
                        'sub_total' => $this->getSubTotal()
                    ], 201);
            } else {
                $success = false;
                $message = 'Stok habis.';
            }
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
                'message' => 'Data berhasil dihapus.',
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
        $items_belanja = $_SESSION['items_belanja'];
        $id = $_POST['id'];
        $cart_discount = $items_belanja[$id]['discount'] / $items_belanja[$id]['qty'];
        $items_belanja[$id]['qty'] = (int)$_POST['qty'];

        $model = \Model\ProductsModel::model()->findByPk($items_belanja[$id]['id']);
        $stmodel = new \Model\ProductStocksModel();
        $stock = $stmodel->getTotalStock($model->id);

        if((int)$_POST['qty'] <= (int)$stock){ //jika kurang dari atau sm dengan persediaan
            $price = 0;
            $prmodel = new \Model\ProductPricesModel();

            $discounts = $prmodel->getDiscontedItems($model->id);
            if(is_array($discounts) && count($discounts) > 1){
                foreach ($discounts as $index => $data) {
                    if ($data['quantity'] <=0)
                        $data['quantity'] = 1;
                    $bagi = $items_belanja[$id]['qty'] / $data['quantity'];
                    $mod = $items_belanja[$id]['qty'] % $data['quantity'];
                    /*if (((int)$bagi > 0) & ($bagi <= $data['quantity'])) {
                        $price = (int)$bagi * $data['price'];
                        if ($mod > 0) {
                            $price = $price + $items_belanja[$id]['unit_price'] * $mod;
                        }
                        $items_belanja[$id]['discount'] = ($items_belanja[$id]['unit_price'] * $items_belanja[$id]['qty']) - $price;
                    }*/
                    /*if ($mod > 0) {
                        var_dump($mod); exit;
                    }*/
                }
            }else{
                $unit_price = $prmodel->getPrice($model->id, 1);
                $price = $unit_price * $_POST['qty'];
                //if(Yii::app()->user->hasState('promocode'))
                    //$items_belanja[$id]['discount']=Promo::getDiscountValue(Yii::app()->user->getState('promocode'),$price);
            }
            var_dump($items_belanja); exit;

            $_SESSION['items_belanja'] = $items_belanja;

            if ($price > 0)
                $total = $price;
            else
                $total = $items_belanja[$id]['unit_price'] * $items_belanja[$id]['qty'];

            $discount = $items_belanja[$id]['discount'];

            return $response->withJson(
                [
                    'status' => 'success',
                    'div' => (int)$_POST['qty'],
                    'total' => number_format($total - $discount,0,',','.'),
                    'subtotal' => number_format($this->getSubTotal(),0,',','.'),
                    'discount' => number_format($discount,0,',','.'),
                ], 201);
        }else{
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message'=>$_POST['qty'].' is not allowed, max '.$stock.' ready stock.',
                ], 201);
        }
    }

    private function getSubTotal()
    {
        $items_belanja = $_SESSION['items_belanja'];
        $tot_price = 0;
        foreach ($items_belanja as $i => $item) {
            $tot_price = $tot_price + ($item['qty']*$item['unit_price']);
        }

        return $tot_price;
    }
}