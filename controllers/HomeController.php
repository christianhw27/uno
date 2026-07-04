<?php

class HomeController {
    /**
     * Show the main menu / join page.
     */
    public function index() {
        $playerName = $_SESSION['player_name'] ?? '';
        $error = $_SESSION['error'] ?? '';
        unset($_SESSION['error']); // Clear after showing

        require_once __DIR__ . '/../views/home.php';
    }

    /**
     * Handle creating a room.
     */
    public function createRoom() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }

        $playerName = trim($_POST['player_name'] ?? '');
        if (empty($playerName)) {
            $_SESSION['error'] = 'Player name is required.';
            header('Location: index.php');
            exit;
        }

        $_SESSION['player_name'] = $playerName;
        if (empty($_SESSION['player_id'])) {
            $_SESSION['player_id'] = uniqid('p_');
        }

        // Generate a random 5-character Room ID
        $roomId = $this->generateRoomId();

        // Create the room
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::create($roomId, $playerName, $_SESSION['player_id']);

        header("Location: index.php?action=game&room_id={$roomId}");
        exit;
    }

    /**
     * Handle joining a room.
     */
    public function joinRoom() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }

        $playerName = trim($_POST['player_name'] ?? '');
        $roomId = strtoupper(trim($_POST['room_id'] ?? ''));

        if (empty($playerName) || empty($roomId)) {
            $_SESSION['error'] = 'Player name and Room ID are required.';
            header('Location: index.php');
            exit;
        }

        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);
        if (!$room) {
            $_SESSION['error'] = 'Room ' . htmlspecialchars($roomId) . ' not found.';
            header('Location: index.php');
            exit;
        }

        if ($room->game->status !== 'lobby') {
            $_SESSION['error'] = 'Game already started in Room ' . htmlspecialchars($roomId) . '.';
            header('Location: index.php');
            exit;
        }

        $_SESSION['player_name'] = $playerName;
        if (empty($_SESSION['player_id'])) {
            $_SESSION['player_id'] = uniqid('p_');
        }

        $joined = $room->addPlayer($playerName, $_SESSION['player_id']);
        if (!$joined) {
            $_SESSION['error'] = 'Failed to join room. Room might be full (max 10 players).';
            header('Location: index.php');
            exit;
        }

        header("Location: index.php?action=game&room_id={$roomId}");
        exit;
    }

    /**
     * Helper to generate alphanumeric room IDs.
     */
    private function generateRoomId() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Confusable chars omitted
        $roomId = '';
        for ($i = 0; $i < 5; $i++) {
            $roomId .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $roomId;
    }
}
