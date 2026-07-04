<?php

require_once __DIR__ . '/Card.php';
require_once __DIR__ . '/Player.php';

class Game {
    public $deck = [];
    public $discard_pile = [];
    public $players = [];
    public $current_turn = 0; // index of active player
    public $direction = 1;    // 1 for clockwise, -1 for counter-clockwise
    public $selected_color = null; // color for wild card
    public $status = 'lobby'; // 'lobby', 'playing', 'finished'
    public $winner_id = null;
    public $logs = [];
    public $last_action_time = 0.0;
    public $has_drawn_this_turn = false; // Flag to prevent multiple draws in a turn
    public $accumulated_draw_penalty = 0; // Accumulated stackable draw penalty (+2 / +4)
    public $initial_card_count = 7; // Cards dealt to each player at game start
    public $last_play_cards = []; // Cards from the most recent play action (single or combo)

    public function __construct() {
        $this->last_action_time = microtime(true);
    }

    /**
     * Start the game: generates deck, deals cards, sets up discard pile.
     */
    public function start() {
        if (count($this->players) < 2) {
            $this->addLog("Cannot start game. At least 2 players are required.");
            return false;
        }

        $this->generateDeck();
        
        // Deal cards to each player
        foreach ($this->players as $player) {
            $player->cards = [];
            for ($i = 0; $i < $this->initial_card_count; $i++) {
                $player->addCard(array_pop($this->deck));
            }
            $player->called_uno = false;
        }

        // Setup initial discard card — must be a NUMBER card (not effect card)
        do {
            $firstCard = array_pop($this->deck);
            if ($firstCard->type !== 'number') {
                // Put back and shuffle if it's not a number card
                $this->deck[] = $firstCard;
                shuffle($this->deck);
            } else {
                $this->discard_pile[] = $firstCard;
                break;
            }
        } while (true);

        $this->status = 'playing';
        $this->current_turn = rand(0, count($this->players) - 1);
        $this->direction = 1;
        $this->selected_color = null;
        $this->winner_id = null;
        $this->has_drawn_this_turn = false;
        $this->accumulated_draw_penalty = 0;
        $this->last_play_cards = [];
        $this->last_action_time = microtime(true);

        $this->addLog("Game started! First card is " . $this->getCardName($firstCard));

        // If the first card is a special card, apply its effect immediately
        if ($firstCard->type === 'skip') {
            $this->addLog($this->getActivePlayer()->name . "'s turn skipped by the starter card.");
            $this->advanceTurn(1);
        } elseif ($firstCard->type === 'reverse') {
            if (count($this->players) == 2) {
                $this->addLog($this->getActivePlayer()->name . "'s turn skipped by the starter reverse.");
                $this->advanceTurn(1);
            } else {
                $this->direction *= -1;
                $this->addLog("Play direction reversed by the starter card.");
                // In standard UNO, dealer plays first, but reverse makes it go counter-clockwise (so player index is adjusted).
                // Let's start with player 0, but if it reversed, we go counter-clockwise next.
            }
        } elseif ($firstCard->type === 'draw_2') {
            $activePlayer = $this->getActivePlayer();
            $this->addLog($activePlayer->name . " draws 2 cards and turn skipped by starter Draw 2.");
            $this->drawCardsForPlayer($activePlayer, 2);
            $this->advanceTurn(1);
        } elseif ($firstCard->type === 'wild') {
            $this->addLog("Starter card is Wild! " . $this->getActivePlayer()->name . " can play any card.");
        }

        return true;
    }

    /**
     * Reset the game to the lobby state, keeping joined players and bots but clearing cards, deck, and logs.
     */
    public function resetToLobby() {
        $this->deck = [];
        $this->discard_pile = [];
        $this->current_turn = 0;
        $this->direction = 1;
        $this->selected_color = null;
        $this->status = 'lobby';
        $this->winner_id = null;
        $this->has_drawn_this_turn = false;
        $this->accumulated_draw_penalty = 0;
        $this->last_play_cards = [];
        
        foreach ($this->players as $player) {
            $player->cards = [];
            $player->called_uno = false;
        }
        
        $this->logs = [];
        $this->addLog("Game room returned to lobby.");
        $this->last_action_time = microtime(true);
    }

