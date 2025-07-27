<?php
// auth.php + Raumlogik + Inventar + Kämpfe + Händler + PVP + Chat

function getDb(): PDO {
    static $db;
    if (!$db) {
        $db = new PDO('sqlite:game.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Tabellen
        $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password TEXT);");
        $db->exec("CREATE TABLE IF NOT EXISTS rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, description TEXT);");
        $db->exec("CREATE TABLE IF NOT EXISTS room_links (from_room TEXT, to_room TEXT);");
        $db->exec("CREATE TABLE IF NOT EXISTS user_locations (user_id INTEGER PRIMARY KEY, room_name TEXT);");
        $db->exec("CREATE TABLE IF NOT EXISTS inventory (user_id INTEGER, item TEXT, PRIMARY KEY(user_id, item));");
        $db->exec("CREATE TABLE IF NOT EXISTS monsters (room_name TEXT, name TEXT, hp INTEGER);");
        $db->exec("CREATE TABLE IF NOT EXISTS user_stats (user_id INTEGER PRIMARY KEY, hp INTEGER DEFAULT 100, gold INTEGER DEFAULT 0);");
        $db->exec("CREATE TABLE IF NOT EXISTS shop (item TEXT, price INTEGER);");
        $db->exec("CREATE TABLE IF NOT EXISTS chat (room_name TEXT, user TEXT, message TEXT, time TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");

        // Initialräume
        $stmt = $db->query("SELECT COUNT(*) as count FROM rooms");
        if ($stmt->fetchColumn() == 0) {
            $rooms = [
                ['Start', 'Du stehst auf einer sonnigen Lichtung.'],
                ['Wald', 'Ein dichter Wald mit geheimnisvollen Geräuschen.'],
                ['Höhle', 'Eine dunkle, feuchte Höhle.'],
                ['Dorf', 'Ein kleines Dorf mit einem Marktplatz.'],
                ['Straße', 'Eine alte Straße mit Pflastersteinen.']
            ];
            foreach ($rooms as [$name, $desc]) {
                $db->prepare("INSERT INTO rooms (name, description) VALUES (?, ?)")->execute([$name, $desc]);
            }
            $links = [['Start','Wald'],['Wald','Höhle'],['Start','Dorf'],['Dorf','Straße'],['Straße','Höhle']];
            foreach ($links as [$a,$b]) {
                $db->prepare("INSERT INTO room_links (from_room, to_room) VALUES (?, ?)")->execute([$a,$b]);
                $db->prepare("INSERT INTO room_links (from_room, to_room) VALUES (?, ?)")->execute([$b,$a]);
            }
            $db->prepare("INSERT INTO monsters (room_name, name, hp) VALUES ('Höhle', 'Goblin', 30)")->execute();
            $shopItems = [['Heiltrank', 10], ['Schwert', 25], ['Rüstung', 20]];
            foreach ($shopItems as [$item, $price]) {
                $db->prepare("INSERT INTO shop (item, price) VALUES (?, ?)")->execute([$item, $price]);
            }
        }
    }
    return $db;
}

// Weitere Funktionen (auth, Räume, Inventar etc.) wie bisher...

function kaempfen(int $userId): void {
    $db = getDb();
    $room = getCurrentRoom($userId);
    $stmt = $db->prepare("SELECT name, hp FROM monsters WHERE room_name = ?");
    $stmt->execute([$room]);
    $monster = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$monster) {
        echo "🐾 Kein Gegner hier.
";
        return;
    }

    echo "⚔️ Du kämpfst gegen {$monster['name']} mit {$monster['hp']} HP!
";
    $stmt = $db->prepare("SELECT hp FROM user_stats WHERE user_id = ?");
    $stmt->execute([$userId]);
    $playerHp = $stmt->fetchColumn();
    if ($playerHp <= 0) {
        echo "☠️ Du bist bewusstlos. Heil dich zuerst.
";
        return;
    }

    $monsterHp = $monster['hp'];
    while ($monsterHp > 0 && $playerHp > 0) {
        $monsterHp -= rand(5, 15);
        echo "💥 Du triffst das Monster. Es hat noch $monsterHp HP
";
        if ($monsterHp > 0) {
            $playerHp -= rand(3, 10);
            echo "😵 Das Monster trifft dich. Du hast noch $playerHp HP
";
        }
    }
    if ($playerHp <= 0) {
        echo "☠️ Du wurdest besiegt!
";
        $db->prepare("UPDATE user_stats SET hp = 0 WHERE user_id = ?")->execute([$userId]);
    } else {
        echo "🏆 Du hast {$monster['name']} besiegt!
";
        $db->prepare("DELETE FROM monsters WHERE room_name = ?")->execute([$room]);
        $db->prepare("UPDATE user_stats SET gold = gold + 20, hp = ? WHERE user_id = ?")->execute([$playerHp, $userId]);
    }
}

function shopInteraktion(int $userId): void {
    $db = getDb();
    echo "🛒 Willkommen im Shop:
";
    $items = $db->query("SELECT item, price FROM shop")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $i => $item) {
        echo ($i+1).") {$item['item']} - {$item['price']} Gold
";
    }
    $wahl = (int)readline("Kaufe (Nummer): ") - 1;
    if (!isset($items[$wahl])) return;

    $stmt = $db->prepare("SELECT gold FROM user_stats WHERE user_id = ?");
    $stmt->execute([$userId]);
    $gold = $stmt->fetchColumn();

    $preis = $items[$wahl]['price'];
    if ($gold >= $preis) {
        inventarHinzufuegen($userId, $items[$wahl]['item']);
        $db->prepare("UPDATE user_stats SET gold = gold - ? WHERE user_id = ?")->execute([$preis, $userId]);
        echo "✅ Gekauft!
";
    } else {
        echo "❌ Nicht genug Gold.
";
    }
}

function chatNachricht(int $userId, string $msg): void {
    $db = getDb();
    $room = getCurrentRoom($userId);
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetchColumn();
    $stmt = $db->prepare("INSERT INTO chat (room_name, user, message) VALUES (?, ?, ?)");
    $stmt->execute([$room, $user, $msg]);
}

function chatAnzeigen(int $userId): void {
    $db = getDb();
    $room = getCurrentRoom($userId);
    $stmt = $db->prepare("SELECT user, message, time FROM chat WHERE room_name = ? ORDER BY time DESC LIMIT 5");
    $stmt->execute([$room]);
    foreach ($stmt->fetchAll() as $row) {
        echo "💬 [{$row['time']}] {$row['user']}: {$row['message']}
";
    }
}

function getCurrentRoom(int $userId): string {
    $db = getDb();
    $stmt = $db->prepare("SELECT room_name FROM user_locations WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() ?: 'Start';
}
