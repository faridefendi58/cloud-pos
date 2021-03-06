<?php

namespace Components;

class BaseController
{
    protected $_container;
    protected $_settings;
    protected $_user;
    protected $_login_url = '/panel-admin/default/login';
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
        $path = $request->getUri()->getPath();
        $action = end(explode('/',$path));
        if (!empty($args) && in_array($action, $args)) {
            $sprites = explode('/',$path);
            $action = $sprites[count($sprites)-2];
        }

        $access_rules = $this->accessRules();
        $allows = [];
        if (is_array($access_rules)){
            foreach ($access_rules as $i => $rules) {
                if (is_array($rules['actions']) && in_array($action, $rules['actions']) && $rules[0] == 'allow'){
                    if (!empty($rules['users'][0])){
                        if ($rules['users'][0] == '@')
                            array_push($allows, !$this->_user->isGuest());
                    }
                    if (isset($rules['expression'])){
                        array_push($allows, $rules['expression']);
                    }
                }
                if ($rules[0] == 'deny'){
                    if (!empty($rules['users'][0])){
                        if ($rules['users'][0] == '*' && $this->_user->isGuest()) {
                            $login_url = $this->_login_url;
                            if (!empty($request->getUri()->getPath())) {
                                $login_url .= '?r='.$request->getUri()->getPath();
                            }

                            return $response->withRedirect( $login_url );
                        }
                    }
                }
            }
        }

        return !in_array(false, $allows);
    }

    public function notAllowedAction()
    {
        $this->_container['response']
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Anda tidak diperbolehkan mengakses halaman ini!');
    }

    protected function hasAccess($path)
    {
        $model = new \Model\AdminGroupModel();
        return $model->hasAccess($this->_user, $path);
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
            if (is_array($data['recipients'])) {
                $data['recipients'] = json_encode($data['recipients']);
            }
            $model->recipients = $data['recipients'];
            if (isset($data['rel_id']) && isset($data['rel_type'])) {
                $model->rel_id = $data['rel_id'];
                $model->rel_type = $data['rel_type'];
            }
            $model->created_at = date("Y-m-d H:i:s");
            $save = \Model\NotificationsModel::model()->save(@$model);
            if ($save) {
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