    /**
     * Generate standard UNO deck.
     */
    private function generateDeck() {
        $this->deck = [];
        $cardId = 1;
        $colors = ['red', 'blue', 'green', 'yellow'];

        foreach ($colors as $color) {
            $colorCap = ucfirst($color);
            // Number 0
            $img = "{$colorCap}_0.jpg";
            $this->deck[] = new Card("card_" . $cardId++, $color, 'number', 0, $img);

            // Numbers 1-9 (2 of each)
            for ($i = 1; $i <= 9; $i++) {
                $img = "{$colorCap}_{$i}.jpg";
                $this->deck[] = new Card("card_" . $cardId++, $color, 'number', $i, $img);
                $this->deck[] = new Card("card_" . $cardId++, $color, 'number', $i, $img);
            }

            // Skip (2 of each)
            $img = "{$colorCap}_Skip.jpg";
            $this->deck[] = new Card("card_" . $cardId++, $color, 'skip', null, $img);
            $this->deck[] = new Card("card_" . $cardId++, $color, 'skip', null, $img);

            // Reverse (2 of each)
            if ($color === 'red') {
                $img = "RED_Reverse.jpg"; // handle uppercase Red reverse
            } else {
                $img = "{$colorCap}_Reverse.jpg";
            }
            $this->deck[] = new Card("card_" . $cardId++, $color, 'reverse', null, $img);
            $this->deck[] = new Card("card_" . $cardId++, $color, 'reverse', null, $img);

            // Draw 2 (2 of each)
            $img = "{$colorCap}_Draw_2.jpg";
            $this->deck[] = new Card("card_" . $cardId++, $color, 'draw_2', null, $img);
            $this->deck[] = new Card("card_" . $cardId++, $color, 'draw_2', null, $img);
        }

        // Wild Cards (4 cards)
        for ($i = 1; $i <= 4; $i++) {
            $img = "Wild({$i}).jpg";
            $this->deck[] = new Card("card_" . $cardId++, 'wild', 'wild', null, $img);
        }

        // Wild Draw 4 Cards (4 cards)
        for ($i = 1; $i <= 4; $i++) {
            $img = "Wild_Draw_4({$i}).jpg";
            $this->deck[] = new Card("card_" . $cardId++, 'wild', 'wild_4', null, $img);
        }

        shuffle($this->deck);
    }

    /**
     * Draw cards for a player from the deck.
     */
    private function drawCardsForPlayer($player, $count) {
        for ($i = 0; $i < $count; $i++) {
            if (empty($this->deck)) {
                $this->recycleDiscardPile();
            }
            if (!empty($this->deck)) {
                $player->addCard(array_pop($this->deck));
            }
        }
    }

    /**
     * Recycles cards from the discard pile (except top card) back into the deck.
     */
    private function recycleDiscardPile() {
        if (count($this->discard_pile) <= 1) {
            return;
        }

        $topCard = array_pop($this->discard_pile);
        $this->deck = $this->discard_pile;
        $this->discard_pile = [$topCard];

        // Shuffle deck
        shuffle($this->deck);
        $this->addLog("Deck reshuffled from discard pile.");
    }

    /**
     * Get the active player whose turn it is.
     * 
     * @return Player
     */
    public function getActivePlayer() {
        return $this->players[$this->current_turn];
    }

    /**
     * Advance the turn.
     * 
     * @param int $steps Number of turn spaces to advance.
     */
    private function advanceTurn($steps = 1) {
        $playerCount = count($this->players);
        $this->current_turn = ($this->current_turn + ($steps * $this->direction) + ($playerCount * 100)) % $playerCount;
        $this->has_drawn_this_turn = false;
        $this->last_action_time = microtime(true);
    }

