<?php

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);


    require_once("REST.api.php");


    class API extends REST {

        public $data = "";

        // const DB_SERVER = "mysql.selfmade.ninja:3306";
        // const DB_USER = "roottester";
        // const DB_PASSWORD = "prad2003";
        // const DB = "roottester_restapi";

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
            $data = [
                "Status" => "Working"
            ];
            $data = $this->json($data);
            $this->response($data,200);
        }



        /*************API SPACE END*********************/

        /*
            Encode array into JSON
        */
        private function json($data){
            if(is_array($data)){
                return json_encode($data, JSON_PRETTY_PRINT);
            }
        }

    }

    // Initiiate Library

    $api = new API;
    $api->processApi();
?>