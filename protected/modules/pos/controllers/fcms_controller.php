<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class FcmsController extends BaseController
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
                'expression' => $this->hasAccess('pos/fcms/read'),
            ],
            ['allow',
                'actions' => ['create'],
                'expression' => $this->hasAccess('pos/fcms/create'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('pos/fcms/update'),
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
        $model = new \Model\FcmsModel();
        if (isset($params['Fcms'])) {
            $model->title = $params['Fcms']['title'];
            $model->message = $params['Fcms']['message'];
            $model->topic = $params['Fcms']['topic'];
            $model->status = \Model\FcmsModel::STATUS_UNSENT;
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\FcmsModel::model()->save(@$model);
            if ($save) {
                $message = 'Data Anda telah berhasil disimpan.';
                $success = true;

                $p_model = new \Model\OptionsModel();
                $serverKey = $p_model->getOption('fcm_server_key');
                $result = $this->sendNotification($model->title, $model->message, ["new_post_id" => "605"], $model->topic, $serverKey);
                if (is_object($result) && !empty($result->message_id)) {
                    $model2 = \Model\FcmsModel::model()->findByPk($model->id);
                    $model2->result_message_id = $result->message_id;
                    $model2->status = \Model\FcmsModel::STATUS_SENT;
                    $update = \Model\FcmsModel::model()->update($model2);
                }
            } else {
                $message = \Model\FcmsModel::model()->getErrors(false);
                $errors = \Model\FcmsModel::model()->getErrors(true, true);
                $success = false;
            }
        }

        return $this->_container->module->render(
            $response,
            'fcms/create.html',
            [
                'model' => $model,
                'message' => ($message) ? $message : null,
                'success' => $success,
                'errors' => $errors
            ]);
    }

    private function sendNotification($title = "", $body = "", $customData = [], $topic = "", $serverKey = ""){
        if($serverKey != ""){
            ini_set("allow_url_fopen", "On");
            $data =
                [
                    "to" => '/topics/'.$topic,
                    "notification" => [
                        "body" => $body,
                        "title" => $title,
                    ],
                    "data" => $customData
                ];

            $options = array(
                'http' => array(
                    'method'  => 'POST',
                    'content' => json_encode( $data ),
                    'header'=>  "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n" .
                        "Authorization:key=".$serverKey
                )
            );

            $context  = stream_context_create( $options );
            $result = file_get_contents( "https://fcm.googleapis.com/fcm/send", false, $context );
            return json_decode( $result );
        }
        return false;
    }
}