    /**
     * Play a card from player's hand.
     */
    public function playCard($playerId, $cardId, $chosenColor = null) {
        if ($this->status !== 'playing') return false;

        $activePlayer = $this->getActivePlayer();
        if ($activePlayer->id !== $playerId) {
            return false; // Not their turn
        }

        // Find the card in the player's hand
        $card = null;
        foreach ($activePlayer->cards as $c) {
            if ($c->id === $cardId) {
                $card = $c;
                break;
            }
        }

        if (!$card) return false;

        $topCard = end($this->discard_pile);

        // Stacking check: if a penalty is active, the player can ONLY play draw_2 or wild_4
        // and ANY draw_2/wild_4 is stackable regardless of the top card
        if ($this->accumulated_draw_penalty > 0) {
            if ($card->type !== 'draw_2' && $card->type !== 'wild_4') {
                return false;
            }
        } else {
            // Normal playability check (no penalty)
            if (!$card->isPlayableOn($topCard, $this->selected_color)) {
                return false;
            }
        }

        // Remove card from hand
        $activePlayer->removeCard($cardId);
        $this->discard_pile[] = $card;
        $this->last_play_cards = [$card]; // track single-card play
        
        $cardName = $this->getCardName($card);
        $logMessage = "{$activePlayer->name} played {$cardName}";

        // Clear previous selected color
        $this->selected_color = null;

        // Process Card Action
        $nextSkip = false;

        if ($card->color === 'wild') {
            $validColors = ['red', 'blue', 'green', 'yellow'];
            $this->selected_color = in_array(strtolower($chosenColor), $validColors) ? strtolower($chosenColor) : 'red';
            $logMessage .= " (chosen color: " . ucfirst($this->selected_color) . ")";
        }

        if ($card->type === 'skip') {
            $nextSkip = true;
        } elseif ($card->type === 'reverse') {
            if (count($this->players) == 2) {
                $nextSkip = true; // In 2-player games, reverse acts as skip
            } else {
                $this->direction *= -1;
                $logMessage .= ". Play direction reversed!";
            }
        } elseif ($card->type === 'draw_2') {
            $this->accumulated_draw_penalty += 2;
            $logMessage .= ". Penalti bertambah! Total penalti: +{$this->accumulated_draw_penalty}";
        } elseif ($card->type === 'wild_4') {
            $validColors = ['red', 'blue', 'green', 'yellow'];
            $this->selected_color = in_array(strtolower($chosenColor), $validColors) ? strtolower($chosenColor) : 'red';
            $this->accumulated_draw_penalty += 4;
            $logMessage .= " (chosen color: " . ucfirst($this->selected_color) . "). Penalti bertambah! Total penalti: +{$this->accumulated_draw_penalty}";
        }

        $this->addLog($logMessage);

        // Check Victory
        if (count($activePlayer->cards) === 0) {
            $this->status = 'finished';
            $this->winner_id = $activePlayer->id;
            $this->addLog("🏆 {$activePlayer->name} wins the game!");
            $this->last_action_time = microtime(true);
            return true;
        }

        // Move to next turn
        // Advance turn: 2 steps if skipped (since the next player is skipped), 1 otherwise
        $steps = $nextSkip ? 2 : 1;
        $this->advanceTurn($steps);

        return true;
    }

