<?php
namespace Extensions;

class ProductService
{
    protected $basePath;
    protected $themeName;
    protected $adminPath;
    protected $tablePrefix;

    public function __construct($settings = null)
    {
        $this->basePath = (is_object($settings))? $settings['basePath'] : $settings['settings']['basePath'];
        $this->themeName = (is_object($settings))? $settings['theme']['name'] : $settings['settings']['theme']['name'];
        $this->adminPath = (is_object($settings))? $settings['admin']['path'] : $settings['settings']['admin']['path'];
        $this->tablePrefix = (is_object($settings))? $settings['db']['tablePrefix'] : $settings['settings']['db']['tablePrefix'];
    }
    
    public function install()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_product` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_category_id` int(11) DEFAULT NULL,
          `type` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `title` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `slug` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `description` text CHARACTER SET utf8,
          `unit` varchar(50) CHARACTER SET utf8 DEFAULT 'product',
          `active` tinyint(1) DEFAULT '1',
          `status` varchar(50) CHARACTER SET utf8 DEFAULT 'enabled' COMMENT 'enabled, disabled',
          `hidden` tinyint(1) DEFAULT '0',
          `is_addon` tinyint(1) DEFAULT '0',
          `setup` varchar(50) CHARACTER SET utf8 DEFAULT 'after_payment',
          `addons` text CHARACTER SET utf8,
          `upgrades` text CHARACTER SET utf8,
          `priority` bigint(20) DEFAULT NULL,
          `config` text CHARACTER SET utf8,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `slug` (`slug`),
          KEY `product_type_idx` (`type`),
          KEY `product_category_id_idx` (`product_category_id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

        // product category
        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_product_category` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `title` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `description` text CHARACTER SET utf8,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        COMMIT;";

        // tabel product payment
        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_product_payment` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_id` int(11) NOT NULL,
          `type` varchar(30) CHARACTER SET utf8 DEFAULT NULL COMMENT 'free, once, recurrent',
          `w_price` decimal(18,2) DEFAULT '0.00',
          `m_price` decimal(18,2) DEFAULT '0.00',
          `q_price` decimal(18,2) DEFAULT '0.00',
          `b_price` decimal(18,2) DEFAULT '0.00',
          `a_price` decimal(18,2) DEFAULT '0.00',
          `bia_price` decimal(18,2) DEFAULT '0.00',
          `tria_price` decimal(18,2) DEFAULT '0.00',
          `w_enabled` tinyint(1) DEFAULT '1',
          `m_enabled` tinyint(1) DEFAULT '1',
          `q_enabled` tinyint(1) DEFAULT '1',
          `b_enabled` tinyint(1) DEFAULT '1',
          `a_enabled` tinyint(1) DEFAULT '1',
          `bia_enabled` tinyint(1) DEFAULT '1',
          `tria_enabled` tinyint(1) DEFAULT '1',
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        COMMIT;";

        $sql = str_replace(['{tablePrefix}'], [$this->tablePrefix], $sql);
        
        $model = new \Model\OptionsModel();
        $install = $model->installExt($sql);

        return $install;
    }

    public function uninstall()
    {
        return true;
    }
}
