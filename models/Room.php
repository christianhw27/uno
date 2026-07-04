<?php

require_once __DIR__ . '/Game.php';

class Room {
    public $room_id;
    public $creator_id;
    public $game;

    public function __construct() {
        $this->game = new Game();
    }

    /**
     * Load a room from the JSON file.
     * 
     * @param string $roomId
     * @return Room|null
     */
    public static function load($roomId) {
        $roomId = cleanRoomId($roomId);
        $filePath = __DIR__ . "/../storage/rooms/{$roomId}.json";
        
        if (!file_exists($filePath)) {
            return null;
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);
        if (!$data) {
            return null;
        }

        $room = new self();
        $room->room_id = $data['room_id'];
        $room->creator_id = $data['creator_id'];
        
        if (isset($data['game'])) {
            $room->game = Game::fromArray($data['game']);
        }

        return $room;
    }

    /**
     * Save the room status to its JSON file.
     */
    public function save() {
        $dirPath = __DIR__ . "/../storage/rooms";
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0777, true);
        }
        
        $roomId = cleanRoomId($this->room_id);
        $filePath = "{$dirPath}/{$roomId}.json";
        
        file_put_contents($filePath, json_encode([
            'room_id' => $this->room_id,
            'creator_id' => $this->creator_id,
            'game' => $this->game
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Initialize a new room.
     */
    public static function create($roomId, $creatorName, $creatorId) {
        $room = new self();
        $room->room_id = $roomId;
        $room->creator_id = $creatorId;
        
        // Add the creator as the first player
        $creator = new Player($creatorId, $creatorName);
        $room->game->players[] = $creator;
        $room->game->addLog("{$creatorName} created room {$roomId}.");
        $room->save();

        return $room;
    }

    /**
     * Add a player to the room.
     */
    public function addPlayer($playerName, $playerId) {
        if ($this->game->status !== 'lobby') {
            return false; // Cannot join active game
        }

        // Check if player already in room
        foreach ($this->game->players as $player) {
            if ($player->id === $playerId) {
                return true; // Already joined
            }
        }

        if (count($this->game->players) >= 10) {
            return false; // Limit to 10 players
        }

        $player = new Player($playerId, $playerName);
        $this->game->players[] = $player;
        $this->game->addLog("{$playerName} joined the room.");
        $this->save();

        return true;
    }
}

/**
 * Helper function to sanitize Room ID to prevent directory traversal.
 */
function cleanRoomId($roomId) {
    return preg_replace('/[^a-zA-Z0-9]/', '', $roomId);
}