    /**
     * Play multiple cards of the same number/type in one turn (combo play).
     * The first card must be valid against the discard pile.
     * Subsequent cards must have the same type and value as the first card.
     * The last card in the array becomes the new top of the discard pile.
     *
     * @param string $playerId
     * @param array $cardIds Array of card IDs to play
     * @param string|null $chosenColor Color chosen for wild cards (if any)
     * @return bool
     */
    public function playCombo($playerId, $cardIds, $chosenColor = null) {
        if ($this->status !== 'playing') return false;
        if (empty($cardIds)) return false;

        // Single card? Use normal playCard
        if (count($cardIds) === 1) {
            return $this->playCard($playerId, $cardIds[0], $chosenColor);
        }

        $activePlayer = $this->getActivePlayer();
        if ($activePlayer->id !== $playerId) return false;

        // Collect all card objects from hand
        $cardsToPlay = [];
        foreach ($cardIds as $cid) {
            $found = null;
            foreach ($activePlayer->cards as $c) {
                if ($c->id === $cid) { $found = $c; break; }
            }
            if (!$found) return false; // card not in hand
            $cardsToPlay[] = $found;
        }

        // Combo not allowed during draw penalty (must stack +2/+4 one at a time)
        if ($this->accumulated_draw_penalty > 0) return false;

        // First card must be playable against discard pile
        $topCard = end($this->discard_pile);
        $firstCard = $cardsToPlay[0];
        if (!$firstCard->isPlayableOn($topCard, $this->selected_color)) return false;

        // All cards in combo must share the same type and value
        foreach ($cardsToPlay as $i => $card) {
            if ($i === 0) continue;
            if ($card->type !== $firstCard->type) return false;
            if ($card->type === 'number' && $card->value !== $firstCard->value) return false;
        }

        // Wild cards cannot be combo'd
        if ($firstCard->color === 'wild') return false;

        // All validated — remove cards from hand and add to discard
        $names = [];
        foreach ($cardsToPlay as $card) {
            $activePlayer->removeCard($card->id);
            $this->discard_pile[] = $card;
            $names[] = $this->getCardName($card);
        }
        $this->last_play_cards = $cardsToPlay; // track combo cards

        $this->selected_color = null; // last card is a colored card (no wild in combo)
        $logMessage = "{$activePlayer->name} played COMBO: " . implode(' + ', $names);

        // The last card determines the effect
        $lastCard = end($cardsToPlay);
        $nextSkip = false;
        $skipSteps = 0;

        if ($lastCard->type === 'skip') {
            // Count skips — skip that many players ahead
            $skipCount = 0;
            foreach ($cardsToPlay as $c) {
                if ($c->type === 'skip') $skipCount++;
            }
            $nextSkip = true;
            $skipSteps = $skipCount;
            $logMessage .= ". Melewati {$skipCount} pemain!";
        } elseif ($lastCard->type === 'reverse') {
            // Each reverse flips the direction
            $reversals = 0;
            foreach ($cardsToPlay as $c) {
                if ($c->type === 'reverse') $reversals++;
            }
            if (count($this->players) == 2) {
                // In 2-player, odd reversals = skip 1 player per reverse
                if ($reversals > 0) {
                    $nextSkip = true;
                    $skipSteps = $reversals;
                }
            } else {
                // Odd number of reversals = direction changed
                if ($reversals % 2 === 1) {
                    $this->direction *= -1;
                    $logMessage .= ". Arah bermain berbalik!";
                }
            }
        } elseif ($lastCard->type === 'draw_2') {
            // Each draw_2 in combo adds +2
            $drawCount = 0;
            foreach ($cardsToPlay as $c) {
                if ($c->type === 'draw_2') $drawCount++;
            }
            $this->accumulated_draw_penalty += $drawCount * 2;
            $logMessage .= ". Penalti +{$this->accumulated_draw_penalty}!";
        }

        $this->addLog($logMessage);

        // Check victory
        if (count($activePlayer->cards) === 0) {
            $this->status = 'finished';
            $this->winner_id = $activePlayer->id;
            $this->addLog("🏆 {$activePlayer->name} wins the game!");
            $this->last_action_time = microtime(true);
            return true;
        }

        $steps = $nextSkip ? 1 + $skipSteps : 1;
        $this->advanceTurn($steps);

        return true;
    }

