<?php
namespace App\Http\Controllers\Websocket;

use App\Http\Controllers\Controller;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class WebSocketController extends Controller implements MessageComponentInterface
{
    protected $connections = [];

    public function __construct()
    {
        $this->connections = new \SplObjectStorage;
    }

    /**
     * Sending a signal to all about a new online user
     */
    private function signal()
    {
        foreach ($this->connections as $client) {
            $client->send(json_encode([
                'type' => 'newUser',
                'users' => [],
            ]));
        }
    }

    /**
     * Get all online users
     */
    private function onlineUsers($currentUser)
    {
        $users = [];
        foreach ($this->connections as $client) {
            if ($currentUser !== $client->resourceId) {
                $users[] = [
                    'uid' => $client->resourceId,
                ];
            }
        }
        $size = sizeof($users);
        echo "Sending list of users to {$currentUser} and list of {$size} users\n";
        $userData = json_encode([
            'type' => 'userlist',
            'users' => $users,
        ]);

        // Send to the requester
        foreach ($this->connections as $client) {
            if ($client->resourceId === $currentUser) {
                $client->send($userData);
            }
        }

    }

    /**
     * Send message to a specific user
     */
    private function uniqueUser($uid, $type, $from, $msg = '')
    {
        foreach ($this->connections as $client) {
            if ($from != $client && $client->resourceId === $uid) {
                echo $type . " user {$uid}...\n";
                $client->send($msg);
            }
        }
    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->connections->attach($conn);

        echo "New connection: ({$conn->resourceId})\n";
        $conn->send(json_encode([
            'id' => $conn->resourceId,
            'type' => 'id',
        ]));

        // Send signal for new online user
        $this->signal();
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $conn The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $decode = json_decode($msg);
        $type = $decode->type;

        if ($type == 'signal') {
            $userId = (int) $decode->msg->user;
            $this->onlineUsers($userId);
        } else {
            $this->uniqueUser($decode->msg->to, $type, $from, $msg);
        }

    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->connections->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
        // Send signal when user has been disconnected
        $this->signal();

    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        // $userId = $this->connections[$conn->resourceId]['user_id'];
        echo "An error has occurred with user {$e->getMessage()}\n";
        // unset($this->connections[$conn->resourceId]);
        $conn->close();

        // Send signal when user has an error
        $this->signal();
    }

}
