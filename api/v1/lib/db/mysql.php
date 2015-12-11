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

		public function getUser($id, $cid, $login) {
			if (!isset($login)) {
				if ( !(isset($id) && is_numeric($id)) )
					return false;
				if ( !(isset($cid) && is_numeric($cid)) )
					return false;
			}

			$params = array();
			if (isset($login)) {
				$params[':email'] = $login;
			} else {
				$params[':id'] = $id;
				$params[':cid'] = $cid;
			}

			$statement = <<<SQL
			SELECT u.id, u.fullname, u.access , u.phone, u.is_active, u.is_admin, u.email, u.customer, u.salt, u.password,
				f.uid, f.gid, f.access AS f_access, f.chroot, f.homedirectory,
				c.name, c.total_space, c.used_space, c.path
			FROM   User u
			LEFT JOIN FtpUser f USING (id)
			LEFT JOIN Customer c ON (u.customer = c.id)
SQL;
			if (isset($login)) {
				$statement .= ' WHERE email = :email';
			} else {
				$statement .= ' WHERE  u.customer = :cid AND u.id = :id';
			}

			$stmt = $this->db_connection->prepare($statement);
			try {
				$res = $stmt->execute($params);
			} catch (Exception $e) {
				print $e;
				return false;
			}
			if (!$res)
				return false;
			if ($stmt->rowCount() != 1)
				return null;

			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$user =  $this->reformUsers($rows, true);
			return $user[0];
		}

		public function getUsersByCustomerId($id) {
			if ( !(isset($id) && is_numeric($id)) )
				return false;

			$params = array();
			$params[':id'] = $id;

			$statement = <<<SQL
			SELECT u.id, u.fullname, u.access, u.phone, u.is_active, u.is_admin, u.email, u.customer,
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

		public function setUser($fields, $salt) {
			$params = array();
			$update_fields = array(
				'email' => array('type' => 'email', 'length' => 255, 'required' => true, 'tb' => 'User'),
				'customer' => array('type' => 'int','required' => false, 'tb' => 'User'),
				'fullname' => array('type' => 'text', 'required' => true, 'tb' => 'User'),
				'password' => array('type' => 'text', 'required' => true, 'tb' => 'User'),
				'salt' => array('type' => 'string', 'length' => 255, 'required' => true, 'tb' => 'User'),
				'phone' => array('type' => 'text', 'required' => false, 'tb' => 'User'),
				'is_active' => array('type' => 'bool', 'required' => true, 'tb' => 'User'),
				'is_admin' => array('type' => 'bool', 'required' => true, 'tb' => 'User'),
				'chroot' => array('type' => 'bool', 'required' => true, 'tb' => 'FtpUser'),
				//'homedirectory' => array('type' => 'text', 'required' => true, 'tb' => 'FtpUser'),
				'access' => array('type' => 'string','length' => 255, 'required' => true, 'tb' => 'FtpUser'),
				'uid' => array('type' => 'int','required' => true, 'tb' => 'FtpUser'),
				'gid' => array('type' => 'int', 'required' => true, 'tb' => 'FtpUser')
			);

			// Manage password
			if (isset($fields['password']) && isset($salt)) {
				$salt = base64_encode($salt);
				$password = hash_pbkdf2('sha512', $fields['password'], $salt, 1024, 40, true);
				$fields['password'] = base64_encode($password);
				$fields['salt'] = $salt;
			}

			// Manage FTP Access
			$fields['access'] = 'none';
			if (($fields['ftp_read'] === true) && ($fields['ftp_write'] === true))
				$fields['access'] = 'read_write';
			else if ($fields['ftp_read'] === true)
				$fields['access'] = 'read';
			else if ($fields['ftp_write'] === true)
				$fields['access'] = 'write';
			unset($fields['ftp_read']);
			unset($fields['ftp_write']);

			if (intval($fields['id'])) // UPDATE
			{
				// Remove elements
				$params[':id'] = intval($fields['id']);
				$params[':customer'] = intval($fields['customer']);
				unset($fields['customer']);
				unset($fields['id']);

				// validate
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

				$statement = <<<SQL
				UPDATE User
				LEFT JOIN Customer ON (customer = Customer.id)
				LEFT JOIN FtpUser ON (User.id = FtpUser.id) SET

SQL;
				$statement .= join(', ', $to_be_updated);
				$statement .= ' WHERE User.id = :id AND customer = :customer';

				// Execution
				$stmt = $this->db_connection->prepare($statement);
				try {
					$stmt->execute($params);
				}
				catch (PDOException $e) {
					// Duplicate Entry
					if ($e->errorInfo[1] == 1062)
						return 'email already exists';
					return false;
				}
				return true;
			}
			else // INSERT
			{

				$paramsuser = $paramsftp = array();
				$paramsuser[':customer'] = $fields['gid']  = intval($fields['customer']);
				$fields['uid'] = 0;
				// check if all elements are presents
				$fields_inserted_user = array();
				$fields_inserted_ftp = array();
				$values_inserted_user = array();
				$values_inserted_ftp = array();

				foreach($update_fields as $k => $type) {

					if ($type['required'] === true) {
						if (array_key_exists($k, $fields) !== false) {
							$msg = $this->validate($fields[$k], $type);
							if ($msg !== true)
								return $k.$msg;
							if ($type['tb'] == 'User') {
								array_push($fields_inserted_user, $k);
								array_push($values_inserted_user, ':'.$k);
								$paramsuser[':'.$k] = $fields[$k];
							} else if ($type['tb'] == 'FtpUser') {
								array_push($fields_inserted_ftp, $k);
								array_push($values_inserted_ftp, ':'.$k);
								$paramsftp[':'.$k] = $fields[$k];
							}
						} else {
							return $k.' is needed';
						}
					} else {
						if (array_key_exists($k, $fields)) {
							$msg = $this->validate($fields[$k], $type);
							if ($msg !== true)
								return $k.$msg;
							if ($type['tb'] == 'User') {
								array_push($fields_inserted_user, $k);
								array_push($values_inserted_user, ':'.$k);
								$paramsuser[':'.$k] = $fields[$k];
							} else if ($type['tb'] == 'FtpUser') {
								array_push($fields_inserted_ftp, $k);
								array_push($values_inserted_ftp, ':'.$k);
								$paramsftp[':'.$k] = $fields[$k];
							}
						}
					}
				}

				// INSERT
				$statement1 = 'INSERT INTO User (';
				$statement1 .= join(', ', $fields_inserted_user);
				$statement1 .= ') VALUES (';
				$statement1 .= join(', ', $values_inserted_user);
				$statement1 .= ')';

				$this->db_connection->beginTransaction();
				$stmt1 = $this->db_connection->prepare($statement1);
				try {
					$stmt1->execute($paramsuser);
					$uid = $this->db_connection->lastInsertId();
				} catch(PDOException $e) {
					$this->db_connection->rollback();
					if ($e->errorInfo[1] == 1062)
						return 'email already exists';
					return false;
				}

				// add specifics elements
				array_push($fields_inserted_ftp, 'id');
				array_push($values_inserted_ftp, ':id');
				$paramsftp[':id'] = $paramsftp[':uid'] = $uid;

				$statement2 = 'INSERT INTO FtpUser (';
				$statement2 .= join(', ', $fields_inserted_ftp);
				$statement2 .= ') VALUES (';
				$statement2 .= join(', ', $values_inserted_ftp);
				$statement2 .= ')';
				$stmt2 = $this->db_connection->prepare($statement2);
				try {
					$stmt2->execute($paramsftp);
					$this->db_connection->commit();
				} catch(PDOException $e) {
					$this->db_connection->rollback();
					return false;
				}
				return true;
			}
		}

		public function getCustomer($id) {
			if ( !(isset($id) || ! is_numeric($id)) )
				return false;

			$params = array();
			$params[':id'] = $id;

			$customer_req = <<<SQL
                SELECT c.*
                FROM   Customer c
                WHERE  c.id = :id
SQL;
            
            $address_req = <<<SQL
                SELECT     a.*, c.id as customer
                FROM       Customer c
                LEFT JOIN  addresscustomerrelation rel ON rel.customer = c.id
                LEFT JOIN  Address a ON rel.address = a.id
                WHERE      c.id = :id
SQL;

			$cust_stmt = $this->db_connection->prepare($customer_req);
            $addr_stmt = $this->db_connection->prepare($address_req);
			$cust_stmt->execute($params);
            $addr_stmt->execute($params);
            
			try {
    			$cust_res = $cust_stmt->execute($params);
                $addr_res = $addr_stmt->execute($params);
			} catch (Exception $e) {
				return false;
			}
			if ( !($addr_res && $cust_res) )
				return false;

			if ($cust_stmt->rowCount() != 1)
				return null;

			$rows = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);
            $addr_list = $addr_stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $key => $row) {
				$rows[$key]['id'] = intval($rows[$key]['id']);
				$rows[$key]['total_space'] = intval($rows[$key]['total_space']);
				$rows[$key]['used_space'] = intval($rows[$key]['used_space']);
				$rows[$key]['max_monthly_space'] = intval($rows[$key]['max_monthly_space']);
                $rows[$key]['address'] = $addr_list;

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
				'name' => array('type' => 'string', 'length' => 255, 'required' => true),
				'total_space' => array('type' => 'int', 'required' => false)
			);

			// manage Id
			$params = array();
			$params[':id'] = intval($fields['id']);
            $address_list = $fields['address'];
            unset($fields['id']);
            
            $errors = '';
            
            if(count($address_list)) foreach($address_list as $addr){
                $success = $this->setAddress($addr);
                if( !($success === true) ){
                    $errors .=  $success.';';
                }
            }

			// validate or exit
			$to_be_updated = array();
			foreach($update_fields as $k => $type) {
				if (array_key_exists($k, $fields)) {
					array_push($to_be_updated, $k . '=:' . $k);
					$msg = $this->validate($fields[$k], $type);
					if ($msg !== true)
						$errors .= $k.$msg.';';
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
            if(empty($errors))
			    return true;
            return $errors;
		}


		public function getAddress($cid, $aid = null) {
			if ( !(isset($cid) && is_numeric($cid)) )
				return false;

			$params = array();
			$params[':cid'] = $cid;


			$statement = <<<SQL
			SELECT a.*
			FROM Address a
			LEFT JOIN addresscustomerrelation ac ON (a.id = ac.address)
			WHERE ac.customer = :cid
SQL;

			if ( ($aid !== null) && is_numeric($aid)) {
				$params[':aid'] = intval($aid);
				$statement .= ' AND a.id = :aid';
			}

			$stmt = $this->db_connection->prepare($statement);
			try {
				$res = $stmt->execute($params);
			} catch (Exception $e) {
				return false;
			}
			if (!$res)
				return false;

			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $key => $row) {
				$rows[$key]['id'] = intval($rows[$key]['id']);
			}
			//return $rows;
			if ( ($aid !== null) && is_numeric($aid))
				return $rows[0];
			return $rows;
		}

		public function setAddress($fields) {
			// define elements to be updated
			$update_fields = array(
				'title' => array('type' => 'text', 'required' => true),
				'street' => array('type' => 'text', 'required' => true),
				'zip_code' => array('type' => 'text', 'required' => true),
				'city' => array('type' => 'text', 'required' => true),
				'country' => array('type' => 'text', 'required' => true),
				'phone' => array('type' => 'text', 'required' => false),
				'iban' => array('type' => 'iban', 'required' => false),
				'vat_number' => array('type' => 'text', 'required' => false)
			);
			$params = array();

			if (intval($fields['id'])) // UPDATE
			{
				// Remove elements
				$params[':id'] = intval($fields['id']);
				$params[':customer'] = intval($fields['customer']);
				unset($fields['customer']);
				unset($fields['id']);

				// validate
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

				$statement = <<<SQL
				UPDATE Address
				LEFT JOIN addresscustomerrelation ON (id = address) SET

SQL;
				$statement .= join(', ', $to_be_updated);
				$statement .= ' WHERE id = :id AND customer = :customer';

				// Execution
				$stmt = $this->db_connection->prepare($statement);
				try {
					$stmt->execute($params);
				}
				catch (Exception $e) {
					return false;
				}
				return true;

			}
			else // INSERT
			{
				// check if all elements are presents
				$fields_inserted = array();
				$values_inserted = array();
				foreach($update_fields as $k => $type) {
					if ($type['required'] === true) {
						if (array_key_exists($k, $fields)) {
							$msg = $this->validate($fields[$k], $type);
							if ($msg !== true)
								return $k.$msg;
							array_push($fields_inserted, $k);
							array_push($values_inserted, ':'.$k);
							$params[':'.$k] = $fields[$k];
						} else {
							return $k.' is needed';
						}
					} else {
						if (array_key_exists($k, $fields)) {
							$msg = $this->validate($fields[$k], $type);
							if ($msg !== true)
								return $k.$msg;
							array_push($fields_inserted, $k);
							array_push($values_inserted, ':'.$k);
							$params[':'.$k] = $fields[$k];
						}
					}
				}

				// INSERT
				$statement1 = 'INSERT INTO Address (';
				$statement1 .= join(', ', $fields_inserted);
				$statement1 .= ') VALUES (';
				$statement1 .= join(', ', $values_inserted);
				$statement1 .= ')';

				$this->db_connection->beginTransaction();
				$stmt1 = $this->db_connection->prepare($statement1);
				try {
					$stmt1->execute($params);
					$aid = $this->db_connection->lastInsertId();
				} catch(PDOExecption $e) {
					$this->db_connection->rollback();
					return false;
				}
				$statement2 = 'INSERT INTO addresscustomerrelation (customer, address) VALUES (:customer, :address)';
				$stmt2 = $this->db_connection->prepare($statement2);
				$params2 = array(':customer' => $fields['customer'], ':address' => $aid);
				try {
					$stmt2->execute($params2);
					$this->db_connection->commit();
				} catch(PDOExecption $e) {
					$this->db_connection->rollback();
					return false;
				}
				return true;
			}
		}

		public function deleteAddress($id, $cid) {
			if ( !(isset($id) || !is_numeric($id)) )
				return false;
			if ( !(isset($id) || !is_numeric($cid)) )
				return false;

			$params = array();
			$params[':id'] = $id;
			$params[':cid'] = $cid;

			$statement = <<<SQL
			DELETE Address,addresscustomerrelation
			FROM Address
			LEFT JOIN addresscustomerrelation ON (id = address)
			WHERE
					id = :id
				AND
					customer = :cid
SQL;

			$stmt = $this->db_connection->prepare($statement);
			try {
				$res = $stmt->execute($params);
			} catch (Exception $e) {
				print $e;
				return false;
			}
			return true;
		}

		public function isConnected() {
			// return !$this->db_connection->connect_error;
			return true;
		}

		/*
		*		Validations fonctions
		*/

		/**
		 * \brief main validate method
		 */
		private function validate($el, $type) {
			if (($type['required'] === true) && (  (trim($el) === NULL )
                                                || (empty($el))))
				return ' need to be set 2';
			switch ($type['type']) {
				case 'bool':
					if(is_bool($el))
						return true;
					return ' is not boolean';
				case 'int':
					if (!is_numeric($el))
						return ' is not numeric';
					return true;
					break;
				case 'string':
					if (!is_string($el))
						return ' is not string';
					if (strlen($el) > $type['length'])
						return ' is too long';
					return true;
					break;
				case 'email':
					if (!is_string($el))
						return ' is not string';
					if (strlen($el) > $type['length'])
						return ' is too long';
					if (!filter_var($el, FILTER_VALIDATE_EMAIL))
						return ' is not email address';
					return true;
					break;
				case 'text':
					if (!is_string($el))
						return ' is not text';
					return true;
					break;
				case 'iban':
					if (!is_string($el))
						return ' is not IBAN';
					if (!$this->checkIBAN($el))
						return ' is not valid IBAN';
					return true;
					break;
			}
		}

		/**
		 * \brief iban validate method
		 */
		private function checkIBAN($iban)
		{
		    $iban = strtolower(str_replace(' ','',$iban));
		    $Countries = array('al'=>28,'ad'=>24,'at'=>20,'az'=>28,'bh'=>22,'be'=>16,'ba'=>20,'br'=>29,'bg'=>22,'cr'=>21,'hr'=>21,'cy'=>28,'cz'=>24,'dk'=>18,'do'=>28,'ee'=>20,'fo'=>18,'fi'=>18,'fr'=>27,'ge'=>22,'de'=>22,'gi'=>23,'gr'=>27,'gl'=>18,'gt'=>28,'hu'=>28,'is'=>26,'ie'=>22,'il'=>23,'it'=>27,'jo'=>30,'kz'=>20,'kw'=>30,'lv'=>21,'lb'=>28,'li'=>21,'lt'=>20,'lu'=>20,'mk'=>19,'mt'=>31,'mr'=>27,'mu'=>30,'mc'=>27,'md'=>24,'me'=>22,'nl'=>18,'no'=>15,'pk'=>24,'ps'=>29,'pl'=>28,'pt'=>25,'qa'=>29,'ro'=>24,'sm'=>27,'sa'=>24,'rs'=>22,'sk'=>24,'si'=>19,'es'=>24,'se'=>24,'ch'=>21,'tn'=>24,'tr'=>26,'ae'=>23,'gb'=>22,'vg'=>24);
		    $Chars = array('a'=>10,'b'=>11,'c'=>12,'d'=>13,'e'=>14,'f'=>15,'g'=>16,'h'=>17,'i'=>18,'j'=>19,'k'=>20,'l'=>21,'m'=>22,'n'=>23,'o'=>24,'p'=>25,'q'=>26,'r'=>27,'s'=>28,'t'=>29,'u'=>30,'v'=>31,'w'=>32,'x'=>33,'y'=>34,'z'=>35);

		    if(strlen($iban) == $Countries[substr($iban,0,2)]){

		        $MovedChar = substr($iban, 4).substr($iban,0,4);
		        $MovedCharArray = str_split($MovedChar);
		        $NewString = "";

		        foreach($MovedCharArray AS $key => $value) {
		            if(!is_numeric($MovedCharArray[$key])) {
		                $MovedCharArray[$key] = $Chars[$MovedCharArray[$key]];
		            }
		            $NewString .= $MovedCharArray[$key];
		        }

		        if(bcmod($NewString, '97') == 1) {
		        	return true;
		        } else {
		            return false;
		        }
		     } else
		        return false;
		}


		/*
		*		Presentations fonctions
		*/

		/**
		 * \brief Users method
		 */
		private function reformUsers($rows, $keep_credentials = false) {
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
				if (isset($rows[$key]['chroot']))
					$rows[$key]['chroot'] = boolval($rows[$key]['chroot']);
				if (isset($rows[$key]['f_access']))
					$rows[$key]['ftp_read'] = $rows[$key]['ftp_write'] = 0;
				if (($rows[$key]['f_access'] == 'read') || ($rows[$key]['f_access'] == 'read_write'))
					$rows[$key]['ftp_read'] = 1;
				if (($rows[$key]['f_access'] == 'write') || ($rows[$key]['f_access'] == 'read_write'))
					$rows[$key]['ftp_write'] = 1;

				// Special PATH
				if ((isset($rows[$key]['path'])) && (isset($rows[$key]['homedirectory'])))
					$rows[$key]['directory'] = str_replace($rows[$key]['path'], '', $rows[$key]['homedirectory']);

				// Remove unnecessary elements
				if(!$keep_credentials) {
					unset($rows[$key]['salt']);
					unset($rows[$key]['password']);
				}
				unset($rows[$key]['f_access']);
				unset($rows[$key]['path']);
				unset($rows[$key]['homedirectory']);
				unset($rows[$key]['access']);
			}
			return $rows;
		}
	}
?>
