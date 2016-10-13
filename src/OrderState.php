<?php
/*
0 : 오류
1 : 입금확인중[주문접수]
2 : 결제완료
3 : 배송준비중[주문서출력]
4 : 배송중[발송완료]
5 : 배송완료
===============================
6 : 구매확정[고객확인]
7 : 구매확정보류
11 : 미입금취소
12 : 취소중
13 : 취소완료
 */
namespace Sunyfor\LibOrderState;

use Sunyforss\LibOrderState\Model\Ord_b2c;
use Exception;

class OrderState
{
    public function __construct()
    {

    }

    // 주문상태변경
    public function changeStatus($num_ord, $f_ord) {

        $result = true;
        $allowStatus = array('1', '2', '3', '4', '5');

        try {
            $ord_b2c = new Ord_b2c();
            $DB = $ord_b2c->getConnection();

            // 주문상품목록
            $itemList = $ord_b2c->getListByOrderNumber($num_ord);
            $itemList[0]->new_f_ord = $f_ord;
            $masterOrder = $itemList[0];

            // f_ord 유효성 검사
            $this->statusValidation($itemList);

            // Transaction start
            $DB->beginTransaction();

            foreach ($itemList as $item) {

                if (in_array($item->f_ord, $allowStatus) == true && $masterOrder->f_ord == $item->f_ord) {

                    $vo = array(
                        'num_ord'           => $item->num_ord,
                        'num_item'          => $item->num_item,
                        'current_f_ord'     => $item->f_ord,
                        'f_ord'             => $f_ord,
                        'idx_member'        => $item->idx_member,
                        'idx_ord_b2c'       => $item->pr_idx,
                    );

                    //print_r($vo);

                    // 상태변경
                    $result = $ord_b2c->updateStatus($vo);

                    if ($result == true) {
                        // Log insert
                        $result = $ord_b2c->insertLog($vo);
                    }

                } // End if
            } // End foreach

            if ($result == true) {
                $DB->commit();
            } else {
                $DB->rollBack();
            }

        } // End try

        catch(Exception $e) {
            echo "changeStatus => ". $e->getMessage();
            $result = false;
        }

        return $result;

    }

    // 취소철회
    public function cancelWithdraw($num_ord) {

        $result = true;

        try{
            $ord_b2c = new Ord_b2c();
            $DB = $ord_b2c->getConnection();

            // 주문상품목록
            $itemList = $ord_b2c->getListByOrderNumber($num_ord);
            $masterOrder = $itemList[0];

            // 기존 f_ord
            $current_f_ord = $masterOrder->f_ord;
            $pr_idx = $masterOrder->pr_idx;
            $f_ord = '';

            if ($current_f_ord != '12') {
                throw new Exception("Invalid status value [". $num_ord ." Status ". $current_f_ord ."]");
            }

            // 주문 상태가 취소 중인 데이터를 제외한 가장 최신 데이터
            $lastLog = $ord_b2c->getLastRegistrationLog($pr_idx);

            // 취소중 이전 상태값
            $f_ord = $lastLog[0]->f_ord;

            // Transaction start
            $DB->beginTransaction();

            foreach ($itemList as $item) {

                if ($masterOrder->f_ord == $item->f_ord) {

                    $vo = array(
                        'num_ord'           => $item->num_ord,
                        'num_item'          => $item->num_item,
                        'current_f_ord'     => $item->f_ord,
                        'f_ord'             => $f_ord,
                        'idx_member'        => $item->idx_member,
                        'idx_ord_b2c'       => $item->pr_idx,
                    );

                    //print_r($vo);

                    // 상태변경
                    $result = $ord_b2c->updateStatus($vo);

                    if ($result == true) {
                        // Log insert
                        $result = $ord_b2c->insertLog($vo);
                    }

                } // End if
            } // End foreach

            if ($result == true) {
                $DB->commit();
            } else {
                $DB->rollBack();
            }
        }

        catch(Exception $e) {
            echo "cancelWithdraw => ". $e->getMessage();
        }

        return $result;
    }

