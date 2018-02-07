<?php
namespace Extensions;

class BlogService
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
        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_post` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `tags` text COLLATE utf8_unicode_ci,
          `status` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'draft, published, archived',
          `allow_comment` tinyint(1) DEFAULT '0',
          `post_type` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'post' COMMENT 'post, page',
          `author_id` int(11) DEFAULT '0',
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `FK_post_author` (`author_id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_post_category` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `category_name` varchar(128) NOT NULL,
          `slug` varchar(128) NOT NULL,
          `parent_id` int(11) DEFAULT '0',
          `description` text,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_post_content` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `post_id` int(11) NOT NULL,
          `title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
          `content` text COLLATE utf8_unicode_ci NOT NULL,
          `language` int(11) DEFAULT '1',
          `viewed` int(11) DEFAULT '0',
          `slug` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
          `meta_keywords` text COLLATE utf8_unicode_ci,
          `meta_description` text COLLATE utf8_unicode_ci,
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_post_images` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `post_id` int(11) NOT NULL,
          `type` varchar(32) NOT NULL DEFAULT 'open_graft' COMMENT 'open_graft, ilustration',
          `upload_folder` varchar(256) DEFAULT NULL,
          `file_name` varchar(128) DEFAULT NULL,
          `alt` varchar(128) DEFAULT NULL,
          `description` text,
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_post_in_category` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `post_id` int(11) NOT NULL,
          `category_id` int(11) NOT NULL,
          `created_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

        $sql .= "CREATE TABLE IF NOT EXISTS `{tablePrefix}ext_post_language` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `language_name` varchar(32) NOT NULL,
          `code` varchar(3) NOT NULL,
          `is_default` tinyint(1) DEFAULT '0',
          `created_at` datetime DEFAULT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
        INSERT INTO `{tablePrefix}ext_post_language` (`id`, `language_name`, `code`, `is_default`, `created_at`, `updated_at`) VALUES
        (1, 'Bahasa Indonesia', 'id', 1, '{created_at}', '{updated_at}'),
        (2, 'English', 'en', 0, '{created_at}', '{updated_at}');";

        $sql = str_replace(['{tablePrefix}', '{created_at}', '{updated_at}'], [$this->tablePrefix, date("Y-m-d H:i:s"), date("Y-m-d H:i:s")], $sql);
        
        $model = new \Model\OptionsModel();
        $install = $model->installExt($sql);

        return $install;
    }

    public function uninstall()
    {
        return true;
    }

    /**
     * Blog extension available menu
     * @return array
     */
    public function getMenu()
    {
        return [
            [ 'label' => 'Daftar Postingan', 'url' => 'blog/posts/view', 'icon' => 'fa fa-search' ],
            [ 'label' => 'Tambah Postingan', 'url' => 'blog/posts/create', 'icon' => 'fa fa-plus' ],
        ];
    }
}
