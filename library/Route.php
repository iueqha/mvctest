<?php
class Route{
    public $default =array(
        'controller' => 'home',
        'action'      => 'index'
    );
    // 自动加载类
    protected function autoload($classname,$dir="")
    {//echo APP_PATH . 'controller/'  .$dir. strtolower($classname) . 'Controller.php';die;
        if (file_exists(APP_PATH . 'controller/'  .$dir. strtolower($classname) . 'Controller.php'))
        {
            require_once(APP_PATH . 'controller/'  .$dir. strtolower($classname) . 'Controller.php');
        }
        else
        {
            /* Error Code for can not find the files */
            die('class not found.<br />');
        }
    }
    public function run(){
        $requestUrl = $_SERVER['REQUEST_URI'];
        $requestArr = explode("?",$requestUrl);
        $url = $requestArr[0];

        $urlArr = explode("/", trim($url, "/"));
        $dir    = "";
        if(count($urlArr) == 3){
            $dir  = array_shift($urlArr);
            $dir .= "/";
        }
        $controller = array_shift($urlArr);
        $action = array_shift($urlArr);
        $param = $urlArr;

        if ($controller == "")
        {
            $controller = $this->default['controller'];
            $action = $this->default['action'];
        }

        if ($action == "")
        {
            $action = $this->default['action'];
        }
        $this->autoload($controller,$dir);


        // 控制类书写规则 HomeController->Index
        $controllerName = ucfirst($controller).'Controller';
        $dispatch = new $controllerName($controller, $action);
        //echo $action;die;
        if (method_exists($dispatch, ucfirst($action).'Action'))
        {
            call_user_func_array(array($dispatch, ucfirst($action).'Action'), $param);
        }
        else
        {
            /* Error Code for Method is not exists */
            die('method not exitsts.<br />');
        }
    }
}
