<?php

class Player {
    public $id;
    public $name;
    public $is_bot;
    public $cards; // Array of Card objects
    public $called_uno; // Boolean flag

    public function __construct($id, $name, $is_bot = false, $cards = [], $called_uno = false) {
        $this->id = $id;
        $this->name = $name;
        $this->is_bot = $is_bot;
        $this->cards = $cards;
        $this->called_uno = $called_uno;
    }

    /**
     * Add a card to the player's hand.
     * 
     * @param Card $card
     */
    public function addCard($card) {
        $this->cards[] = $card;
        // Reset UNO call when drawing a card (standard rule: you must call UNO again if you draw)
        $this->called_uno = false;
    }

    /**
     * Remove a card by its ID.
     * 
     * @param string $cardId
     * @return Card|null The removed card, or null if not found.
     */
    public function removeCard($cardId) {
        foreach ($this->cards as $index => $card) {
            if ($card->id === $cardId) {
                unset($this->cards[$index]);
                $this->cards = array_values($this->cards); // Re-index array
                return $card;
            }
        }
        return null;
    }

    /**
     * Check if the player has any playable cards on the current board state.
     * 
     * @param Card $topCard
     * @param string $selectedColor
     * @return bool
     */
    public function hasPlayableCard($topCard, $selectedColor = null) {
        foreach ($this->cards as $card) {
            if ($card->isPlayableOn($topCard, $selectedColor)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reconstruct Player object from array.
     */
    public static function fromArray($array) {
        if (!$array) return null;
        
        $cards = [];
        if (isset($array['cards']) && is_array($array['cards'])) {
            foreach ($array['cards'] as $cardData) {
                $cards[] = Card::fromArray($cardData);
            }
        }

        return new self(
            $array['id'],
            $array['name'],
            $array['is_bot'] ?? false,
            $cards,
            $array['called_uno'] ?? false
        );
    }
}
