<?php
namespace Extensions;

class OrderService
{
    protected $basePath;
    protected $themeName;
    protected $adminPath;
    protected $tablePrefix;
    protected $_settings;

    public function __construct($settings = null)
    {
        $this->basePath = (is_object($settings))? $settings['basePath'] : $settings['settings']['basePath'];
        $this->themeName = (is_object($settings))? $settings['theme']['name'] : $settings['settings']['theme']['name'];
        $this->adminPath = (is_object($settings))? $settings['admin']['path'] : $settings['settings']['admin']['path'];
        $this->tablePrefix = (is_object($settings))? $settings['db']['tablePrefix'] : $settings['settings']['db']['tablePrefix'];
        $this->_settings = $settings;
    }
    
    public function install()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_client_order` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `client_id` int(11) DEFAULT NULL,
          `product_id` int(11) DEFAULT NULL,
          `promo_id` int(11) DEFAULT NULL,
          `group_id` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `group_master` tinyint(1) DEFAULT '0',
          `invoice_option` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `title` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `currency` varchar(20) CHARACTER SET utf8 DEFAULT NULL,
          `unpaid_invoice_id` int(11) DEFAULT NULL,
          `service_id` int(11) DEFAULT NULL,
          `service_type` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `period` varchar(20) CHARACTER SET utf8 DEFAULT NULL,
          `quantity` tinyint(2) DEFAULT '1',
          `unit` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `price` double(18,2) DEFAULT NULL,
          `discount` double(18,2) DEFAULT NULL COMMENT 'first invoice discount',
          `status` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
          `reason` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT 'suspend/cancel reason',
          `notes` text CHARACTER SET utf8,
          `config` text CHARACTER SET utf8,
          `expires_at` datetime DEFAULT NULL,
          `activated_at` datetime DEFAULT NULL,
          `suspended_at` datetime DEFAULT NULL,
          `unsuspended_at` datetime DEFAULT NULL,
          `canceled_at` datetime DEFAULT NULL,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `client_id_idx` (`client_id`),
          KEY `product_id_idx` (`product_id`),
          KEY `promo_id_idx` (`promo_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_service_website` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `client_id` int(11) NOT NULL,
          `domain` varchar(128) NOT NULL,
          `created_at` datetime NOT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

        $sql = str_replace(['{tablePrefix}'], [$this->tablePrefix], $sql);

        $model = new \Model\OptionsModel();
        $install = $model->installExt($sql);

        return $install;
    }

    public function uninstall()
    {
        return true;
    }

    /**
     * Order extension available menu
     * @return array
     */
    public function getMenu()
    {
        return [
            [ 'label' => 'Daftar Order', 'url' => 'order/admin/view', 'icon' => 'fa fa-search' ],
        ];
    }

    public function activate($model)
    {
        if (!$model instanceof \RedBeanPHP\OODBBean)
            return false;

        // build a subdomain name
        $configs = json_decode($model->config, true);
        if (!empty($configs['s'])) {
            $configs['s'] = trim(strtolower($configs['s']));
            if (preg_match('/\s/',$configs['s']))
                $configs['s'] = preg_replace( '/\s+/', '', $configs['s'] );
            $prefiks = substr($configs['s'], 0, 4);
            $ext_order = json_decode($this->_settings['params']['ext_order'], true);
            if (!is_array($ext_order) || empty($ext_order['server_domain_name'])) {
                throw new \Exception( 'Server domain name tidak ditemukan.' );
            }

            $configs['domain_name'] = $this->_generateUsername($prefiks, 4).'.'.$ext_order['server_domain_name'];
            $model->config = json_encode( $configs );
        }

        $model->updated_at = date("Y-m-d H:i:s");
        $update = \ExtensionsModel\ClientOrderModel::model()->update(@$model);

        if ($update) {
            $plugin = $this->get_plugin($model);
            $create = $plugin->create();
            if ($create) {
                $model2 = new \ExtensionsModel\ServiceWebsiteModel();
                $model2->client_id = $model->client_id;
                $model2->domain = $configs['domain_name'];
                $model2->created_at = date("Y-m-d H:i:s");
                $model2->updated_at = date("Y-m-d H:i:s");
                $save = \ExtensionsModel\ServiceWebsiteModel::model()->save( @$model2 );
                if ($save) {
                    $model->service_id = $model2->id;
                    $model->status = \ExtensionsModel\ClientOrderModel::STATUS_ACTIVE;
                    $model->expires_at = $this->set_expiration_date($model->period, $model->expires_at);
                    $model->activated_at = date("Y-m-d H:i:s");
                    $update = \ExtensionsModel\ClientOrderModel::model()->update(@$model);
                }
            }
            
            return true;
        }

        return false;
    }

    private function get_plugin($model)
    {
        switch ($model->service_type) {
            case 'website':
                $plugin = new \Extensions\Components\Website($model, $this->_settings);
                break;
        }

        return $plugin;
    }

    public function suspend($model)
    {
        if (!$model instanceof \RedBeanPHP\OODBBean)
            return false;

        $model->status = \ExtensionsModel\ClientOrderModel::STATUS_SUSPENDED;
        $model->suspended_at = date("Y-m-d H:i:s");
        $model->updated_at = date("Y-m-d H:i:s");
        $update = \ExtensionsModel\ClientOrderModel::model()->update($model);

        if ($update) {
            return true;
        }

        return false;
    }

    public function unsuspend($model)
    {
        if (!$model instanceof \RedBeanPHP\OODBBean)
            return false;

        $model->status = \ExtensionsModel\ClientOrderModel::STATUS_ACTIVE;
        $model->suspended_at = null;
        $model->unsuspended_at = date("Y-m-d H:i:s");
        $model->updated_at = date("Y-m-d H:i:s");
        $update = \ExtensionsModel\ClientOrderModel::model()->update($model);

        if ($update) {
            return true;
        }

        return false;
    }

    public function cancel($model)
    {
        if (!$model instanceof \RedBeanPHP\OODBBean)
            return false;

        $model->status = \ExtensionsModel\ClientOrderModel::STATUS_CANCELED;
        $model->canceled_at = date("Y-m-d H:i:s");
        $model->updated_at = date("Y-m-d H:i:s");
        $update = \ExtensionsModel\ClientOrderModel::model()->update($model);

        if ($update) {
            return true;
        }

        return false;
    }

    private function set_expiration_date($period = '1Y', $date_start = null)
    {
        if (empty($date_start))
            $date_start = date("Y-m-d H:i:s");

        switch ($period) {
            case '1W':
                $date_end = date("Y-m-d H:i:s", strtotime("+1 week", strtotime($date_start)));
                break;
            case '2W':
                $date_end = date("Y-m-d H:i:s", strtotime("+2 week", strtotime($date_start)));
                break;
            case '1M':
                    $date_end = date("Y-m-d H:i:s", strtotime("+1 month", strtotime($date_start)));
                break;
            case '3M':
                $date_end = date("Y-m-d H:i:s", strtotime("+3 month", strtotime($date_start)));
                break;
            case '6M':
                $date_end = date("Y-m-d H:i:s", strtotime("+6 month", strtotime($date_start)));
                break;
            case '1Y':
                $date_end = date("Y-m-d H:i:s", strtotime("+1 year", strtotime($date_start)));
                break;
            case '2Y':
                $date_end = date("Y-m-d H:i:s", strtotime("+2 year", strtotime($date_start)));
                break;
            case '3Y':
                $date_end = date("Y-m-d H:i:s", strtotime("+3 year", strtotime($date_start)));
                break;
        }

        return $date_end;
    }

    private function _generateUsername( $prefix = null, $length = 8 )
    {
        $num1 = rand(10000, 99999);
        $num2 = rand(10000, 99999);
        $username = $num1 . $num2;
        $username = substr($username, 0, $length);

        return $prefix.$username;
    }
}