    /**
     * Draw a card for the current player.
     */
    public function drawCard($playerId) {
        if ($this->status !== 'playing') return false;

        $activePlayer = $this->getActivePlayer();
        if ($activePlayer->id !== $playerId) return false;

        // Stacking draw logic
        if ($this->accumulated_draw_penalty > 0) {
            $penalty = $this->accumulated_draw_penalty;
            $this->drawCardsForPlayer($activePlayer, $penalty);
            $this->addLog("{$activePlayer->name} mengambil {$penalty} kartu penalti.");
            $this->accumulated_draw_penalty = 0;
            $this->has_drawn_this_turn = false; // Reset for next turn
            $this->advanceTurn(1); // Drawn penalty ends player's turn instantly
            return true;
        }

        if ($this->has_drawn_this_turn) return false; // Already drew once

        if (empty($this->deck)) {
            $this->recycleDiscardPile();
        }

        if (empty($this->deck)) {
            $this->addLog("No cards left in deck to draw!");
            return false;
        }

        $drawnCard = array_pop($this->deck);
        $activePlayer->addCard($drawnCard);
        $this->has_drawn_this_turn = true;
        
        $this->addLog("{$activePlayer->name} drew a card.");
        $this->last_action_time = microtime(true);

        return $drawnCard;
    }

    /**
     * Pass the turn (only allowed after drawing a card).
     */
    public function passTurn($playerId) {
        if ($this->status !== 'playing') return false;

        $activePlayer = $this->getActivePlayer();
        if ($activePlayer->id !== $playerId) return false;
        if (!$this->has_drawn_this_turn) return false; // Must draw first before passing

        $this->addLog("{$activePlayer->name} passed their turn.");
        $this->advanceTurn(1);
        return true;
    }

    /**
     * Declare UNO for a player.
     */
    public function declareUno($playerId) {
        if ($this->status !== 'playing') return false;

        foreach ($this->players as $player) {
            if ($player->id === $playerId) {
                $player->called_uno = true;
                $this->addLog("📣 {$player->name} declared UNO!");
                $this->last_action_time = microtime(true);
                return true;
            }
        }
        return false;
    }

    /**
     * Challenge a player who has only 1 card left but did not call UNO.
     */
    public function challengePlayer($challengerId, $targetId) {
        if ($this->status !== 'playing') return false;

        $challenger = null;
        $target = null;

        foreach ($this->players as $player) {
            if ($player->id === $challengerId) $challenger = $player;
            if ($player->id === $targetId) $target = $player;
        }

        if (!$challenger || !$target) return false;

        // Condition for challenge: target has exactly 1 card and has not called UNO
        if (count($target->cards) === 1 && !$target->called_uno) {
            $this->drawCardsForPlayer($target, 2);
            $this->addLog("🎯 {$challenger->name} successfully challenged {$target->name} for not declaring UNO! {$target->name} draws 2 cards.");
            $this->last_action_time = microtime(true);
            return true;
        }

        // If target actually has more than 1 card or already called UNO, challenger gets penalized?
        // Let's implement a penalty for false challenge: challenger draws 1 card (fun rule!)
        $this->drawCardsForPlayer($challenger, 1);
        $this->addLog("❌ False challenge! {$challenger->name} challenged {$target->name}, but they are safe. {$challenger->name} draws 1 card as penalty.");
        $this->last_action_time = microtime(true);
        return false;
    }

