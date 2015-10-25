from common_test import CommonTest
import json

class AuthTest(CommonTest):
    def test_01_get(self):
        connection = self.newConnection()
        connection.request('GET', self.path + 'auth/')
        response = connection.getresponse()
        message = json.loads(response.read().decode('utf-8'))
        connection.close()
        self.assertEqual(response.status, 401)
        self.assertIn('message', message)

    def test_02_auth_without_data(self):
        connection = self.newConnection()
        connection.request('POST', self.path + 'auth/')
        response = connection.getresponse()
        message = json.loads(response.read().decode('utf-8'))
        connection.close()
        self.assertEqual(response.status, 415)
        self.assertIn('message', message)

    def test_02_auth_with_random_params(self):
        body = json.dumps({"foo": "bar"})
        headers = {"Content-type": "application/json"}
        connection = self.newConnection()
        connection.request('POST', self.path + 'auth/', body, headers)
        response = connection.getresponse()
        message = json.loads(response.read().decode('utf-8'))
        connection.close()
        self.assertEqual(response.status, 400)
        self.assertIn('message', message)

    def test_03_auth_bad_credential(self):
        body = json.dumps({"login": "foo", "password": "bar"})
        headers = {"Content-type": "application/json"}
        connection = self.newConnection()
        connection.request('POST', self.path + 'auth/', body, headers)
        response = connection.getresponse()
        message = json.loads(response.read().decode('utf-8'))
        connection.close()
        self.assertEqual(response.status, 400)
        self.assertIn('message', message)

