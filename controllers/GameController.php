<?php

class GameController {
    /**
     * Display the main game board page.
     */
    public function show() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $_SESSION['error'] = 'Room not found.';
            header('Location: index.php');
            exit;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        $playerName = $_SESSION['player_name'] ?? '';

        if (empty($playerId) || empty($playerName)) {
            $_SESSION['error'] = 'Please enter your name first.';
            header('Location: index.php');
            exit;
        }

        // Verify if player is in the room. If not, try to join.
        $inRoom = false;
        foreach ($room->game->players as $player) {
            if ($player->id === $playerId) {
                $inRoom = true;
                break;
            }
        }

        if (!$inRoom) {
            if ($room->game->status !== 'lobby') {
                $_SESSION['error'] = 'Cannot join. Game is already in progress.';
                header('Location: index.php');
                exit;
            }
            $joined = $room->addPlayer($playerName, $playerId);
            if (!$joined) {
                $_SESSION['error'] = 'Room is full.';
                header('Location: index.php');
                exit;
            }
        }

        require_once __DIR__ . '/../views/game.php';
    }

    /**
     * AJAX endpoint: Get game status and run bot tasks.
     */
    public function status() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $game = $room->game;
        $updated = false;

        // Run bot actions if it's currently a bot's turn
        if ($game->status === 'playing') {
            $activePlayer = $game->getActivePlayer();
            if ($activePlayer->is_bot) {
                $botPlayed = $game->runBotTurn();
                if ($botPlayed) {
                    $updated = true;
                }
            }

            // Run bot challenge logic (bots challenge human/bot if they forgot UNO)
            $challenged = $game->runBotChallenges();
            if ($challenged) {
                $updated = true;
            }
        }

        if ($updated) {
            $room->save();
        }

        // Prepare response with anti-cheat filters (hiding other players' card details)
        $playerId = $_SESSION['player_id'] ?? '';
        $playersData = [];

        foreach ($game->players as $player) {
            $isMe = ($player->id === $playerId);
            $cards = [];

            if ($isMe) {
                $cards = $player->cards;
            } else {
                // Return dummy cards just to represent length
                $cardsCount = count($player->cards);
                for ($i = 0; $i < $cardsCount; $i++) {
                    $cards[] = ['id' => 'dummy_' . $i];
                }
            }

            $playersData[] = [
                'id' => $player->id,
                'name' => $player->name,
                'is_bot' => $player->is_bot,
                'card_count' => count($player->cards),
                'cards' => $cards,
                'called_uno' => $player->called_uno
            ];
        }

        // Get last 5 cards from discard pile (so combo plays are visible)
        $discardRecent = [];
        if (!empty($game->discard_pile)) {
            $discardRecent = array_values(array_slice($game->discard_pile, -5));
        }
        $topCard = !empty($game->discard_pile) ? end($game->discard_pile) : null;

        // Cards from the most recent play action (single or combo)
        $lastPlayCards = [];
        foreach ($game->last_play_cards as $card) {
            $lastPlayCards[] = $card;
        }

        $response = [
            'room_id' => $room->room_id,
            'creator_id' => $room->creator_id,
            'status' => $game->status,
            'winner_id' => $game->winner_id,
            'direction' => $game->direction,
            'current_turn' => $game->current_turn,
            'selected_color' => $game->selected_color,
            'has_drawn' => $game->has_drawn_this_turn,
            'accumulated_draw_penalty' => $game->accumulated_draw_penalty,
            'deck_count' => count($game->deck),
            'discard_card' => $topCard,
            'discard_recent' => $discardRecent,
            'last_play_cards' => $lastPlayCards,
            'initial_card_count' => $game->initial_card_count,
            'players' => $playersData,
            'logs' => $game->logs,
            'player_id' => $playerId
        ];

        $this->jsonResponse(true, 'Status retrieved.', $response);
    }

    /**
     * AJAX endpoint: Add a bot to the lobby.
     */
    public function addBot() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        if ($room->game->status !== 'lobby') {
            $this->jsonResponse(false, 'Cannot add bots. Game already in progress.');
            return;
        }

        if (count($room->game->players) >= 10) {
            $this->jsonResponse(false, 'Lobby is full.');
            return;
        }

        // Generate bot names
        $botNames = ['🤖 Bot Alpha', '🤖 Bot Beta', '🤖 Bot Gamma', '🤖 Bot Delta', '🤖 Bot Epsilon', '🤖 Bot Zeta'];
        $existingBotCount = 0;
        foreach ($room->game->players as $player) {
            if ($player->is_bot) $existingBotCount++;
        }

        $botName = $botNames[$existingBotCount % count($botNames)];
        // Add random number to bot name if we exceed count
        if ($existingBotCount >= count($botNames)) {
            $botName .= ' ' . ($existingBotCount + 1);
        }

        $botId = uniqid('bot_');
        $bot = new Player($botId, $botName, true);
        
        $room->game->players[] = $bot;
        $room->game->addLog("{$botName} was added to the lobby.");
        $room->save();

        $this->jsonResponse(true, 'Bot added successfully.');
    }

    /**
     * AJAX endpoint: Remove a bot from the lobby.
     */
    public function removeBot() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        if ($room->game->status !== 'lobby') {
            $this->jsonResponse(false, 'Cannot remove players. Game already in progress.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        if ($room->creator_id !== $playerId) {
            $this->jsonResponse(false, 'Only the room creator can remove bots.');
            return;
        }

        $targetId = $_POST['target_id'] ?? '';
        if (empty($targetId)) {
            $this->jsonResponse(false, 'No player ID specified.');
            return;
        }

        $removed = false;
        foreach ($room->game->players as $i => $player) {
            if ($player->id === $targetId && $player->is_bot) {
                $botName = $player->name;
                array_splice($room->game->players, $i, 1);
                $room->game->addLog("{$botName} was removed from the lobby.");
                $removed = true;
                break;
            }
        }

        if (!$removed) {
            $this->jsonResponse(false, 'Bot not found or target is not a bot.');
            return;
        }

        $room->save();
        $this->jsonResponse(true, 'Bot removed successfully.');
    }

    /**
     * AJAX endpoint: Start game.
     */
    public function startGame() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        if ($room->creator_id !== $playerId) {
            $this->jsonResponse(false, 'Only the room creator can start the game.');
            return;
        }

        $cardCount = intval($_POST['card_count'] ?? 7);
        if ($cardCount < 1) $cardCount = 1;
        if ($cardCount > 15) $cardCount = 15;
        $room->game->initial_card_count = $cardCount;

        $started = $room->game->start();
        if ($started) {
            $room->save();
            $this->jsonResponse(true, 'Game started.');
        } else {
            $this->jsonResponse(false, 'Failed to start game. Need at least 2 players.');
        }
    }

    /**
     * AJAX endpoint: Play a card (supports combo play with multiple card_ids).
     */
    public function playCard() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        $cardIdsRaw = $_POST['card_ids'] ?? ($_POST['card_id'] ?? '');
        $chosenColor = $_POST['chosen_color'] ?? null;

        // Support both single card_id and comma-separated card_ids
        $cardIds = array_filter(array_map('trim', explode(',', $cardIdsRaw)));

        if (empty($cardIds)) {
            $this->jsonResponse(false, 'No card IDs provided.');
            return;
        }

        $success = $room->game->playCombo($playerId, $cardIds, $chosenColor);
        if ($success) {
            $room->save();
            $this->jsonResponse(true, 'Card(s) played successfully.');
        } else {
            $this->jsonResponse(false, 'Invalid move. Check top card, selected color, or turn order.');
        }
    }

    /**
     * AJAX endpoint: Draw card.
     */
    public function drawCard() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        $card = $room->game->drawCard($playerId);

        if ($card) {
            $room->save();
            $this->jsonResponse(true, 'Card drawn successfully.', ['card' => $card]);
        } else {
            $this->jsonResponse(false, 'Failed to draw card. Ensure it is your turn and you have not drawn yet.');
        }
    }

    /**
     * AJAX endpoint: Pass turn.
     */
    public function passTurn() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        $success = $room->game->passTurn($playerId);

        if ($success) {
            $room->save();
            $this->jsonResponse(true, 'Turn passed.');
        } else {
            $this->jsonResponse(false, 'Cannot pass. You must draw a card first.');
        }
    }

    /**
     * AJAX endpoint: Declare UNO.
     */
    public function declareUno() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        $success = $room->game->declareUno($playerId);

        if ($success) {
            $room->save();
            $this->jsonResponse(true, 'UNO declared!');
        } else {
            $this->jsonResponse(false, 'Failed to declare UNO.');
        }
    }

    /**
     * AJAX endpoint: Challenge player.
     */
    public function challenge() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        $targetId = $_POST['target_id'] ?? '';

        $success = $room->game->challengePlayer($playerId, $targetId);
        $room->save();

        if ($success) {
            $this->jsonResponse(true, 'Challenge successful! Player draws 2 cards.');
        } else {
            $this->jsonResponse(false, 'Challenge failed! You draw 1 card penalty.');
        }
    }

    /**
     * AJAX endpoint: Reset game to lobby.
     */
    public function resetToLobby() {
        $roomId = $_GET['room_id'] ?? '';
        require_once __DIR__ . '/../models/Room.php';
        $room = Room::load($roomId);

        if (!$room) {
            $this->jsonResponse(false, 'Room not found.');
            return;
        }

        $playerId = $_SESSION['player_id'] ?? '';
        if ($room->creator_id !== $playerId) {
            $this->jsonResponse(false, 'Only the room creator can reset the game.');
            return;
        }

        $room->game->resetToLobby();
        $room->save();

        $this->jsonResponse(true, 'Game reset to lobby successfully.');
    }

    /**
     * Helper to return standard JSON.
     */
    private function jsonResponse($success, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
    }
}
