<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class NotificationsModel extends \Model\BaseModel
{
    const TYPE_PURCHASE_ORDER = 'purchase_order';
    const TYPE_TRANSFER_ISSUE = 'transfer_issue';
    const TYPE_INVENTORY_ISSUE = 'inventory_issue';

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_notification';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['message', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*    
            FROM {tablePrefix}ext_notification t';

        if (isset($data['admin_id'])) {
            $sql .= ' LEFT JOIN {tablePrefix}ext_notification_recipient r ON r.notification_id = t.id';
        }

        $sql .= ' WHERE 1';

        $params = [];
        if (isset($data['admin_id'])) {
            $sql .= ' AND r.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];

            if (isset($data['status'])) {
                $sql .= ' AND r.status =:status';
                $params['status'] = $data['status'];
            }
        }

        $sql .= ' GROUP BY t.id ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*   
            FROM {tablePrefix}ext_notification t 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
