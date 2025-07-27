<?php
require_once "db.php"; // Funktion getDb() für SQLite-Verbindung

// Räume initial anlegen (falls noch nicht vorhanden)
function initRooms(): void {
    $db = getDb();
    $db->exec("
    CREATE TABLE IF NOT EXISTS rooms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        description TEXT
    );
    CREATE TABLE IF NOT EXISTS room_connections (
        from_room INTEGER,
        to_room INTEGER,
        PRIMARY KEY (from_room, to_room),
        FOREIGN KEY (from_room) REFERENCES rooms(id),
        FOREIGN KEY (to_room) REFERENCES rooms(id)
    );
    ");

    // Wenn leer, Räume einfügen (nur einmal)
    $stmt = $db->query("SELECT COUNT(*) as c FROM rooms");
    $count = $stmt->fetch()['c'];
    if ($count == 0) {
        $rooms = [
            ['name' => 'Dorfplatz', 'desc' => 'Du stehst auf dem Dorfplatz mit einem Brunnen in der Mitte.'],
            ['name' => 'Wald', 'desc' => 'Dichter Wald, Vogelgezwitscher überall.'],
            ['name' => 'Schatzkammer', 'desc' => 'Ein Raum voller Schatztruhen und geheimnisvoller Artefakte.'],
            ['name' => 'Höhle', 'desc' => 'Eine dunkle, feuchte Höhle mit unheimlichen Geräuschen.'],
            ['name' => 'Marktplatz', 'desc' => 'Hier verkaufen Händler ihre Waren und bieten Tränke und Waffen an.'],
        ];
        $insert = $db->prepare("INSERT INTO rooms (name, description) VALUES (:name, :desc)");
        foreach ($rooms as $r) {
            $insert->execute([':name' => $r['name'], ':desc' => $r['desc']]);
        }

        // Raumverbindungen (einfach als Beispiel)
        $db->exec("
            INSERT INTO room_connections (from_room, to_room) VALUES
            ((SELECT id FROM rooms WHERE name='Dorfplatz'), (SELECT id FROM rooms WHERE name='Wald')),
            ((SELECT id FROM rooms WHERE name='Wald'), (SELECT id FROM rooms WHERE name='Dorfplatz')),
            ((SELECT id FROM rooms WHERE name='Wald'), (SELECT id FROM rooms WHERE name='Schatzkammer')),
            ((SELECT id FROM rooms WHERE name='Schatzkammer'), (SELECT id FROM rooms WHERE name='Wald')),
            ((SELECT id FROM rooms WHERE name='Dorfplatz'), (SELECT id FROM rooms WHERE name='Marktplatz')),
            ((SELECT id FROM rooms WHERE name='Marktplatz'), (SELECT id FROM rooms WHERE name='Dorfplatz')),
            ((SELECT id FROM rooms WHERE name='Marktplatz'), (SELECT id FROM rooms WHERE name='Höhle')),
            ((SELECT id FROM rooms WHERE name='Höhle'), (SELECT id FROM rooms WHERE name='Marktplatz'));
        ");
    }
}

// Hole Raum-ID anhand Name
function getRoomIdByName(string $name): ?int {
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM rooms WHERE name = :name");
    $stmt->execute([':name' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

// Zeige aktuellen Raum (Name + Beschreibung)
function zeigeAktuellenRaum(int $userId): void {
    $db = getDb();
    $stmt = $db->prepare("SELECT current_room FROM user_stats WHERE user_id = :uid");
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "❌ Kein Raum gefunden.\n";
        return;
    }
    $roomId = (int)$row['current_room'];
    $stmt = $db->prepare("SELECT name, description FROM rooms WHERE id = :rid");
    $stmt->execute([':rid' => $roomId]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        echo "❌ Raum existiert nicht.\n";
        return;
    }
    echo "📍 Du bist im Raum: {$room['name']}\n";
    echo $room['description'] . "\n";
}

// Wechsle Raum, wenn Verbindung existiert
function wechselRaum(int $userId, string $zielName): void {
    $db = getDb();
    $currentRoomStmt = $db->prepare("SELECT current_room FROM user_stats WHERE user_id = :uid");
    $currentRoomStmt->execute([':uid' => $userId]);
    $current = $currentRoomStmt->fetch(PDO::FETCH_ASSOC)['current_room'];

    $zielId = getRoomIdByName($zielName);
    if (!$zielId) {
        echo "❌ Raum '$zielName' existiert nicht.\n";
        return;
    }

    // Prüfe, ob Verbindung vom aktuellen zum Zielraum besteht
    $connStmt = $db->prepare("SELECT 1 FROM room_connections WHERE from_room = :from AND to_room = :to");
    $connStmt->execute([':from' => $current, ':to' => $zielId]);
    if (!$connStmt->fetch()) {
        echo "❌ Du kannst nicht direkt von hier nach '$zielName'.\n";
        return;
    }

    // Raumwechsel speichern
    $update = $db->prepare("UPDATE user_stats SET current_room = :newroom WHERE user_id = :uid");
    $update->execute([':newroom' => $zielId, ':uid' => $userId]);
    echo "✅ Du bist nun im Raum '$zielName'.\n";
    zeigeAktuellenRaum($userId);
}