    /**
     * Automatically run bots challenges checking.
     * Bots check if any other player has 1 card and hasn't called UNO.
     * If they do, they challenge them.
     */
    public function runBotChallenges() {
        foreach ($this->players as $bot) {
            if (!$bot->is_bot) continue;

            foreach ($this->players as $player) {
                if ($player->id === $bot->id) continue;

                // If player has exactly 1 card and did not call UNO
                if (count($player->cards) === 1 && !$player->called_uno) {
                    // 30% chance to challenge on this status poll
                    if (rand(1, 100) <= 30) {
                        $this->challengePlayer($bot->id, $player->id);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Execute the bot's turn.
     * Throttled by time to feel realistic.
     */
    public function runBotTurn() {
        if ($this->status !== 'playing') return false;

        $activePlayer = $this->getActivePlayer();
        if (!$activePlayer->is_bot) return false;

        // 2-second delay to simulate thinking time
        $timeElapsed = microtime(true) - $this->last_action_time;
        if ($timeElapsed < 2.0) {
            return false; // Wait
        }

        $topCard = end($this->discard_pile);

        // Stacking check
        if ($this->accumulated_draw_penalty > 0) {
            $stackCards = [];
            foreach ($activePlayer->cards as $card) {
                if ($card->type === 'draw_2' || $card->type === 'wild_4') {
                    $stackCards[] = $card;
                }
            }

            if (!empty($stackCards)) {
                // Play a stacking card
                $cardToPlay = $stackCards[array_rand($stackCards)];
                $chosenColor = null;

                if ($cardToPlay->color === 'wild') {
                    $colorCounts = ['red' => 0, 'blue' => 0, 'green' => 0, 'yellow' => 0];
                    foreach ($activePlayer->cards as $c) {
                        if ($c->color !== 'wild') {
                            $colorCounts[$c->color]++;
                        }
                    }
                    arsort($colorCounts);
                    $chosenColor = key($colorCounts);
                }

                if (count($activePlayer->cards) === 2) {
                    if (rand(1, 100) <= 95) {
                        $this->declareUno($activePlayer->id);
                    }
                }

                $this->playCard($activePlayer->id, $cardToPlay->id, $chosenColor);
                return true;
            } else {
                // No stacking card, must take penalty
                $this->drawCard($activePlayer->id);
                return true;
            }
        }

        // Normal bot logic
        // Find playable cards in hand
        $playableCards = [];
        foreach ($activePlayer->cards as $card) {
            if ($card->isPlayableOn($topCard, $this->selected_color)) {
                $playableCards[] = $card;
            }
        }

        // AI decision
        if (!empty($playableCards)) {
            // Pick card to play: prefer action/wild cards or just pick a random one
            // Let's make bots a bit smart: prioritize action/wild cards
            $actionCards = [];
            $numberCards = [];
            foreach ($playableCards as $card) {
                if ($card->color === 'wild' || in_array($card->type, ['skip', 'reverse', 'draw_2'])) {
                    $actionCards[] = $card;
                } else {
                    $numberCards[] = $card;
                }
            }

            // 70% chance to play an action card if they have one, to be aggressive
            if (!empty($actionCards) && (empty($numberCards) || rand(1, 100) <= 70)) {
                $cardToPlay = $actionCards[array_rand($actionCards)];
            } else {
                $cardToPlay = $numberCards[array_rand($numberCards)];
            }

            // Color choice for Wild
            $chosenColor = null;
            if ($cardToPlay->color === 'wild') {
                // Pick the color the bot has the most of in hand
                $colorCounts = ['red' => 0, 'blue' => 0, 'green' => 0, 'yellow' => 0];
                foreach ($activePlayer->cards as $c) {
                    if ($c->color !== 'wild') {
                        $colorCounts[$c->color]++;
                    }
                }
                arsort($colorCounts);
                $chosenColor = key($colorCounts);
            }

            // Check if playing this card will leave the bot with 1 card.
            // If so, the bot will automatically call UNO.
            if (count($activePlayer->cards) === 2) {
                // Bot calls UNO 95% of the time. 5% chance they "forget" so humans can challenge them!
                if (rand(1, 100) <= 95) {
                    $this->declareUno($activePlayer->id);
                }
            }

            // Play the card
            $this->playCard($activePlayer->id, $cardToPlay->id, $chosenColor);
            return true;

        } else {
            // No playable card, draw one
            if (!$this->has_drawn_this_turn) {
                $drawnCard = $this->drawCard($activePlayer->id);
                
                // Check if the drawn card is playable
                if ($drawnCard && $drawnCard->isPlayableOn($topCard, $this->selected_color)) {
                    // Play it immediately!
                    $chosenColor = null;
                    if ($drawnCard->color === 'wild') {
                        $colorCounts = ['red' => 0, 'blue' => 0, 'green' => 0, 'yellow' => 0];
                        foreach ($activePlayer->cards as $c) {
                            if ($c->color !== 'wild') {
                                $colorCounts[$c->color]++;
                            }
                        }
                        arsort($colorCounts);
                        $chosenColor = key($colorCounts);
                    }
                    
                    if (count($activePlayer->cards) === 2) {
                        if (rand(1, 100) <= 95) {
                            $this->declareUno($activePlayer->id);
                        }
                    }

                    $this->playCard($activePlayer->id, $drawnCard->id, $chosenColor);
                } else {
                    // Pass turn
                    $this->passTurn($activePlayer->id);
                }
                return true;
            } else {
                // Safety pass
                $this->passTurn($activePlayer->id);
                return true;
            }
        }

        return false;
    }

    /**
     * Add log entry. Keep last 25 logs.
     */
    public function addLog($message) {
        $this->logs[] = $message;
        if (count($this->logs) > 25) {
            array_shift($this->logs);
        }
    }

    /**
     * Convert Card object to human readable string.
     */
    private function getCardName($card) {
        $colorName = ucfirst($card->color);
        if ($card->type === 'number') {
            return "{$colorName} {$card->value}";
        } elseif ($card->type === 'skip') {
            return "{$colorName} Skip";
        } elseif ($card->type === 'reverse') {
            return "{$colorName} Reverse";
        } elseif ($card->type === 'draw_2') {
            return "{$colorName} Draw 2";
        } elseif ($card->type === 'wild') {
            return "Wild Card";
        } elseif ($card->type === 'wild_4') {
            return "Wild Draw 4";
        }
        return "Unknown Card";
    }

    /**
     * Reconstruct Game object from array.
     */
    public static function fromArray($array) {
        if (!$array) return null;

        $game = new self();
        
        // Load deck
        if (isset($array['deck']) && is_array($array['deck'])) {
            foreach ($array['deck'] as $cardData) {
                $game->deck[] = Card::fromArray($cardData);
            }
        }

        // Load discard pile
        if (isset($array['discard_pile']) && is_array($array['discard_pile'])) {
            foreach ($array['discard_pile'] as $cardData) {
                $game->discard_pile[] = Card::fromArray($cardData);
            }
        }

        // Load players
        if (isset($array['players']) && is_array($array['players'])) {
            foreach ($array['players'] as $playerData) {
                $game->players[] = Player::fromArray($playerData);
            }
        }

        $game->current_turn = $array['current_turn'] ?? 0;
        $game->direction = $array['direction'] ?? 1;
        $game->selected_color = $array['selected_color'] ?? null;
        $game->status = $array['status'] ?? 'lobby';
        $game->winner_id = $array['winner_id'] ?? null;
        $game->logs = $array['logs'] ?? [];
        $game->last_action_time = $array['last_action_time'] ?? 0.0;
        $game->has_drawn_this_turn = $array['has_drawn_this_turn'] ?? false;
        $game->accumulated_draw_penalty = $array['accumulated_draw_penalty'] ?? 0;
        $game->initial_card_count = $array['initial_card_count'] ?? 7;
        $game->last_play_cards = [];

        if (isset($array['last_play_cards']) && is_array($array['last_play_cards'])) {
            foreach ($array['last_play_cards'] as $cardData) {
                $game->last_play_cards[] = Card::fromArray($cardData);
            }
        }

        return $game;
    }
}
