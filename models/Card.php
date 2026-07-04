<?php

class Card {
    public $id;
    public $color; // 'red', 'blue', 'green', 'yellow', 'wild'
    public $type;  // 'number', 'skip', 'reverse', 'draw_2', 'wild', 'wild_4'
    public $value; // 0-9 for number cards, null for others
    public $image; // Filename of the card image, e.g., 'Blue_5.jpg'

    public function __construct($id, $color, $type, $value = null, $image = null) {
        $this->id = $id;
        $this->color = $color;
        $this->type = $type;
        $this->value = $value;
        $this->image = $image;
    }

    /**
     * Check if this card can be played on the active top card.
     * 
     * @param Card $topCard The card currently at the top of the discard pile.
     * @param string $selectedColor The active color chosen by the last Wild card.
     * @return bool
     */
    public function isPlayableOn($topCard, $selectedColor = null) {
        // Wild cards are always playable
        if ($this->color === 'wild') {
            return true;
        }

        // Determine the target color we must match.
        // If the top card was a wild card and a color was chosen, match that color.
        $targetColor = ($topCard->color === 'wild' && !empty($selectedColor)) ? $selectedColor : $topCard->color;

        // Match color
        if ($this->color === $targetColor) {
            return true;
        }

        // Match type (skip, reverse, draw_2, number)
        if ($this->type === $topCard->type) {
            // For number cards, they must also match value if types match.
            if ($this->type === 'number') {
                return $this->value === $topCard->value;
            }
            return true;
        }

        return false;
    }

    /**
     * Reconstruct Card object from array.
     */
    public static function fromArray($array) {
        if (!$array) return null;
        return new self(
            $array['id'],
            $array['color'],
            $array['type'],
            $array['value'] ?? null,
            $array['image'] ?? null
        );
    }
}
