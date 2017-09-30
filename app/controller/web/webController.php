<?php
//require_once(ROOT_PATH.'library/controller.php');
class webController extends Controller{
    

    public $name = "ABC";
    public function indexAction(){
        $a= $this->get('a');
        //echo $this->get('a');
        //echo "<script>alert(111);</script>";
        include(APP_PATH.'view/test.html');
        //include(APP_PATH.'view/test2.html');
    }
    public function sayAction(){
        echo "say Hello2";
    }
    public function infoAction(){
        include(APP_PATH.'view/test2.html');
        
    }
    public function dbAction(){
        $a= $this->get('a');
        $model = new \app\model\dbTest();
        $model->create($a);
    }
}