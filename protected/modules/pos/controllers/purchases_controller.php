<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class PurchasesController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'create', 'update', 'delete'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('pos/purchases/read'),
            ],
            ['allow',
                'actions' => ['create'],
                'expression' => $this->hasAccess('pos/purchases/create'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('pos/purchases/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('pos/purchases/delete'),
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
        
        $model = new \Model\PurchaseOrdersModel();
        $purchases = $model->getData();
        $smodel = new \Model\SuppliersModel();
        $suppliers = $smodel->getData();
        $spmodel = new \Model\ShipmentsModel();
        $shipments = $spmodel->getData();

        return $this->_container->module->render(
            $response, 
            'purchases/view.html',
            [
                'purchases' => $purchases,
                'suppliers' => $suppliers,
                'shipments' => $shipments
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

        $model = new \Model\PurchaseOrdersModel();
        if (isset($_POST['PurchaseOrders'])) {
            $po_number = $this->get_po_number();
            if (!empty($_POST['PurchaseOrders']['po_number']))
                $model->po_number = $_POST['PurchaseOrders']['po_number'];
            else {
                $model->po_number = $po_number['serie_nr'];
            }
            $model->po_serie = $po_number['serie'];
            $model->po_nr = $po_number['nr'];
            $model->quantity = $_POST['PurchaseOrders']['quantity'];
            $model->unit = $_POST['PurchaseOrders']['unit'];
            $model->price_netto = $_POST['PurchaseOrders']['price_netto'];
            $model->supplier_id = $_POST['PurchaseOrders']['supplier_id'];
            $model->date_order = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['date_order']));
            $model->due_date = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['due_date']));
            $model->status = $_POST['PurchaseOrders']['status'];
            $model->notes = $_POST['PurchaseOrders']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\PurchaseOrdersModel::model()->save($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\PurchaseOrdersModel::model()->findByPk($args['id']);
        $wmodel = new \Model\PurchaseOrdersModel();
        $detail = $wmodel->getDetail($args['id']);
        $smodel = new \Model\SuppliersModel();
        $suppliers = $smodel->getData();
        $spmodel = new \Model\ShipmentsModel();
        $shipments = $spmodel->getData();

        if (isset($_POST['PurchaseOrders'])){
            $model->po_number = $_POST['PurchaseOrders']['po_number'];
            $model->quantity = $_POST['PurchaseOrders']['quantity'];
            $model->unit = $_POST['PurchaseOrders']['unit'];
            $model->price_netto = $_POST['PurchaseOrders']['price_netto'];
            $model->supplier_id = $_POST['PurchaseOrders']['supplier_id'];
            $model->date_order = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['date_order']));
            $model->due_date = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['due_date']));
            $model->status = $_POST['PurchaseOrders']['status'];
            $model->notes = $_POST['PurchaseOrders']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\PurchaseOrdersModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\PurchaseOrdersModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'purchases/update.html', [
            'model' => $model,
            'detail' => $detail,
            'suppliers' => $suppliers,
            'shipments' => $shipments
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

        $model = \Model\PurchaseOrdersModel::model()->findByPk($args['id']);
        $delete = \Model\PurchaseOrdersModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    private function get_po_number()
    {
        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        $prefiks = $ext_pos['po_prefiks'];
        if (empty($prefiks)) {
            $prefiks = 'PO-';
        }

        $wmodel = new \Model\PurchaseOrdersModel();
        $max_nr = $wmodel->getLastPoNumber($prefiks);
        
        if (empty($max_nr['max_nr'])) {
            $next_nr = 1;
        } else {
            $next_nr = $max_nr['max_nr'] + 1;
        }

        $po_number = str_repeat("0", 5 - strlen($max_nr)).$next_nr;

        return [
                'serie' => $prefiks,
                'nr' => $next_nr,
                'serie_nr' => $prefiks.$po_number
            ];
    }
}