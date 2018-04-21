<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class NotificationRecipientsModel extends \Model\BaseModel
{
    const STATUS_UNREAD = 'unread';
    const STATUS_READ = 'read';

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_notification_recipient';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['notification_id, admin_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData()
    {
        $sql = 'SELECT t.*, n.message AS message, a.name AS admin_name     
            FROM {tablePrefix}ext_notification_recipient t 
            LEFT JOIN {tablePrefix}ext_notification n ON n.id = t.notification_id
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id
            WHERE 1';

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, n.message AS message, a.name AS admin_name    
            FROM {tablePrefix}ext_notification_recipient t 
            LEFT JOIN {tablePrefix}ext_notification n ON n.id = t.notification_id
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