    // 개별주문상태변경
    public function changeStatusIndivisual($num_item, $f_ord) {

        $result = true;

        try{
            $ord_b2c = new Ord_b2c();
            $DB = $ord_b2c->getConnection();

            // 주문번호 상세정보
            $itemDetail = $ord_b2c->getDetailByItemOrderNumber($num_item);
            $itemDetail[0]->new_f_ord = $f_ord;

            // f_ord 유효성 검사
            $this->statusValidation($itemDetail);

            $vo = array(
                'num_ord'           => $itemDetail[0]->num_ord,
                'num_item'          => $itemDetail[0]->num_item,
                'current_f_ord'     => $itemDetail[0]->f_ord,
                'f_ord'             => $f_ord,
                'idx_member'        => $itemDetail[0]->idx_member,
                'idx_ord_b2c'       => $itemDetail[0]->pr_idx,
            );

            // Transaction start
            $DB->beginTransaction();

            // 상태변경
            $result = $ord_b2c->updateStatus($vo);

            if ($result == true) {
                // Log insert
                $result = $ord_b2c->insertLog($vo);
            }

            if ($result == true) {
                $DB->commit();
            } else {
                $DB->rollBack();
            }
        }

        catch(Exception $e) {
            echo "changeStatusIndivisual => ". $e->getMessage();
            $result = false;
        }

        return $result;
    }

    // 개별취소철회
    public function cancelWithdrawIndivisual($num_item) {

        $result = true;

        try{
            $ord_b2c = new Ord_b2c();
            $DB = $ord_b2c->getConnection();

            // 주문번호 상세정보
            $itemDetail = $ord_b2c->getDetailByItemOrderNumber($num_item);

            // 기존 f_ord
            $current_f_ord = $itemDetail[0]->f_ord;
            $pr_idx = $itemDetail[0]->pr_idx;
            $f_ord = '';

            if ($current_f_ord != '12') {
                throw new Exception("Invalid status value [". $num_item ." Status ". $current_f_ord ."]");
            }

            // 주문 상태가 취소 중인 데이터를 제외한 가장 최신 데이터
            $lastLog = $ord_b2c->getLastRegistrationLog($pr_idx);

            // 취소중 이전 상태값
            $f_ord = $lastLog[0]->f_ord;

            $vo = array(
                'num_ord'           => $itemDetail[0]->num_ord,
                'num_item'          => $itemDetail[0]->num_item,
                'current_f_ord'     => $itemDetail[0]->f_ord,
                'f_ord'             => $f_ord,
                'idx_member'        => $itemDetail[0]->idx_member,
                'idx_ord_b2c'       => $itemDetail[0]->pr_idx,
            );

            //print_r($vo);

            // Transaction start
            $DB->beginTransaction();

            // 상태변경
            $result = $ord_b2c->updateStatus($vo);

            if ($result == true) {
                // Log insert
                $result = $ord_b2c->insertLog($vo);
            }

            if ($result == true) {
                $DB->commit();
            } else {
                $DB->rollBack();
            }
        }

        catch(Exception $e) {
            echo "cancelWithdrawIndivisual => ". $e->getMessage();
            $result = false;
        }

        return $result;
    }

    public function statusValidation($detail) {

        $num_ord        = $detail[0]->num_ord;
        $current_f_ord  = $detail[0]->f_ord;
        $new_f_ord      = $detail[0]->new_f_ord;

        if ($current_f_ord == $new_f_ord) {
            throw new Exception("Already status changed [". $num_ord ." Status ". $current_f_ord ." -> ". $new_f_ord ."]");
        }

        $check = true;

        switch ($current_f_ord) {
            case '1': // 입금확인중[주문접수] => 결제완료 | 미입금취소
                if ($new_f_ord != '2' && $new_f_ord != '11') {
                    $check = false;
                }
                break;
            case '2' : // 결제완료 => 배송준비중[주문서출력] | 취소중
                if ($new_f_ord != '3' && $new_f_ord != '12') {
                    $check = false;
                }
                break;
            case '3' : // 배송준비중[주문서출력] => 배송중[발송완료]
                if ($new_f_ord != '4') {
                    $check = false;
                }
                break;
            case '4' : // 배송중[발송완료] => 배송완료
                if ($new_f_ord != '5') {
                    $check = false;
                }
                break;
            case '5' : // 배송완료 => 구매확정[고객확인] | 구매확정보류
                if ($new_f_ord != '6' && $new_f_ord != '7') {
                    $check = false;
                }
                break;
            case '7' : // 구매확정보류 => 구매확정
                if ($new_f_ord != '6') {
                    $check = false;
                }
                break;
            case '11' : // 미입금취소 => 주문복구 : 입금확인중[주문접수]
                if ($new_f_ord != '1') {
                    $check = false;
                }
                break;
            case '12' : // 취소중 => 취소완료
                if ($new_f_ord != '13') {
                    $check = false;
                }
                break;
            default:
                $check = false;
                break;
        }

        if ($check != true) {
            throw new Exception("Invalid status value [". $num_ord ." Status ". $current_f_ord ." -> ". $new_f_ord ."]");
        }
    }

}
