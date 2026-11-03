"""Send a real FastCGI request without an HTTP proxy dependency."""
import socket
import struct
import sys

port, filename = int(sys.argv[1]), sys.argv[2]

def record(kind, content):
    return struct.pack('!BBHHBB', 1, kind, 1, len(content), 0, 0) + content

def length(n):
    return bytes([n]) if n < 128 else struct.pack('!I', n | 0x80000000)

params = {'SCRIPT_FILENAME': filename, 'SCRIPT_NAME': '/index.php', 'REQUEST_METHOD': 'GET',
          'REQUEST_URI': '/index.php', 'QUERY_STRING': '', 'SERVER_PROTOCOL': 'HTTP/1.1',
          'SERVER_NAME': 'localhost', 'SERVER_PORT': '80', 'REMOTE_ADDR': '127.0.0.1',
          'HTTP_TRACEPARENT': '00-12345678901234567890123456789012-1234567890123456-01'}
body = b''
for key, value in params.items():
    key, value = key.encode(), value.encode()
    body += length(len(key)) + length(len(value)) + key + value
with socket.create_connection(('127.0.0.1', port), timeout=30) as connection:
    connection.sendall(record(1, struct.pack('!HB5x', 1, 0)) + record(4, body) + record(4, b'') + record(5, b''))
    def read(n):
        result = b''
        while len(result) < n:
            data = connection.recv(n - len(result))
            if not data:
                raise RuntimeError('FastCGI closed before end record')
            result += data
        return result
    output, errors = b'', b''
    while True:
        version, kind, request, size, padding, reserved = struct.unpack('!BBHHBB', read(8))
        data = read(size)
        read(padding)
        if kind == 6:
            output += data
        elif kind == 7:
            errors += data
        elif kind == 3:
            assert struct.unpack('!I', data[:4])[0] == 0, data
            break
assert b'SERVER_OK' in output and b'Warning' not in output and b'Fatal' not in output, output.decode()
assert not errors, errors.decode()
print('FPM startup, application result and native PDO hooks passed.')
