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

	public function countEachWH($datas = [])
    {
        $sql = 'SELECT t.warehouse_id, COUNT(t.id) AS count
            FROM {tablePrefix}ext_notification_recipient t 
            LEFT JOIN {tablePrefix}ext_notification n ON n.id = t.notification_id
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id
            WHERE t.warehouse_id > 0';

		$params = [];
		if (isset($datas['admin_id'])) {
			$sql .= ' AND t.admin_id =:admin_id';
			$params['admin_id'] = $datas['admin_id'];
		}

		if (isset($datas['status'])) {
			$sql .= ' AND t.status =:status';
			$params['status'] = $datas['status'];
		}

        $sql .= ' GROUP BY t.warehouse_id ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getLastNoticeId($datas = [])
    {
        $sql = 'SELECT MAX(t.notification_id) AS max_id
            FROM {tablePrefix}ext_notification_recipient t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id
            WHERE 1';

		$params = [];
		if (isset($datas['admin_id'])) {
			$sql .= ' AND t.admin_id =:admin_id';
			$params['admin_id'] = $datas['admin_id'];
		}

		if (isset($datas['warehouse_id'])) {
			$sql .= ' AND t.warehouse_id =:warehouse_id';
			$params['warehouse_id'] = $datas['warehouse_id'];
		}

		if (isset($datas['status'])) {
			$sql .= ' AND t.status =:status';
			$params['status'] = $datas['status'];
		}

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return (!empty($row['max_id']))? $row['max_id'] : 0;
    }
}
