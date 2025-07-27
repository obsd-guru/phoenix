<?php
// adventure.php
require 'auth.php';

const DB_FILE = 'game.sqlite';

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function setupGameDb() {
    $db = db();
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INTEGER PRIMARY KEY,
        name TEXT,
        description TEXT
    );
    CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY,
        name TEXT,
        type TEXT,
        value INTEGER,
        damage INTEGER,
        armor INTEGER
    );
    CREATE TABLE IF NOT EXISTS room_items (
        room_id INTEGER,
        item_id INTEGER,
        quantity INTEGER
    );
    CREATE TABLE IF NOT EXISTS inventories (
        user_id INTEGER,
        item_id INTEGER,
        quantity INTEGER
    );
    CREATE TABLE IF NOT EXISTS user_states (
        user_id INTEGER PRIMARY KEY,
        current_room INTEGER
    );
    CREATE TABLE IF NOT EXISTS monsters (
        id INTEGER PRIMARY KEY,
        room_id INTEGER,
        name TEXT,
        hp INTEGER,
        damage INTEGER
    );
    CREATE TABLE IF NOT EXISTS merchants (
        id INTEGER PRIMARY KEY,
        room_id INTEGER,
        name TEXT
    );
    CREATE TABLE IF NOT EXISTS merchant_items (
        merchant_id INTEGER,
        item_id INTEGER,
        price INTEGER
    );
    CREATE TABLE IF NOT EXISTS pvp_messages (
        id INTEGER PRIMARY KEY,
        from_user INTEGER,
        to_user INTEGER,
        message TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS pvp_fights (
        id INTEGER PRIMARY KEY,
        user1 INTEGER,
        user2 INTEGER,
        winner INTEGER,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // Nur wenn leer initialisieren
    $roomCount = $db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    if ($roomCount == 0) {
        seedWorld($db);
    }
}

function seedWorld(PDO $db) {
    $rooms = [
        ["Dorfplatz", "Ein ruhiger Platz mit einem Brunnen in der Mitte."],
        ["Dunkler Wald", "Ein schattiger Wald voller Gefahren."],
        ["Verlassene Hütte", "Eine verfallene Hütte mit knarrenden Dielen."],
        ["Verzauberter See", "Ein schimmernder See mit magischer Aura."],
        ["Drachenhöhle", "Ein finsterer Hort, in dem ein Drache lauert."]
    ];

    foreach ($rooms as $i => [$name, $desc]) {
        $stmt = $db->prepare("INSERT INTO rooms (id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$i + 1, $name, $desc]);
    }

    $items = [
        ["Heiltrank", "potion", 50, 0, 0],
        ["Holzschwert", "weapon", 10, 5, 0],
        ["Lederpanzer", "armor", 20, 0, 3]
    ];
    foreach ($items as $i => [$name, $type, $value, $dmg, $armor]) {
        $stmt = $db->prepare("INSERT INTO items (id, name, type, value, damage, armor) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$i + 1, $name, $type, $value, $dmg, $armor]);
    }

    // Händler im Dorf
    $db->exec("INSERT INTO merchants (id, room_id, name) VALUES (1, 1, 'Händler Hans')");
    $db->exec("INSERT INTO merchant_items (merchant_id, item_id, price) VALUES (1, 1, 20), (1, 2, 50), (1, 3, 40)");
}

function startGame(int $userId) {
    $db = db();
    // Initialer Raum setzen, wenn noch kein Spielstand
    $stmt = $db->prepare("INSERT OR IGNORE INTO user_states (user_id, current_room) VALUES (?, 1)");
    $stmt->execute([$userId]);

    echo "\nWillkommen im Textadventure! Gib 'hilfe' für Befehle.\n";

    while (true) {
        $room = getCurrentRoom($userId);
        echo "\nDu befindest dich in: {$room['name']}\n";
        echo $room['description'] . "\n";

        $input = strtolower(trim(readline("> ")));
        if ($input === 'exit') break;
        handleCommand($input, $userId);
    }
}

function getCurrentRoom(int $userId): array {
    $db = db();
    $stmt = $db->prepare("SELECT r.* FROM rooms r JOIN user_states u ON r.id = u.current_room WHERE u.user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function handleCommand(string $input, int $userId) {
    switch ($input) {
        case 'hilfe':
            echo "Verfügbare Befehle: gehe <raum>, inventar, nutze <item>, kaufe <item>, exit\n";
            break;
        case 'inventar':
            showInventory($userId);
            break;
        default:
            echo "Unbekannter Befehl.\n";
    }
}

function showInventory(int $userId) {
    $db = db();
    $stmt = $db->prepare("SELECT i.name, v.quantity FROM inventories v JOIN items i ON i.id = v.item_id WHERE v.user_id = ?");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($items)) {
        echo "Dein Inventar ist leer.\n";
    } else {
        foreach ($items as $item) {
            echo "- {$item['name']} x{$item['quantity']}\n";
        }
    }
}

// Einstiegspunkt nach erfolgreichem Login
function run() {
    setupGameDb();
    $userId = authFlow(); // aus auth.php
    startGame($userId);
}

run();

