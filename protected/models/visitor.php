<?php
namespace Model;

require_once __DIR__ . '/base.php';

class VisitorModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'visitor';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            //['ip_address, url', 'safe'],
        ];
    }

    /**
     * @param $session_id
     * @return int
     */
    public function deactivate($session_id)
    {
        $sql = 'UPDATE {tablePrefix}visitor SET active = 0 WHERE session_id = :session_id AND active = 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $update = R::exec( $sql, ['session_id' => $session_id] );

        return $update;
    }

    /**
     * Breakdown the cookie format
     * @param string $name
     * @param bool $expiration
     * @return bool|null|string
     */
    public function getCookie($name='_ma',$expiration=true)
    {
        if(!empty($_COOKIE[$name])){
            $pecah = explode("-",$_COOKIE[$name]);
            if($expiration){
                if(!empty($pecah[1]))
                    return date("Y-m-d H:i:s",strtotime($pecah[1]));
                else
                    return null;
            }else
                return $pecah[0];
        }
        return null;
    }

    /**
     * Getting the active visitor
     * @param null $date
     * @return mixed
     */
    public function getActiveVisitor($date = null)
    {
        if (empty($date))
            $date = date("Y-m-d H:i:s");

        $sql = 'SELECT COUNT(t.ip_address) AS count 
          FROM {tablePrefix}visitor t 
          WHERE DATE_FORMAT(t.date_expired,"%Y-%m-%d %H:%i:%s") >= :date_limit 
          GROUP BY t.session_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['date_limit' => date("Y-m-d H:i:s",strtotime($date))] );

        return $row['count'];
    }

    /**
     * @param null $date_from
     * @param null $date_to
     * @return array
     */
    public function getVisitorByDevice($date_from = null, $date_to = null)
    {
        if (empty($date_from))
            $date_from = date('Y-m-d');
        if (empty($date_to))
            $date_to = date('Y-m-d');

        $sql = 'SELECT IF(t.mobile >0, "mobile","desktop") AS device, COUNT(t.ip_address) AS count 
          FROM {tablePrefix}visitor t 
          WHERE DATE_FORMAT(t.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to  
          GROUP BY t.mobile';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, ['date_from' => date("Y-m-d",strtotime($date_from)), 'date_to' => date("Y-m-d",strtotime($date_to))] );

        $items = [];
        if (is_array($rows)) {
            foreach ($rows as $i => $row) {
                $items[$row['device']] = $row['count'];
            }
        }

        return $items;
    }

    /**
     * @param null $date_from
     * @param null $date_to
     * @return mixed
     */
    public function getUniqueVisitor($date_from = null, $date_to = null)
    {
        if (empty($date_from))
            $date_from = date('Y-m-d');
        if (empty($date_to))
            $date_to = date('Y-m-d');

        $sql = 'SELECT COUNT(DISTINCT v.ip_address) as count 
          FROM {tablePrefix}visitor v 
          WHERE DATE_FORMAT(v.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['date_from' => date("Y-m-d",strtotime($date_from)), 'date_to' => date("Y-m-d",strtotime($date_to))] );

        return $row['count'];
    }

    /**
     * @param $data
     * @return bool|string
     */
    public function getAverageDuration($date_from = null, $date_to = null)
    {
        if (empty($date_from))
            $date_from = date('Y-m-d');
        if (empty($date_to))
            $date_to = date('Y-m-d');

        $sql = 'SELECT DISTINCT v.session_id 
          FROM {tablePrefix}visitor v 
          WHERE DATE_FORMAT(v.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to 
          GROUP BY v.session_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, ['date_from'=>$date_from,'date_to'=>$date_to]);
        $items = [];
        if (count($rows)>0){
            foreach ($rows as $row){
                $sql2 = 'SELECT TIMEDIFF(MAX(v.created_at),MIN(v.created_at)) as diff 
                  FROM {tablePrefix}visitor v WHERE v.session_id=:session_id';

                $sql2 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql2);

                $row2 = R::getRow( $sql2, ['session_id'=>$row['session_id']] );
                if ($row2['diff'] != '00:00:00')
                    $items[] = strtotime($row2['diff']);
            }
        }
        if (count($items)>0)
            return date("H:i:s",round(array_sum($items)/count($items),0));
        else
            return '00:00:00';
    }

    /**
     * @param null $date_from
     * @param null $date_to
     * @return mixed
     */
    public function getReferral($date_from = null, $date_to = null)
    {
        if (empty($date_from))
            $date_from = date('Y-m-d');
        if (empty($date_to))
            $date_to = date('Y-m-d');

        $sql = 'SELECT COUNT(v.id) AS count 
          FROM {tablePrefix}visitor v 
          WHERE DATE_FORMAT(v.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to 
            AND v.url_referrer NOT LIKE "%'.$_SERVER['HTTP_HOST'].'%"';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['date_from'=>$date_from,'date_to'=>$date_to] );

        return $row['count'];
    }

    /**
     * @param null $date_from
     * @param null $date_to
     * @return array
     */
    public function getPageViewInterval($date_from = null,$date_to = null)
    {
        if(empty($date_from))
            $date_from = date('Y-m-d', strtotime('last month'));
        if(empty($date_to))
            $date_to = date('Y-m-d');

        $begin = new \DateTime($date_from);
        $end = new \DateTime($date_to);
        $end = $end->modify( '+1 day' );
        if ($date_from == $date_to) {
            $begin = $begin->modify('-2 day');
        }

        $interval = new \DateInterval('P1D');
        $daterange = new \DatePeriod($begin, $interval ,$end);

        $page_views = []; $sessions = [];
        foreach($daterange as $date){
            $page_views[] = array(
                strtotime($date->format("Y-m-d"))*1000,
                self::getCountVisitor($date->format("Y-m-d"),'pageview')
            );
            $sessions[] = array(
                strtotime($date->format("Y-m-d"))*1000,
                self::getCountVisitor($date->format("Y-m-d"),'session'),
            );
        }
        
        return [
            'pageview' => $page_views,
            'session' => $sessions
        ];
    }

    /**
     * @param $date
     * @param string $type
     * @return int
     */
    public function getCountVisitor($date, $type = 'pageview')
    {
        switch($type){
            case 'pageview':
                $sql = 'SELECT COUNT(v.id) as count FROM {tablePrefix}visitor v WHERE DATE_FORMAT(v.created_at,"%Y-%m-%d")=:date ORDER BY v.id DESC';
                $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);
                $row = R::getRow( $sql, ['date'=>$date] );

                return (int)$row['count'];
                break;
            case 'session':
                $sql = 'SELECT COUNT(DISTINCT v.session_id) as count FROM {tablePrefix}visitor v WHERE DATE_FORMAT(v.created_at,"%Y-%m-%d")=:date GROUP BY v.session_id';
                $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);
                $row = R::getRow( $sql, ['date'=>$date] );

                return count($row);
                break;
            case 'pageviewmonthly':
                $sql = 'SELECT COUNT(v.id) as count FROM {tablePrefix}visitor v WHERE DATE_FORMAT(v.created_at,"%Y-%m")=:date ORDER BY v.id DESC';
                $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);
                $row = R::getRow( $sql, ['date'=>$date] );

                return (int)$row['count'];
                break;
            case 'sessionmonthly':
                $sql = 'SELECT COUNT(DISTINCT v.session_id) as count FROM {tablePrefix}visitor v WHERE DATE_FORMAT(v.created_at,"%Y-%m")=:date GROUP BY v.session_id';
                $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);
                $row = R::getRow( $sql, ['date'=>$date] );

                return count($row);
                break;
        }
    }
}
