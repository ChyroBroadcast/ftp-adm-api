import json, unittest
import http.client

class CommonTest(unittest.TestCase):
    scheme = 'http'
    host = 'taiko'
    path = '/storiqone-backend/api/v1/'
    users = {
        'qlowd': {
            'login': 'foo',
            'password': 'bar'
        }
    }
    parsed = False

    def newConnection(self):
        if (self.scheme == 'http'):
            return http.client.HTTPConnection(self.host)
        else:
            return http.client.HTTPSConnection(self.host)

    def newLoggedConnection(self, user):
        if (user not in self.users):
            self.fail("user < %s > not found is config" % (user))
        conn = self.newConnection()
        body = json.dumps({'login': self.users[user]['login'], 'password': self.users[user]['password']})
        headers = {"Content-type": "application/json"}
        conn.request('POST', self.path + 'auth/', body, headers)
        res = conn.getresponse()
        message = json.loads(res.read().decode("utf-8"))
        conn.close()
        self.assertEqual(res.status, 201)
        conn = self.newConnection()
        return conn, {'Cookie': res.getheader('Set-Cookie').split(';')[0]}, message

    def setUp(self):
        if (self.parsed):
            return
        f = open('config.json', 'r', encoding='utf-8')
        config = json.load(f)
        f.close()
        self.parsed = True
        if ('scheme' in config):
            self.scheme = config['scheme']
        if ('host' in config):
            self.host = config['host']
        if ('path' in config):
            self.path = config['path']
        if ('users' in config):
            self.users = config['users']

