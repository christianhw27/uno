<?php
session_start();

// Load core models
require_once __DIR__ . '/models/Card.php';
require_once __DIR__ . '/models/Player.php';
require_once __DIR__ . '/models/Game.php';
require_once __DIR__ . '/models/Room.php';

// Load controllers
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/GameController.php';

// Route action
$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'home':
        $controller = new HomeController();
        $controller->index();
        break;

    case 'create_room':
        $controller = new HomeController();
        $controller->createRoom();
        break;

    case 'join_room':
        $controller = new HomeController();
        $controller->joinRoom();
        break;

    case 'game':
        $controller = new GameController();
        $controller->show();
        break;

    case 'game_status':
        $controller = new GameController();
        $controller->status();
        break;

    case 'add_bot':
        $controller = new GameController();
        $controller->addBot();
        break;

    case 'remove_bot':
        $controller = new GameController();
        $controller->removeBot();
        break;

    case 'start_game':
        $controller = new GameController();
        $controller->startGame();
        break;

    case 'play_card':
        $controller = new GameController();
        $controller->playCard();
        break;

    case 'draw_card':
        $controller = new GameController();
        $controller->drawCard();
        break;

    case 'pass_turn':
        $controller = new GameController();
        $controller->passTurn();
        break;

    case 'declare_uno':
        $controller = new GameController();
        $controller->declareUno();
        break;

    case 'challenge':
        $controller = new GameController();
        $controller->challenge();
        break;

    case 'reset_to_lobby':
        $controller = new GameController();
        $controller->resetToLobby();
        break;

    default:
        // Redirect back home on unknown actions
        header('Location: index.php');
        exit;
}
