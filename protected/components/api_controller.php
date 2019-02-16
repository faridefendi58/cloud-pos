<?php

namespace Components;

class ApiBaseController
{
    protected $_container;
    protected $_settings;
    protected $_user;
    protected $_login_url = '/api/user/login';
    protected $_extensions;

    public function __construct($app, $user)
    {
        $container = $app->getContainer();
        $this->_container = $container;
        $this->_settings = $container->get('settings');
        $this->_user = $user;
        if (!empty($container->get('settings')['params']['extensions'])) {
            $this->_extensions = json_decode($container->get('settings')['params']['extensions'], true);
        }

        $this->register($app);
    }

    protected function isAllowed($request, $response, $args = null)
    {
        $params = $request->getParams();
        if (!isset($params['api-key'])) {
            return ['allow' => false, 'message' => 'Api key tidak ditemukan.'];
        }

        $hasAccess = $this->hasAccess($params['api-key']);
        if (!$hasAccess) {
            return ['allow' => false, 'message' => 'Api key tidak ditemukan atau tidak aktif.'];
        }

        return ['allow' => true];
    }

    public function notAllowedAction()
    {
        $this->_container['response']
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Anda tidak diperbolehkan mengakses halaman ini!');
    }

    protected function hasAccess($api_key)
    {
        $model = \Model\ApiModel::model()->findByAttributes(['api_key' => $api_key, 'status' => \Model\ApiModel::STATUS_ACTIVE]);
        if (!$model instanceof \RedBeanPHP\OODBBean)
            return false;

        return true;
    }

    public function getBaseUrl($request)
    {
        if (empty($this->_container->get('settings')['params']['site_url'])) {
            $uri = $request->getUri();
            $base_url = $uri->getScheme().'://'.$uri->getHost().$uri->getBasePath();
            if (!empty($uri->getPort()))
                $base_url .= ':'.$uri->getPort();

            return $base_url;
        }

        return $this->_container->get('settings')['params']['site_url'];
    }

    public function _sendNotification($data)
    {
        if (isset($data['message']) && isset($data['recipients'])) {
            $model = new \Model\NotificationsModel();
            $model->message = $data['message'];
            if (isset($data['rel_id']) && isset($data['rel_type'])) {
                $model->rel_id = $data['rel_id'];
                $model->rel_type = $data['rel_type'];
            }

            if (isset($data['issue_number']) && isset($data['rel_activity'])) {
                $model->issue_number = $data['issue_number'];
                $model->rel_activity = $data['rel_activity'];
            }

            $model->created_at = date("Y-m-d H:i:s");
            $save = \Model\NotificationsModel::model()->save(@$model);
            if ($save) {
                if (!is_array($data['recipients'])) {
                    $data['recipients'] = json_encode($data['recipients']);
                }
                foreach ($data['recipients'] as $i => $admin_id) {
                    $model2 = new \Model\NotificationRecipientsModel();
                    $model2->admin_id = $admin_id;
                    $model2->notification_id = $model->id;
                    $model2->created_at = date("Y-m-d H:i:s");
                    $save2 = \Model\NotificationRecipientsModel::model()->save($model2);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * unformat money format to base number
     */
    public function money_unformat($number, $thousand='.', $decimal=',')
    {
        if (strstr($number, $thousand))
            $number = str_replace($thousand, '', $number);
        if (strstr($number, $decimal))
            $number = str_replace($decimal, '.', $number);

        return $number;
    }
}