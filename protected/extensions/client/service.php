<?php
namespace Extensions;

class ClientService
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
        $sql = "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_client` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT 'Alternative id for foreign systems',
          `client_group_id` int(11) DEFAULT NULL,
          `email` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `password` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `salt` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `status` varchar(30) CHARACTER SET utf8 DEFAULT 'active' COMMENT 'active, suspended, canceled',
          `email_approved` tinyint(1) DEFAULT NULL,
          `type` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `name` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `gender` varchar(20) CHARACTER SET utf8 DEFAULT NULL,
          `birthday` datetime DEFAULT NULL,
          `phone` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `company` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `company_number` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `address_1` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `address_2` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `city` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `state` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `postcode` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `country` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `notes` text CHARACTER SET utf8,
          `currency` varchar(10) CHARACTER SET utf8 DEFAULT 'IDR',
          `lang` varchar(10) CHARACTER SET utf8 DEFAULT 'ID',
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`),
          KEY `alternative_id_idx` (`aid`),
          KEY `client_group_id_idx` (`client_group_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_client_group` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `title` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `created_at` datetime DEFAULT NULL,
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
}
