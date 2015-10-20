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

			$statement = 'SELECT id, customer, email, fullname, password, salt, access, phone, is_active, is_admin FROM User WHERE ';

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
			if ($stmt->rowCount() != 1)
				return false;

			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$user = $this->reformUsers($rows);
			return $user[0];
		}

		public function getAllUser($id, $cid) {
			if ( !(isset($id) && is_numeric($id)) )
				return false;
			if ( !(isset($cid) && is_numeric($cid)) )
				return false;

			$params = array();
			$params[':id'] = $id;
			$params['cid'] = $cid;

			$statement = <<<SQL
			SELECT u.id, u.fullname, u.access , u.phone, u.is_active, u.is_admin, u.email, u.customer,
				f.uid, f.gid, f.access AS f_access, f.chroot, f.homedirectory,
				c.name, c.total_space, c.used_space, c.path
			FROM   User u
			LEFT JOIN FtpUser f USING (id)
			LEFT JOIN Customer c ON (u.customer = c.id)
			WHERE
					u.customer = :cid
				AND
					u.id = :id
SQL;
			$stmt = $this->db_connection->prepare($statement);
			try {
				$res = $stmt->execute($params);
			} catch (Exception $e) {
				return false;
			}
			if (!$res)
				return false;
			if ($stmt->rowCount() != 1)
				return null;

			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$user =  $this->reformUsers($rows);
			return $user[0];
		}

		public function getUsersByCustomerId($id) {
			if ( !(isset($id) && is_numeric($id)) )
				return false;

			$params = array();
			$params[':id'] = $id;

			$statement = <<<SQL
			SELECT u.id, u.fullname, u.access , u.phone, u.is_active, u.is_admin, u.email, u.customer,
				f.uid, f.gid, f.access AS f_access, f.chroot, f.homedirectory
			FROM   User u
			LEFT JOIN FtpUser f USING (id)
			WHERE u.customer = :id
SQL;

			$stmt = $this->db_connection->prepare($statement);
			try {
				$res = $stmt->execute($params);
			} catch (Exception $e) {
				return false;
			}
			if (!$res)
				return false;

			if ($stmt->rowCount() == 0)
				return null;

			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

			return $this->reformUsers($rows);
		}

		public function deleteUser($id, $cid) {
			if ( !(isset($id) && is_numeric($id)) )
				return false;
			if ( !(isset($id) && is_numeric($cid)) )
				return false;

			$params = array();
			$params[':id'] = $id;
			$params[':cid'] = $cid;

			$statement = <<<SQL
			DELETE FROM User
			WHERE
					id = :id
				AND
					customer = :cid
SQL;

			$stmt = $this->db_connection->prepare($statement);
			try {
				$res = $stmt->execute($params);
			} catch (Exception $e) {
				return false;
			}
			return true;
		}

		public function updateUser($fields) {
			$id = $fields['id'];
			$statement = 'UPDATE User SET ';
			
			$params = array();
			$to_be_updated = array();
			unset($fields['id']);
			
			$i = 0;
			foreach($fields as $k => $v) {
				$i++;
				$params[":k$i"] = $k;
				$params[":v$i"] = $v;
				array_push($to_be_updated, ":k$i = :v$i");
			}
			
			$statement .= join(', ', $to_be_updated);
			$statement .= ' WHERE id = :id';

			$params[':id'] = $id;

			error_log($statement);

			$stmt = $this->db_connection->prepare($statement);

			try {
				$stmt->execute($params);
			}
			catch (Exception $e) {
				return null;
			}
			return true;
		}

		public function createUser($fields, $salt) {

			$salt = base64_encode($salt);
			$password = hash_pbkdf2('sha512', $fields['password'], $salt, 1024, 40, true);
			$password = base64_encode($password);

			$params = array(
				':customer' => $fields['customer'],
				':fullname' => $fields['fullname'],
				':access'   => $fields['access'],
				':phone'    => $fields['phone'],
				':is_admin' => $fields['is_admin'],
				':is_active'=> $fields['is_active'],
				':email'    => $fields['email'],
				':salt'     => $salt,
				':password' => $password
			);

			$statement = <<<SQL
			INSERT INTO User
				(id, customer, fullname, password, salt, access, phone, is_active, is_admin, email)
			VALUES
				(NULL, :customer, :fullname, :password, :salt, :access, :phone, :is_active, :is_admin, :email)
SQL;

			error_log($statement);
			error_log($password);
			error_log($salt);

			$stmt = $this->db_connection->prepare($statement);

			try{
				$stmt->execute($params);
			}
			catch (Exception $e){
				return false;
			}

			error_log($fields['login']);
			return $this->getUser($fields['nihil'], $fields['email']);

		}


		public function getCustomer($id) {
			if ( !(isset($id) && is_numeric($id)) )
				return false;

			$params = array();
			$params[':id'] = $id;

			$statement = <<<SQL
			SELECT c.*
			FROM Customer c
			WHERE c.id = :id
SQL;

			$stmt = $this->db_connection->prepare($statement);
			$stmt->execute($params);
			try {
				$res = $stmt->execute($params);
			} catch (Exception $e) {
				return false;
			}
			if (!$res)
				return false;

			if ($stmt->rowCount() != 1)
				return null;

			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $key => $row) {
				$rows[$key]['id'] = intval($rows[$key]['id']);
				$rows[$key]['total_space'] = intval($rows[$key]['total_space']);
				$rows[$key]['used_space'] = intval($rows[$key]['used_space']);
				$rows[$key]['max_monthly_space'] = intval($rows[$key]['max_monthly_space']);

				// Remove unnecessary elements
				unset($rows[$key]['price']);
				unset($rows[$key]['path']);
				unset($rows[$key]['url']);
			}
			return $rows[0];
		}

		public function updateCustomer($fields) {
			// define elements to be updated
			$update_fields = array(
				'name' => array('type' => 'string', 'length' => 255),
				'total_space' => array('type' => 'int')
			);

			// manage Id
			$params = array();
			$params[':id'] = intval($fields['id']);
			unset($fields['id']);

			// validate or exit
			$to_be_updated = array();
			foreach($update_fields as $k => $type) {
				if (array_key_exists($k, $fields)) {
					array_push($to_be_updated, $k . '=:' . $k);
					$msg = $this->validate($fields[$k], $type);
					if ($msg !== true)
						return $k.$msg;
					$params[':'.$k] = $fields[$k];
				}
			}

			// SQL
			$statement = 'UPDATE Customer SET ';
			$statement .= join(', ', $to_be_updated);
			$statement .= ' WHERE id = :id';

			$stmt = $this->db_connection->prepare($statement);

			try {
				$stmt->execute($params);
			}
			catch (Exception $e) {
				print $e;
				return false;
			}
			return true;
		}

		public function isConnected() {
			// return !$this->db_connection->connect_error;
			return true;
		}

		private function validate($el, $type) {
			switch ($type['type']) {
				case 'int':
					if (is_numeric($el))
						return true;
					return ' is not numeric';
					break;
				case 'string':
					if (!is_string($el))
						return ' is not string';
					if (strlen($el) > 255)
						return ' is too long';
					return true;
					break;
			}
		}

		private function reformUsers($rows) {
			if (count($rows)) foreach($rows as $key => $row) {
				// Manage User
				$rows[$key]['id'] = intval($rows[$key]['id']);
				$rows[$key]['customer'] = intval($rows[$key]['customer']);
				$rows[$key]['access'] = boolval($rows[$key]['access']);
				$rows[$key]['is_active'] = boolval($rows[$key]['is_active']);
				$rows[$key]['is_admin'] = boolval($rows[$key]['is_admin']);

				// Manage Customer
				if (isset($rows[$key]['total_space']))
					$rows[$key]['total_space'] = intval($rows[$key]['total_space']);
				if (isset($rows[$key]['used_space']))
					$rows[$key]['used_space'] = intval($rows[$key]['used_space']);

				// Manage FTP
				if (isset($rows[$key]['f_access']))
					$rows[$key]['ftp_read'] = $rows[$key]['ftp_write'] = 0;
				if (isset($rows[$key]['chroot']))
					$rows[$key]['chroot'] = boolval($rows[$key]['chroot']);
				if (($rows[$key]['access'] == 'read') || ($rows[$key]['access'] == 'read_write'))
					$rows[$key]['ftp_read'] = 1;
				if (($rows[$key]['access'] == 'write') || ($rows[$key]['access'] == 'read_write'))
					$rows[$key]['ftp_write'] = 1;

				// Special PATH
				if ((isset($rows[$key]['path'])) && (isset($rows[$key]['homedirectory'])))
					$rows[$key]['directory'] = str_replace($rows[$key]['path'], '', $rows[$key]['homedirectory']);

				// Remove unnecessary elements
				unset($rows[$key]['f_access']);
				unset($rows[$key]['path']);
				unset($rows[$key]['homedirectory']);
				unset($rows[$key]['access']);
			}
			return $rows;
		}
	}
?>
