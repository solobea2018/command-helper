<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

use Solobea\CommandHelper\controller\Home;

date_default_timezone_set('Africa/Nairobi');

session_start();

require_once "vendor/autoload.php";

$path = $_SERVER['PATH_INFO']??"";
$path_array = explode("/", trim($path, "/")); // Trim extra slashes
if (!empty($path_array) && $path_array[0]!="") {
    $raw = strtolower($path_array[0]);               // "employee-role"
    $page = str_replace(' ', '', ucwords(str_replace('-', ' ', $raw)));
    $full_path = "Solobea\\CommandHelper\\controller\\" . $page;
    $method=lcfirst($path_array[1]??"");
    $params=[];
    if (sizeof($path_array)>2){
        $params=array_slice($path_array,2);
    }

    if (class_exists($full_path)) {
        $controller = new $full_path();
        if ($method!=null && method_exists($full_path,$method)){
            if (!empty($params)){
                $controller->$method($params);
            }
            else{
                $controller->$method();
            }

        } else if(method_exists($full_path,"index")){
            $controller->index();
        } else{
            //http_response_code(404);
            echo "Page not found ";
        }
    } else {
        //http_response_code(404);
        echo "Page not found ";
    }
} else {
    // Default route to Home
    $home = new Home();
    $home->index();
}