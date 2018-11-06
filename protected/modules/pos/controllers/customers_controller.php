<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class CustomersController extends BaseController
{
    protected $_login_url = '/pos/default/login';

    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete'
                ],
                'users' => ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('pos/customers/read'),
            ],
            ['allow',
                'actions' => ['create'],
                'expression' => $this->hasAccess('pos/customers/create'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('pos/customers/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('pos/customers/delete'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $params = $request->getParams();
        if (isset($params['Customers'])) {
            $model = new \Model\CustomersModel();
            $model->name = $params['Customers']['name'];
            $model->email = $params['Customers']['email'];
            $model->address = $params['Customers']['address'];
            $model->telephone = $params['Customers']['phone'];
            $model->status = \Model\CustomersModel::STATUS_ACTIVE;
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\CustomersModel::model()->save(@$model);
            if ($save) {
                $_SESSION['customer'] = $model->id;

                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data Anda telah berhasil disimpan'
                    ], 201);
            } else {
                $error = \Model\CustomersModel::model()->getErrors();
                var_dump($error); exit;
            }
        }

        return $this->_container->module->render(
            $response,
            'customers/_form.html',
            [
                'model' => null
            ]);
    }
}