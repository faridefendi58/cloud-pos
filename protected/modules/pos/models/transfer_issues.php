<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class TransferIssuesModel extends \Model\BaseModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_ON_PROCESS = 'processed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_transfer_issue';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ti_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, wf.title AS warehouse_from_name, wt.title AS warehouse_to_name, 
            wh.title AS wh_group_name    
            FROM {tablePrefix}ext_transfer_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wt ON wt.id = t.warehouse_to  
            LEFT JOIN {tablePrefix}ext_warehouse_group wh ON wh.id = t.wh_group_id 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }

            if (isset($data['warehouse_from'])) {
                if ($data['warehouse_from'] == 0) {
                    $sql .= ' AND (t.warehouse_from =0 OR t.warehouse_from IS NULL)';
                } else {
                    $sql .= ' AND t.warehouse_from =:warehouse_from';
                    $params['warehouse_from'] = $data['warehouse_from'];
                }
            }

            if (isset($data['date_start']) && isset($data['date_end'])) {
                $sql .= ' AND DATE_FORMAT(t.date_transfer, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
                $params['date_start'] = $data['date_start'];
                $params['date_end'] = $data['date_end'];
            }

            if (isset($data['wh_group_id'])) {
                if (is_array($data['wh_group_id'])) {
                    $group_id = implode(", ", $data['wh_group_id']);
                    $sql .= ' AND t.wh_group_id IN ('.$group_id.')';
                } else {
                    $sql .= ' AND t.wh_group_id =:wh_group_id';
                    $params['wh_group_id'] = $data['wh_group_id'];
                }
            }
        }

        $sql .= ' ORDER BY t.date_transfer DESC';

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
        $sql = 'SELECT t.*,  a.name AS created_by_name, ab.name AS updated_by_name, 
            wf.title AS warehouse_from_name, wt.title AS warehouse_to_name, 
            wh.title AS wh_group_name, wh.pic AS wh_group_pic 
            FROM {tablePrefix}ext_transfer_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wt ON wt.id = t.warehouse_to  
            LEFT JOIN {tablePrefix}ext_warehouse_group wh ON wh.id = t.wh_group_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastTiNumber($ti_serie = null)
    {
        $sql = 'SELECT MAX(t.ti_nr) AS max_nr 
            FROM {tablePrefix}ext_transfer_issue t 
            WHERE 1';

        $params = [];
        if (!empty($po_serie)) {
            $sql .= ' AND t.ti_serie =:ti_serie';
            $params['ti_serie'] = $ti_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }

	public function getDataByWarehouse($data = [])
    {
        $sql = 'SELECT t.id 
            FROM {tablePrefix}ext_transfer_issue t 
            WHERE 1';

        $params = [];
        if (!empty($data)) {
            $sql .= ' AND t.warehouse_from =:warehouse_from AND t.warehouse_to =:warehouse_to AND t.status =:status';
            $params['warehouse_from'] = $data['warehouse_from'];
            $params['warehouse_to'] = $data['warehouse_to'];
            $params['status'] = self::STATUS_ON_PROCESS;
        }

		$sql .= ' ORDER BY t.id DESC LIMIT 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return (is_array($row))? $row['id'] : 0;
    }

	public function getHistory($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, wf.title AS warehouse_from_name, wt.title AS warehouse_to_name, 
            wh.title AS wh_group_name    
            FROM {tablePrefix}ext_transfer_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wt ON wt.id = t.warehouse_to  
            LEFT JOIN {tablePrefix}ext_warehouse_group wh ON wh.id = t.wh_group_id 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }

            if (isset($data['warehouse_from'])) {
                if ($data['warehouse_from'] == 0) {
                    $sql .= ' AND (t.warehouse_from =0 OR t.warehouse_from IS NULL)';
                } else {
                    $sql .= ' AND t.warehouse_from =:warehouse_from';
                    $params['warehouse_from'] = $data['warehouse_from'];
                }
            }

			if (isset($data['warehouse_to'])) {
                $sql .= ' AND t.warehouse_to =:warehouse_to';
				$params['warehouse_to'] = $data['warehouse_to'];
            }

            if (isset($data['date_start']) && isset($data['date_end'])) {
                $sql .= ' AND DATE_FORMAT(t.date_transfer, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
                $params['date_start'] = $data['date_start'];
                $params['date_end'] = $data['date_end'];
            }

            if (isset($data['wh_group_id'])) {
                if (is_array($data['wh_group_id'])) {
                    $group_id = implode(", ", $data['wh_group_id']);
                    $sql .= ' AND t.wh_group_id IN ('.$group_id.')';
                } else {
                    $sql .= ' AND t.wh_group_id =:wh_group_id';
                    $params['wh_group_id'] = $data['wh_group_id'];
                }
            }
        }

        $sql .= ' ORDER BY t.date_transfer DESC';

		if (isset($data['limit'])) {
			$sql .= ' LIMIT '. $data['limit'];
		}

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getWHHistory($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, wf.title AS warehouse_from_name, wf.code AS warehouse_from_code, 
            wt.title AS warehouse_to_name, wt.code AS warehouse_to_code, tr.id AS tr_id, tr.tr_number
            FROM {tablePrefix}ext_transfer_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wt ON wt.id = t.warehouse_to  
            LEFT JOIN {tablePrefix}ext_transfer_receipt tr ON tr.ti_id = t.id  
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }

            if (isset($data['warehouse_id'])) {
                $sql .= ' AND (t.warehouse_from =:warehouse_id OR t.warehouse_to =:warehouse_id)';
                $params['warehouse_id'] = $data['warehouse_id'];
            }

            if (isset($data['date_start']) && isset($data['date_end'])) {
                $sql .= ' AND DATE_FORMAT(t.date_transfer, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
                $params['date_start'] = $data['date_start'];
                $params['date_end'] = $data['date_end'];
            }
        }

        $sql .= ' ORDER BY t.date_transfer DESC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getItems($ti_id = 0)
    {
        $sql = 'SELECT t.product_id, t.title, t.quantity, t.unit, tr.quantity AS quantity_receipt  
            FROM {tablePrefix}ext_transfer_issue_item t   
            LEFT JOIN {tablePrefix}ext_transfer_receipt_item tr ON tr.ti_item_id = t.id 
            WHERE 1';

        $params = [];
        if ($ti_id > 0) {
            $sql .= ' AND t.ti_id =:ti_id';
            $params['ti_id'] = $ti_id;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        $items = [];
        foreach ($rows as $i => $row) {
            $row['selisih'] = (int)$row['quantity_receipt'] - (int)$row['quantity'];
            $items[$row['product_id']] = $row;
        }

        return $items;
    }
}
