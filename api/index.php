<?php

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);


require_once("REST.api.php");


class API extends REST {

    public $data = "";

    private $db = NULL;

    public function __construct(){
        parent::__construct();                // Init parent contructor
        $this->dbConnect();                    // Initiate Database connection
    }

    /*
        Database connection
    */
    private function dbConnect(){
        $config_json = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/../restapienv.json');  
        $config = json_decode($config_json, true);  //outside json file [restapienv.json] should not have trailing commas or else it returns null.
        if ($this->db != NULL) {
            return $this->db;
        } else {
            $this->db = mysqli_connect($config['server'],$config['username'],$config['password'], $config['database']);
            if (!$this->db) {
                die("Connection failed: ".mysqli_connect_error());
            } else {
                return $this->db;
            }
        }
    }

    /*
        * Public method for access api.
        * This method dynmically call the method based on the query string
        *
        */
    public function processApi(){
        $func = strtolower(trim(str_replace("/","",$_REQUEST['rquest'])));
        if((int)method_exists($this,$func) > 0)
            $this->$func();
        else
            $this->response('',400);                // If the method not exist with in this class, response would be "Page not found".
    }

    /*************API SPACE START*******************/

    private function about(){

        if($this->get_request_method() != "POST"){
            $error = array('status' => 'WRONG_CALL', "msg" => "The type of call cannot be accepted by our servers.");
            $error = $this->json($error);
            $this->response($error,406);
        }
        $data = array('version' => '0.1', 'desc' => 'This API is created by Blovia Technologies Pvt. Ltd., for the public usage for accessing data about vehicles.');
        $data = $this->json($data);
        $this->response($data,200);

    }

    public function test(){

        $data = $this->json(getallheaders());
        $this->response($data,200);
    }

    private function get_current_user(){
        $username = $this->is_logged_in();
        if($username){
            $data = [
                "username" => $username
            ];
            $this->response($this->json($data), 200);
        }else{
            $data = [
                "error" => "Ubauthorized"
            ];
            $this->response($this->json($data), 403);
        }
    }

    private function user_exist(){
        if(isset($this->_request['data'])){
            $data = $this->_request['data'];
            $db = $this->dbConnect();

            // Use a prepared statement
            $stmt = mysqli_prepare($db, "SELECT `id`, `username`, `mobile` FROM `users` WHERE `id` = ? OR `username` = ? OR `mobile` = ?");
            mysqli_stmt_bind_param($stmt, "sss", $data, $data, $data);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if($data = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                $this->response($this->json($data), 200);
            }else{
                $data = ["error" => "user_not_found"];
                $this->response($this->json($data), 404);
            }

            mysqli_stmt_close($stmt);
            mysqli_close($db);
        }else{
            $data = ["error" => "exception_failed"];
            $this->response($this->json($data), 417);
        }
    }

    private function signup(){
        if($this->get_request_method() == "POST"){
            if(isset($this->_request['username']) and isset($this->_request['password'])and isset($this->_request['mobile'])){
                $username = $this->_request['username'];
                $password = password_hash($this->_request['password'], PASSWORD_BCRYPT);
                $mobile = $this->_request['mobile'];


                $db = $this->dbConnect();
                // checking existing user
                $existing_user = mysqli_prepare($db,"SELECT * FROM `users` WHERE `username` = ?");
                mysqli_stmt_bind_param($existing_user, "s", $username);
                mysqli_stmt_execute($existing_user);
                $result = mysqli_stmt_get_result($existing_user);
    
                if(mysqli_num_rows($result) > 0){
                    $data = [
                        "message" => "user already exists"
                    ];
                    $this->response($this->json($data),404);
                }

                //new user insertion
                $query = "INSERT INTO `users` (`username`, `password`, `mobile`) VALUES (?, ?, ?);";
                $stmt = mysqli_prepare($db, $query);
                mysqli_stmt_bind_param($stmt, "sss", $username, $password, $mobile);
                $execute = mysqli_stmt_execute($stmt);

                if($execute){
                    $data = [
                        "message" => "success"
                    ];
                    $this->response($this->json($data),201);
                }else{
                    $data = [
                        "message" => "internal_server_error"
                    ];
                    $this->response($this->json($data),500); 
                }
                mysqli_stmt_close($stmt);
                mysqli_close($db);

            }else{
                $data = [
                    "message" => "expectation_failed"
                ];
                $this->response($this->json($data),417); 
            }
        }else{
            $data = [
                "error" => "method_not_found"
            ];
            $this->response($this->json($data), 405);
        }
    }


