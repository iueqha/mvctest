<?php
namespace app\model;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/31
 * Time: 17:55
 */
class dbTest extends Base{
    protected $_db = 'test_data';
    protected $_table = 'product_category';
    protected $_primary_key = 'id';

    public function create($product_id){
        
        $ret = $this->insert(['product_id'=>$product_id]);
        var_dump($ret);
    }
}