<?php
namespace Extensions;

class EmailService
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
        $sql = "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_email_template` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `action_code` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `category` varchar(30) CHARACTER SET utf8 DEFAULT NULL COMMENT 'general, website',
          `enabled` tinyint(1) DEFAULT '1',
          `subject` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
          `content` text CHARACTER SET utf8,
          `description` text CHARACTER SET utf8,
          `vars` text CHARACTER SET utf8,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `action_code` (`action_code`)
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