    private function login() {
        if ($this->get_request_method() == "POST") {
            if (isset($this->_request['username']) && isset($this->_request['password'])) {
                $username = $this->_request['username']; // Can be username, mobile, or ID
                $password = $this->_request['password'];
    
                $db = $this->dbConnect();
    
                // Check if input is numeric (ID or mobile) or string (username)
                if (is_numeric($username)) {
                    $query = mysqli_prepare($db, "SELECT * FROM `users` WHERE `id` = ? OR `mobile` = ?");
                    mysqli_stmt_bind_param($query, "ss", $username, $username);
                } else {
                    $query = mysqli_prepare($db, "SELECT * FROM `users` WHERE `username` = ?");
                    mysqli_stmt_bind_param($query, "s", $username);
                }
    
                mysqli_stmt_execute($query);
                $result = mysqli_stmt_get_result($query);
                $user = mysqli_fetch_assoc($result);
    
                // Check if user exists and password matches
                if ($user && password_verify($password, $user['password'])) {
                    $userid = $user['id'];
                    $token = $this->generate_hash();
    
                    // Insert login session
                    $sessionQuery = mysqli_prepare($db, "INSERT INTO `session` (`session_token`, `is_valid`, `user_id`) VALUES (?, ?, ?)");
                    $is_valid = 1;
                    mysqli_stmt_bind_param($sessionQuery, "ssi", $token, $is_valid, $userid);
                    $execute = mysqli_stmt_execute($sessionQuery);
    
                    if ($execute) {
                        $data = [
                            "message" => "success",
                            "token" => $token
                        ];
                        $this->response($this->json($data), 201);
                    } else {
                        $data = ["message" => "internal_server_error"];
                        $this->response($this->json($data), 500);
                    }
                } else {
                    $data = ["message" => "invalid_credentials"];
                    $this->response($this->json($data), 401);
                }
            } else {
                $data = ["message" => "expectation_failed"];
                $this->response($this->json($data), 417);
            }
        } else {
            $data = ["error" => "method_not_found"];
            $this->response($this->json($data), 405);
        }
    }
    
    private function logout(){
        $username = $this->is_logged_in();
        if($username){
            $headers = getallheaders();
            $auth_token = $headers["Authorization"];
            $auth_token = explode(" ", $auth_token)[1];
            $query = "DELETE FROM session WHERE session_token='$auth_token'";
            $db = $this->dbConnect();
            if(mysqli_query($db, $query)){
                $data = [
                    "message"=> "success"
                ];
                $this->response($this->json($data), 200);
            } else {
                $data = [
                    "user"=> $this->is_logged_in()
                ];
                $this->response($this->json($data), 200);
            }
        } else {
            $data = [
                "user"=> $this->is_logged_in()
            ];
            $this->response($this->json($data), 200);
        }

    }

    private function is_logged_in(){
        $headers = getallheaders();
        if(isset($headers['Authorization'])){
            $auth_token = $headers['Authorization'];
            $auth_token = explode(" ", $auth_token)[1];
    
            $query = "SELECT * FROM `session` WHERE `session_token` = '$auth_token'";
            $db = $this->dbConnect();
            $_result = mysqli_query($db,$query);
            $d = mysqli_fetch_assoc($_result);
            if($d){
                $data = $d['user_id'];
                $result = mysqli_query($db, "SELECT `id`, `username`, `mobile` FROM `users` WHERE `id`='$data' OR `username`='$data' OR `mobile`='$data' ");
                if($result){
                    $result = mysqli_fetch_array($result, MYSQLI_ASSOC);
                    return $result['username'];
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }
    

    private function generate_hash(){
        $bytes = random_bytes(16);
        return bin2hex($bytes);
    }

    /*************API SPACE END*********************/

    /*
        Encode array into JSON
    */
    private function json($data){
        if(is_array($data)){
            return json_encode($data, JSON_PRETTY_PRINT);
        }else{
            return "{}";
        }
    }

}

// Initiiate Library

$api = new API;
$api->processApi();
?>