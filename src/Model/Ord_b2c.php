<?php

namespace Sunyfor\LibOrderState\Model;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;

class Ord_b2c
{
    protected $connection = 'pping_order_local';
    protected $DB;

    public function __construct($config=array()) {

        try {
            $this->DB = DB::connection($this->connection);
        } catch(Exception $e) {
            if(empty($config)) {
                throw new Exception("Connection not found ");
            } else {
                $this->DB = $this->connection($config);
            }
        }
    }

    protected function connection($config) {

        $capsule = new Capsule();

        $capsule->addConnection($config, $this->connection);
        $capsule->setAsGlobal();
		$capsule->bootEloquent();

        return $capsule->connection($this->connection);
    }

    public function getConnection() {
        return $this->DB;
    }

    // ord_b2c 상품주문상태변경
    public function updateStatus($vo) {

        $slq_date_pay = "";

        if ($vo['f_ord'] == "2") {
            $slq_date_pay = ", date_pay = now() ";
        }

        $sql = "
            update Top_Order.ord_b2c set
                   f_ord = '". $vo['f_ord'] ."'
                 , date_up = now() ". $slq_date_pay ."
             where num_ord = '".$vo['num_ord']."'
               and num_item = '".$vo['num_item']."'
        ";
        $result = $this->DB->update($sql);

        return $result;
    }

    // ord_b2c_log 주문상태변경로그
    public function insertLog($vo) {
        $sql = "
            insert Top_Order.ord_b2c_log set
                   f_ord = '". $vo['f_ord'] ."'
                 , idx_member = '". $vo['idx_member'] ."'
                 , idx_ord_b2c = '". $vo['idx_ord_b2c'] ."'
                 , date_handle = now()
        ";
        $result = $this->DB->insert($sql);

        return $result;
    }

    // 주문 상세정보
    public function getDetailByOrderNumber($num_ord) {

        $sql = "
            select pr_idx, f_ord, idx_b_manager, idx_member, num_ord, num_item, name_ord, name_pay, name_rec
              from Top_Order.ord_b2c
             where num_ord = '". $num_ord ."'
               and num_item = '0'
        ";

        $result = $this->DB->select($sql);

        if (Count($result) < 1) {
            throw new Exception("Invalid order number [". $num_ord ."]");
        }

        return $result;
    }

    // 상품주문 상세정보
    public function getDetailByItemOrderNumber($num_item) {

        $sql = "
            select pr_idx, f_ord, idx_b_manager, idx_member, num_ord, num_item, name_ord, name_pay, name_rec
              from Top_Order.ord_b2c
             where num_item = '". $num_item ."'
        ";
        $result = $this->DB->select($sql);

        if (Count($result) < 1) {
            throw new Exception("Invalid order number [". $num_item ."]");
        }

        return $result;
    }

    // 주문상품목록
    public function getListByOrderNumber($num_ord) {

        $sql = "
            select pr_idx, f_ord, idx_b_manager, idx_member, num_ord, num_item, name_ord, name_pay, name_rec
              from Top_Order.ord_b2c
             where num_ord = '". $num_ord ."'
        ";
        $result = $this->DB->select($sql);

        if (Count($result) < 1) {
            throw new Exception("Invalid order number [". $num_ord ."]");
        }

        return $result;
    }

    // 주문 상태가 취소 중인 데이터를 제외한 가장 최신 데이터
    public function getLastRegistrationLog($pr_idx) {

        $sql = "
            select *
              from Top_Order.ord_b2c_log
             where idx_ord_b2c = '". $pr_idx ."'
               and f_ord != '12'
             order by pr_idx desc
             limit 1
        ";

        $result = $this->DB->select($sql);

        return $result;
    }

}
