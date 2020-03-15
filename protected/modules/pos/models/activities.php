<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class ActivitiesModel extends \Model\BaseModel
{
    const TYPE_TRANSFER_ISSUE = 'transfer_issue';
    const TYPE_INVENTORY_ISSUE = 'inventory_issue';
    const TYPE_TRANSFER_RECEIPT = 'transfer_receipt';
    const TYPE_INVENTORY_RECEIPT = 'inventory_receipt';
    const TYPE_PURCHASE_ORDER = 'purchase_order';
    const TYPE_STOCK_IN = 'stock_in';
    const TYPE_STOCK_OUT = 'stock_out';

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

			if (isset($data['status'])) {
				$sql .= ' AND t.status =:status';
				$params['status'] = $data['status'];
			}

			if (isset($data['type']) && $data['type'] != '-') {
				if (!is_array($data['type'])) {
					$sql .= ' AND t.type =:type';
					$params['type'] = $data['type'];
				} else {
					$stats = [];
					foreach($data['type'] as $s => $stat) {
						$stats[] = '"'. $stat .'"';
					}
					if (count($stats) > 0) {
						$types = implode(", ", $stats);
						$sql .= ' AND t.type IN ('. $types .')';
					}
				}
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
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS finished_by_name, ac.name AS updated_by_name, ad.name AS checked_by_name
            FROM {tablePrefix}ext_activities t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.finished_by 
            LEFT JOIN {tablePrefix}admin ac ON ac.id = t.updated_by 
            LEFT JOIN {tablePrefix}admin ad ON ad.id = t.checked_by 
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

            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );
        if (!empty($row) && array_key_exists('configs', $row)) {
            $row['configs'] = json_decode($row['configs'], true);
			if (array_key_exists('warehouse_from', $row['configs'])) {
				$wh_model = \Model\WarehousesModel::model()->findByPk($row['configs']['warehouse_from']);
				if ($wh_model instanceof \RedBeanPHP\OODBBean) {
					$row['warehouse_from'] = $wh_model->id;
					$row['warehouse_from_name'] = $wh_model->title;
					$row['warehouse_from_code'] = $wh_model->code;
				}
			}
			if (array_key_exists('warehouse_to', $row['configs'])) {
				$wh_model = \Model\WarehousesModel::model()->findByPk($row['configs']['warehouse_to']);
				if ($wh_model instanceof \RedBeanPHP\OODBBean) {
					$row['warehouse_to'] = $wh_model->id;
					$row['warehouse_to_name'] = $wh_model->title;
					$row['warehouse_to_code'] = $wh_model->code;
				}
			}
        }

        return $row;
    }

    public function getLatestGroupId() {
        $sql = 'SELECT MAX(t.group_id) AS max_group_id
            FROM {tablePrefix}ext_activities t  
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql );

        return $row['max_group_id'];
    }
}
