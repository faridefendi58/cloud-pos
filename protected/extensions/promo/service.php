<?php
namespace Extensions;

class PromoService
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
        $sql = "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_promo` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `code` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
          `description` text CHARACTER SET utf8,
          `type` varchar(30) CHARACTER SET utf8 NOT NULL DEFAULT 'percentage' COMMENT 'absolute, percentage, trial',
          `value` decimal(18,2) DEFAULT NULL,
          `maxuses` int(11) DEFAULT '0',
          `used` int(11) DEFAULT '0',
          `config` text CHARACTER SET utf8 NOT NULL,
          `once_per_client` tinyint(1) DEFAULT '0',
          `recurring` tinyint(1) DEFAULT '0',
          `active` tinyint(1) DEFAULT '0',
          `products` text CHARACTER SET utf8,
          `periods` text CHARACTER SET utf8,
          `start_at` datetime DEFAULT NULL,
          `end_at` datetime DEFAULT NULL,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `start_index_idx` (`start_at`),
          KEY `end_index_idx` (`end_at`),
          KEY `active_index_idx` (`active`),
          KEY `code_index_idx` (`code`)
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
