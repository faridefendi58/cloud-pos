<?php
namespace Extensions;

class InvoiceService
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
        $sql = "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_invoice` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `client_id` int(11) DEFAULT NULL,
          `serie` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
          `nr` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `hash` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT 'To access via public link',
          `currency` varchar(25) CHARACTER SET utf8 DEFAULT NULL,
          `currency_rate` decimal(13,6) DEFAULT NULL,
          `credit` double(18,2) DEFAULT NULL,
          `base_income` double(18,2) DEFAULT NULL COMMENT 'Income in default currency',
          `base_refund` double(18,2) DEFAULT NULL COMMENT 'Refund in default currency',
          `refund` double(18,2) DEFAULT NULL,
          `notes` text CHARACTER SET utf8,
          `text_1` text CHARACTER SET utf8,
          `status` varchar(50) CHARACTER SET utf8 DEFAULT 'unpaid' COMMENT 'paid, unpaid',
          `seller_company` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `seller_company_vat` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `seller_company_number` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `seller_address` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `seller_phone` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `seller_email` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_company` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_company_vat` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_company_number` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_address` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_city` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_state` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_country` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_zip` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_phone` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `buyer_email` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `gateway_id` int(11) DEFAULT NULL,
          `approved` tinyint(1) DEFAULT '0',
          `taxname` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `taxrate` varchar(35) CHARACTER SET utf8 DEFAULT NULL,
          `due_at` datetime DEFAULT NULL,
          `reminded_at` datetime DEFAULT NULL,
          `paid_at` datetime DEFAULT NULL,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `hash` (`hash`),
          KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        COMMIT;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_invoice_item` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `invoice_id` bigint(20) DEFAULT NULL,
          `type` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `rel_id` text CHARACTER SET utf8,
          `task` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `status` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `title` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `period` varchar(10) CHARACTER SET utf8 DEFAULT NULL,
          `quantity` bigint(20) DEFAULT NULL,
          `unit` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `price` double(18,2) DEFAULT NULL,
          `taxed` tinyint(1) DEFAULT '0',
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `invoice_id_idx` (`invoice_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        COMMIT;
        ";

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
