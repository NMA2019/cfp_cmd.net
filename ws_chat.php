<?php
require __DIR__ . '/../config/db_connection.php';
require __DIR__ . '/../config/auth.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        parse_str($conn->httpRequest->getUri()->getQuery(), $query);
        $userId = $query['user_id'] ?? null;

        if ($userId && is_numeric($userId)) {
            $this->clients->attach($conn);
            $this->userConnections[$userId] = $conn;
            
            $this->broadcastUserStatus($userId, true);
            echo "Nouvelle connexion pour l'utilisateur $userId\n";
        } else {
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['action'])) return;

        switch ($data['action']) {
            case 'send_message':
                $this->handleSendMessage($data['data']);
                break;
                
            case 'message_read':
                $this->handleMessageRead($data['message_id'], $data['recipient_id']);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $userId = array_search($conn, $this->userConnections, true);
        
        if ($userId !== false) {
            unset($this->userConnections[$userId]);
            $this->broadcastUserStatus($userId, false);
        }
        
        $this->clients->detach($conn);
        echo "Connexion fermée\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Erreur: {$e->getMessage()}\n";
        $conn->close();
    }
    
    protected function handleSendMessage($messageData) {
        global $pdo;
        
        try {
            if ($messageData['type'] === 'private') {
                $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, recipient_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$messageData['sender_id'], $messageData['id'], $messageData['content']]);
                
                if (isset($this->userConnections[$messageData['id']])) {
                    $this->userConnections[$messageData['id']]->send(json_encode([
                        'action' => 'new_message',
                        'message' => $messageData
                    ]));
                }
                
                if (isset($this->userConnections[$messageData['sender_id']])) {
                    $this->userConnections[$messageData['sender_id']]->send(json_encode([
                        'action' => 'message_sent',
                        'message' => $messageData
                    ]));
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur DB chat: " . $e->getMessage());
        }
    }
    
    protected function handleMessageRead($messageId, $recipientId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("UPDATE chat_messages SET is_read = TRUE WHERE id = ? AND recipient_id = ?");
            $stmt->execute([$messageId, $recipientId]);
            
            $stmt = $pdo->prepare("SELECT sender_id FROM chat_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $senderId = $stmt->fetchColumn();
            
            if ($senderId && isset($this->userConnections[$senderId])) {
                $this->userConnections[$senderId]->send(json_encode([
                    'action' => 'message_read',
                    'message_id' => $messageId
                ]));
            }
        } catch (PDOException $e) {
            error_log("Erreur DB message lu: " . $e->getMessage());
        }
    }
    
    protected function broadcastUserStatus($userId, $isOnline) {
        $message = json_encode([
            'action' => $isOnline ? 'user_online' : 'user_offline',
            'user_id' => $userId
        ]);
        
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
}

// Création du serveur - À PLACER EN DEHORS DE LA CLASSE
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080, // Essayez un autre port si ça ne marche pas (ex: 8081)
    'localhost' // Spécifiez explicitement l'adresse IP
);

$server->run();