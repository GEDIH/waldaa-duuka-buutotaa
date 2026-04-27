<?php
/**
 * WebSocket Server Implementation
 * Handles real-time communication for the admin dashboard
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class WebSocketServer {
    private $host;
    private $port;
    private $socket;
    private $clients = [];
    private $subscriptions = [];
    private $messageQueue = [];
    private $running = false;
    
    public function __construct($host = '0.0.0.0', $port = 8080) {
        $this->host = $host;
        $this->port = $port;
    }
    
    /**
     * Start the WebSocket server
     */
    public function start() {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if (!$this->socket) {
            throw new Exception('Failed to create socket: ' . socket_strerror(socket_last_error()));
        }
        
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new Exception('Failed to bind socket: ' . socket_strerror(socket_last_error()));
        }
        
        if (!socket_listen($this->socket, 20)) {
            throw new Exception('Failed to listen on socket: ' . socket_strerror(socket_last_error()));
        }
        
        $this->running = true;
        echo "WebSocket server started on {$this->host}:{$this->port}\n";
        
        $this->run();
    }
    
    /**
     * Main server loop
     */
    private function run() {
        while ($this->running) {
            $read = array_merge([$this->socket], array_keys($this->clients));
            $write = null;
            $except = null;
            
            if (socket_select($read, $write, $except, 0, 100000) < 1) {
                continue;
            }
            
            // Handle new connections
            if (in_array($this->socket, $read)) {
                $this->handleNewConnection();
                $key = array_search($this->socket, $read);
                unset($read[$key]);
            }
            
            // Handle client messages
            foreach ($read as $client) {
                $this->handleClientMessage($client);
            }
            
            // Process message queue
            $this->processMessageQueue();
        }
    }
    
    /**
     * Handle new client connection
     */
    private function handleNewConnection() {
        $client = socket_accept($this->socket);
        
        if (!$client) {
            return;
        }
        
        $request = socket_read($client, 1024);
        $headers = $this->parseHeaders($request);
        
        if (!$this->performHandshake($client, $headers)) {
            socket_close($client);
            return;
        }
        
        $clientId = uniqid();
        $this->clients[$client] = [
            'id' => $clientId,
            'socket' => $client,
            'authenticated' => false,
            'user_id' => null,
            'subscriptions' => [],
            'last_ping' => time()
        ];
        
        echo "New client connected: {$clientId}\n";
        
        // Send welcome message
        $this->sendToClient($client, [
            'type' => 'welcome',
            'client_id' => $clientId,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Parse HTTP headers from request
     */
    private function parseHeaders($request) {
        $headers = [];
        $lines = explode("\r\n", $request);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return $headers;
    }
    
    /**
     * Perform WebSocket handshake
     */
    private function performHandshake($client, $headers) {
        if (!isset($headers['Sec-WebSocket-Key'])) {
            return false;
        }
        
        $key = $headers['Sec-WebSocket-Key'];
        $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";
        
        return socket_write($client, $response) !== false;
    }
    
    /**
     * Handle client message
     */
    private function handleClientMessage($client) {
        $data = socket_read($client, 1024);
        
        if ($data === false || $data === '') {
            $this->disconnectClient($client);
            return;
        }
        
        $message = $this->decodeFrame($data);
        
        if ($message === false) {
            return;
        }
        
        $decoded = json_decode($message, true);
        
        if (!$decoded) {
            return;
        }
        
        $this->processMessage($client, $decoded);
    }
    
    /**
     * Process incoming message
     */
    private function processMessage($client, $message) {
        $clientData = $this->clients[$client];
        
        switch ($message['type']) {
            case 'authenticate':
                $this->handleAuthentication($client, $message);
                break;
                
            case 'subscribe':
                $this->handleSubscription($client, $message);
                break;
                
            case 'unsubscribe':
                $this->handleUnsubscription($client, $message);
                break;
                
            case 'ping':
                $this->handlePing($client, $message);
                break;
                
            case 'heartbeat_response':
                $this->clients[$client]['last_ping'] = time();
                break;
                
            case 'user_active':
            case 'user_inactive':
                $this->broadcastUserActivity($client, $message['type']);
                break;
                
            default:
                // Handle cusTesfaye message types
                $this->handleCusTesfayeMessage($client, $message);
        }
    }
    
    /**
     * Handle client authentication
     */
    private function handleAuthentication($client, $message) {
        if (!isset($message['token'])) {
            $this->sendToClient($client, [
                'type' => 'auth_error',
                'message' => 'Authentication token required'
            ]);
            return;
        }
        
        // Validate JWT token
        $authMiddleware = new AuthMiddleware();
        $userData = $authMiddleware->validateToken($message['token']);
        
        if (!$userData) {
            $this->sendToClient($client, [
                'type' => 'auth_error',
                'message' => 'Invalid authentication token'
            ]);
            return;
        }
        
        $this->clients[$client]['authenticated'] = true;
        $this->clients[$client]['user_id'] = $userData['user_id'];
        $this->clients[$client]['role'] = $userData['role'];
        
        $this->sendToClient($client, [
            'type' => 'authenticated',
            'user_id' => $userData['user_id'],
            'role' => $userData['role']
        ]);
        
        echo "Client authenticated: {$userData['user_id']}\n";
    }
    
    /**
     * Handle channel subscription
     */
    private function handleSubscription($client, $message) {
        if (!$this->clients[$client]['authenticated']) {
            $this->sendToClient($client, [
                'type' => 'error',
                'message' => 'Authentication required for subscriptions'
            ]);
            return;
        }
        
        $channel = $message['channel'];
        
        if (!isset($this->subscriptions[$channel])) {
            $this->subscriptions[$channel] = [];
        }
        
        $this->subscriptions[$channel][] = $client;
        $this->clients[$client]['subscriptions'][] = $channel;
        
        $this->sendToClient($client, [
            'type' => 'subscribed',
            'channel' => $channel
        ]);
        
        echo "Client subscribed to channel: {$channel}\n";
    }
    
    /**
     * Handle channel unsubscription
     */
    private function handleUnsubscription($client, $message) {
        $channel = $message['channel'];
        
        if (isset($this->subscriptions[$channel])) {
            $key = array_search($client, $this->subscriptions[$channel]);
            if ($key !== false) {
                unset($this->subscriptions[$channel][$key]);
            }
        }
        
        $clientSubscriptions = &$this->clients[$client]['subscriptions'];
        $key = array_search($channel, $clientSubscriptions);
        if ($key !== false) {
            unset($clientSubscriptions[$key]);
        }
        
        $this->sendToClient($client, [
            'type' => 'unsubscribed',
            'channel' => $channel
        ]);
    }
    
    /**
     * Handle ping message
     */
    private function handlePing($client, $message) {
        $this->sendToClient($client, [
            'type' => 'pong',
            'timestamp' => $message['timestamp']
        ]);
        
        $this->clients[$client]['last_ping'] = time();
    }
    
    /**
     * Handle cusTesfaye message types
     */
    private function handleCusTesfayeMessage($client, $message) {
        // Broadcast to subscribed channels if specified
        if (isset($message['channel'])) {
            $this->broadcastToChannel($message['channel'], $message);
        }
    }
    
    /**
     * Broadcast user activity
     */
    private function broadcastUserActivity($client, $activity) {
        $clientData = $this->clients[$client];
        
        if (!$clientData['authenticated']) {
            return;
        }
        
        $message = [
            'type' => 'user_activity',
            'payload' => [
                'user_id' => $clientData['user_id'],
                'activity' => $activity,
                'timestamp' => time()
            ]
        ];
        
        $this->broadcastToChannel('user_activity', $message);
    }
    
    /**
     * Send message to specific client
     */
    private function sendToClient($client, $message) {
        $encoded = $this->encodeFrame(json_encode($message));
        socket_write($client, $encoded);
    }
    
    /**
     * Broadcast message to all clients
     */
    public function broadcast($message) {
        foreach ($this->clients as $client => $data) {
            if ($data['authenticated']) {
                $this->sendToClient($client, $message);
            }
        }
    }
    
    /**
     * Broadcast message to channel subscribers
     */
    public function broadcastToChannel($channel, $message) {
        if (!isset($this->subscriptions[$channel])) {
            return;
        }
        
        foreach ($this->subscriptions[$channel] as $client) {
            if (isset($this->clients[$client])) {
                $this->sendToClient($client, $message);
            }
        }
    }
    
    /**
     * Disconnect client
     */
    private function disconnectClient($client) {
        if (isset($this->clients[$client])) {
            $clientData = $this->clients[$client];
            
            // Remove from subscriptions
            foreach ($clientData['subscriptions'] as $channel) {
                if (isset($this->subscriptions[$channel])) {
                    $key = array_search($client, $this->subscriptions[$channel]);
                    if ($key !== false) {
                        unset($this->subscriptions[$channel][$key]);
                    }
                }
            }
            
            unset($this->clients[$client]);
            echo "Client disconnected: {$clientData['id']}\n";
        }
        
        socket_close($client);
    }
    
    /**
     * Process message queue
     */
    private function processMessageQueue() {
        while (!empty($this->messageQueue)) {
            $item = array_shift($this->messageQueue);
            
            if ($item['type'] === 'broadcast') {
                $this->broadcast($item['message']);
            } elseif ($item['type'] === 'channel') {
                $this->broadcastToChannel($item['channel'], $item['message']);
            }
        }
    }
    
    /**
     * Queue message for broadcasting
     */
    public function queueMessage($type, $message, $channel = null) {
        $this->messageQueue[] = [
            'type' => $type,
            'message' => $message,
            'channel' => $channel
        ];
    }
    
    /**
     * Decode WebSocket frame
     */
    private function decodeFrame($data) {
        $length = ord($data[1]) & 127;
        
        if ($length == 126) {
            $masks = substr($data, 4, 4);
            $data = substr($data, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $data = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $data = substr($data, 6);
        }
        
        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        
        return $text;
    }
    
    /**
     * Encode WebSocket frame
     */
    private function encodeFrame($message) {
        $length = strlen($message);
        
        if ($length < 126) {
            return chr(129) . chr($length) . $message;
        } elseif ($length < 65536) {
            return chr(129) . chr(126) . pack('n', $length) . $message;
        } else {
            return chr(129) . chr(127) . pack('N', 0) . pack('N', $length) . $message;
        }
    }
    
    /**
     * Stop the server
     */
    public function stop() {
        $this->running = false;
        
        foreach ($this->clients as $client => $data) {
            socket_close($client);
        }
        
        socket_close($this->socket);
        echo "WebSocket server stopped\n";
    }
}

// CLI script to start the server
if (php_sapi_name() === 'cli') {
    $server = new WebSocketServer();
    
    // Handle shutdown signals (only on Unix-like systems)
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() use ($server) {
            $server->stop();
            exit(0);
        });
        
        pcntl_signal(SIGINT, function() use ($server) {
            $server->stop();
            exit(0);
        });
    }
    
    try {
        $server->start();
    } catch (Exception $e) {
        echo "Server error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>