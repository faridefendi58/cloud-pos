<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class ActivitiesModel extends \Model\BaseModel
{
    const TYPE_TRANSFER_ISSUE = 'transfer_issue';
    const TYPE_INVENTORY_ISSUE = 'inventory_issue';
    const TYPE_TRANSFER_RECEIPT = 'transfer_receipt';
    const TYPE_INVENTORY_RECEIPT = 'inventory_receipt';

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_activities';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['title', 'required'],
        ];
    }

	public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name
            FROM {tablePrefix}ext_activities t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            WHERE 1';

        $params = [];
        if (is_array($data)) {

            if (isset($data['date_start']) && isset($data['date_end'])) {
                $sql .= ' AND DATE_FORMAT(t.created_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
                $params['date_start'] = $data['date_start'];
                $params['date_end'] = $data['date_end'];
            }

			if (isset($data['warehouse_id'])) {
				$sql .= ' AND t.warehouse_id =:warehouse_id';
				$params['warehouse_id'] = $data['warehouse_id'];
			}
        }

        $sql .= ' ORDER BY t.created_at DESC';

		if (isset($data['limit'])) {
			$sql .= ' LIMIT '. $data['limit'];
		}

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getItem($data = null)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name
            FROM {tablePrefix}ext_activities t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            WHERE 1';

        $params = [];
        if (is_array($data)) {

			if (isset($data['warehouse_id'])) {
				$sql .= ' AND t.warehouse_id =:warehouse_id';
				$params['warehouse_id'] = $data['warehouse_id'];
			}

			if (isset($data['rel_id'])) {
				$sql .= ' AND t.rel_id =:rel_id';
				$params['rel_id'] = $data['rel_id'];
			}

			if (isset($data['id'])) {
				$sql .= ' AND t.id =:id';
				$params['id'] = $data['id'];
			}
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }
}
