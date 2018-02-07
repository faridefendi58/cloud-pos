<?php
namespace ExtensionsModel;

use Model\R;

require_once __DIR__ . '/../../../models/base.php';

class PostModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_post';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['status, post_type, author_id', 'required'],
            ['created_at', 'required', 'on'=>'create'],
            ['author_id', 'numerical', 'integerOnly' => true],
        ];
    }

    public function getListStatus()
    {
        return [ 'draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived' ];
    }

    public static function string2array($tags)
    {
        return preg_split('/\s*,\s*/',trim($tags),-1,PREG_SPLIT_NO_EMPTY);
    }

    public static function array2string($tags)
    {
        return implode(', ',$tags);
    }

    public static function createSlug($str)
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9-]/', '-', $str);
        $str = preg_replace('/-+/', "-", $str);
        $str = trim($str, '-');
        return $str;
    }
    
    public function getPosts($data)
    {
        $sql = "SELECT t.status, c.post_id, c.title, c.meta_description, c.content, c.slug, l.id, l.language_name, t.created_at     
        FROM {tablePrefix}ext_post t 
        LEFT JOIN {tablePrefix}ext_post_content c ON c.post_id = t.id 
        LEFT JOIN {tablePrefix}ext_post_language l ON l.id = c.language";

        if (isset($data['category_id'])) {
            $sql .= " LEFT JOIN {tablePrefix}ext_post_in_category ct ON ct.post_id = t.id";
        }

        $sql .= " WHERE 1";

        $params = array();
        if (isset($data['just_default'])) {
            $sql .= ' AND l.is_default = 1';
        }

        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['category_id'])) {
            $sql .= " AND ct.category_id = :category_id";
            $params['category_id'] = $data['category_id'];
        }

        if (isset($data['order'])) {
            if ($data['order'] == 'populer')
                $sql .= ' ORDER BY c.viewed DESC';
        } else
            $sql .= ' ORDER BY t.id DESC';

        if (isset($data['limit']))
            $sql .= ' LIMIT '.$data['limit'];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = \Model\R::getAll( $sql, $params );

        return $rows;
    }
    
    public function getPostDetail($id)
    {
        $sql = "SELECT c.post_id, t.status, t.allow_comment, t.tags, t.created_at, t.updated_at, 
          c.title, c.content, c.slug, l.id AS language_id, 
          c.meta_keywords, c.meta_description, l.language_name, ad.username AS author_name 
        FROM {tablePrefix}ext_post t 
        LEFT JOIN {tablePrefix}ext_post_content c ON c.post_id = t.id 
        LEFT JOIN {tablePrefix}ext_post_language l ON l.id = c.language  
        LEFT JOIN {tablePrefix}admin ad ON ad.id = t.author_id  
        WHERE t.id =:id";

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = \Model\R::getAll( $sql, ['id'=>$id] );

        $items = [
            'id' => $rows[0]['post_id'],
            'status' => $rows[0]['status'],
            'allow_comment' => $rows[0]['allow_comment'],
            'tags' => (!empty($rows[0]['tags']))? self::string2array($rows[0]['tags']) : array(),
            'tags_string' => $rows[0]['tags'],
            'author' => $rows[0]['author_name'],
            'created_at' => $rows[0]['created_at'],
            'updated_at' => $rows[0]['updated_at'],
        ];
        foreach ($rows as $i => $row) {
            $items['content'][$row['language_id']] = [
                'title' => $row['title'],
                'slug' => $row['slug'],
                'content' => $row['content'],
                'meta_keywords' => $row['meta_keywords'],
                'meta_description' => $row['meta_description']
            ];
        }

        $sql2 = "SELECT t.category_id    
        FROM {tablePrefix}ext_post_in_category t 
        WHERE t.post_id =:post_id";

        $sql2 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql2);

        $rows2 = \Model\R::getAll( $sql2, ['post_id'=>$id] );
        $category = [];
        foreach ($rows2 as $j => $row2) {
            array_push($category, $row2['category_id']);
        }
        $items['category'] = $category;

        return $items;
    }

    public function getPost($slug)
    {
        $sql = "SELECT c.post_id, t.status, t.allow_comment, t.tags, t.created_at, t.updated_at, 
          c.title, c.content, c.slug, l.id AS language_id, 
          c.meta_keywords, c.meta_description, l.language_name, ad.username AS author_name 
        FROM {tablePrefix}ext_post t 
        LEFT JOIN {tablePrefix}ext_post_content c ON c.post_id = t.id 
        LEFT JOIN {tablePrefix}ext_post_language l ON l.id = c.language  
        LEFT JOIN {tablePrefix}admin ad ON ad.id = t.author_id  
        WHERE c.slug =:slug";

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = \Model\R::getRow( $sql, ['slug'=>$slug] );

        $items = [
            'id' => $row['post_id'],
            'status' => $row['status'],
            'allow_comment' => $row['allow_comment'],
            'tags' => (!empty($row['tags']))? self::string2array($row['tags']) : array(),
            'tags_string' => $row['tags'],
            'author' => $row['author_name'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'content' => $row['content'],
            'meta_keywords' => $row['meta_keywords'],
            'meta_description' => $row['meta_description']
        ];

        $sql2 = "SELECT t.category_id    
        FROM {tablePrefix}ext_post_in_category t 
        WHERE t.post_id =:post_id";

        $sql2 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql2);

        $rows2 = \Model\R::getAll( $sql2, ['post_id'=>$row['post_id']] );

        $category = [];
        foreach ($rows2 as $j => $row2) {
            array_push($category, $row2['category_id']);
        }
        $items['category'] = $category;

        $sql3 = "SELECT c.*     
        FROM {tablePrefix}ext_post_in_category t 
        LEFT JOIN {tablePrefix}ext_post_category c ON c.id = t.category_id 
        WHERE t.post_id =:post_id";

        $sql3 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql3);

        $items['main_category'] = \Model\R::getRow( $sql3, ['post_id'=>$row['post_id']] );

        return $items;
    }

    public function getCategories($data = null)
    {
        $sql = "SELECT t.id, t.category_name AS title, t.slug, t.description     
        FROM {tablePrefix}ext_post_category t";

        if (is_array($data) && isset($data['post_id'])) {
            $sql .= " LEFT JOIN {tablePrefix}ext_post_in_category pc ON pc.category_id = t.id";
        }

        $sql .= " WHERE 1";

        $params = [];
        if (is_array($data) && isset($data['post_id'])) {
            $sql .= " AND pc.post_id =:post_id";
            $params['post_id'] = $data['post_id'];
        }

        $sql .= " ORDER BY t.id ASC";

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = \Model\R::getAll( $sql, $params );
        return $rows;
    }

    /**
     * @param $data: id (post id), type
     * @return array
     */
    public function getImages($data)
    {
        $sql = "SELECT i.*  
        FROM {tablePrefix}ext_post_images i 
        WHERE i.post_id =:post_id";

        $params = [ 'post_id'=>$data['id'] ];

        if (isset($data['type'])) {
            $sql .= " AND i.type =:type";
            $params['type'] = $data['type'];
        }

        $sql .= " ORDER BY i.id ASC";

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = \Model\R::getAll( $sql, $params );
        return $rows;
    }

    /**
     * @param $data: slug, or id
     * @return array
     */
    public function getCategory($data)
    {
        $sql = "SELECT t.id, t.category_name AS title, t.slug, t.description     
        FROM {tablePrefix}ext_post_category t 
        WHERE 1";

        $params = [];
        if (isset($data['slug'])) {
            $sql .= " AND t.slug =:slug";
            $params['slug'] = $data['slug'];
        }

        if (isset($data['id'])) {
            $sql .= " AND t.id =:id";
            $params['id'] = $data['id'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = \Model\R::getRow( $sql, $params );
        return $row;
    }
}
