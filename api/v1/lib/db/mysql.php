<?php
	class mysql implements DB {
		private $db_connection;

		function __construct($db_config) {
			$host = isset($db_config['host']) ? $db_config['host'] : ini_get('mysqli.default_host');
			$db = isset($db_config['db']) ? $db_config['db'] : '';
			$user = isset($db_config['user']) ? $db_config['user'] : ini_get('mysqli.default_user');
			$password = isset($db_config['password']) ? $db_config['password'] : ini_get('mysqli.default_pw');

			$this->db_connection = new PDO("mysql:host=${host};dbname=${db};charset=UTF8", $user, $password);
			$this->db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}

		public function getUser($id, $login) {
			if ((isset($id) && !is_numeric($id)) || (isset($login) && !is_string($login)))
				return false;

			$statement = 'SELECT id, customer, email, fullname, password, access, phone, is_active, is_admin FROM User WHERE ';
			$params = array();
			if (isset($id)) {
				$statement .= 'id = :id';
				$params[':id'] = $id;
			} else {
				$statement .= 'email = :email';
				$params[':email'] = $login;
			}

			$stmt = $this->db_connection->prepare($statement);
			if (!$stmt->execute($params))
				return null;

			if ($stmt->rowCount() == 0)
				return false;

			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$row['id'] = intval($row['id']);
			$row['is_active'] = boolval($row['is_active']);
			$row['is_admin'] = boolval($row['is_admin']);

			return $row;
		}
    
    public function getUsersByCustomerId($id) {
      if ( !(isset($id) && is_numeric($id)) )
        return false;
      
      $statement = 'SELECT id, fullname, access, phone, is_active, is_admin, email, login FROM User WHERE Customer='.$id; 
			
      $stmt = $this->db_connection->prepare($statement);
			if (!$stmt->execute($params))
				return null;
      
			if ($stmt->rowCount() == 0)
				return false;
      
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      foreach($rows as $row) {
        $row['id'] = intval($row['id']);
        $row['access'] = boolval($row['access']);
        $row['is_active'] = boolval($row['access']);
        $row['is_admin'] = boolval($row['access']);
      }
      
      return $rows;
    }


		public function isConnected() {
			// return !$this->db_connection->connect_error;
			return true;
		}
	}
?>
