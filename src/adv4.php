<?php
// auth.php + Raumlogik + Navigation + Inventarsystem

function getDb(): PDO {
    static $db;
    if (!$db) {
        $db = new PDO('sqlite:game.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Tabellen erstellen, falls sie nicht existieren
        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                password TEXT
            );
        SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                description TEXT
            );
        SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS room_links (
                from_room TEXT,
                to_room TEXT
            );
        SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS user_locations (
                user_id INTEGER PRIMARY KEY,
                room_name TEXT
            );
        SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS inventory (
                user_id INTEGER,
                item TEXT,
                PRIMARY KEY(user_id, item)
            );
        SQL);

        // Räume initialisieren, wenn sie leer sind
        $stmt = $db->query("SELECT COUNT(*) as count FROM rooms");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($count == 0) {
            $rooms = [
                ['Start', 'Du stehst auf einer sonnigen Lichtung.'],
                ['Wald', 'Ein dichter Wald mit geheimnisvollen Geräuschen.'],
                ['Höhle', 'Eine dunkle, feuchte Höhle.'],
                ['Dorf', 'Ein kleines Dorf mit ein paar Hütten.'],
                ['Straße', 'Eine gepflasterte Straße, die nach Osten führt.']
            ];

            foreach ($rooms as [$name, $desc]) {
                $stmt = $db->prepare("INSERT INTO rooms (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $desc]);
            }

            $links = [
                ['Start', 'Wald'],
                ['Wald', 'Höhle'],
                ['Start', 'Dorf'],
                ['Dorf', 'Straße'],
                ['Straße', 'Höhle']
            ];

            foreach ($links as [$from, $to]) {
                $stmt = $db->prepare("INSERT INTO room_links (from_room, to_room) VALUES (?, ?)");
                $stmt->execute([$from, $to]);
                $stmt->execute([$to, $from]); // bidirektional
            }
        }
    }
    return $db;
}

function authFlow(): int {
    $db = getDb();

    while (true) {
        echo "\n_ÿ💻 Auth CLI (SQLite)\n----------------------\n";
        echo "1) Login\n2) Registrierung\n3) Beenden\n";
        $choice = readline("Bitte wählen (1-3): ");

        switch ($choice) {
            case '1':
                echo "\n🔐 Login\n";
                $username = readline("Benutzername: ");
                $password = readline("Passwort: ");

                $stmt = $db->prepare("SELECT id, password FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    echo "✅ Willkommen zurück, $username!\n";
                    return (int) $user['id'];
                } else {
                    echo "❌ Login fehlgeschlagen.\n";
                }
                break;

            case '2':
                echo "\n🆕 Registrierung\n";
                $username = readline("Benutzername: ");
                $password = readline("Passwort: ");
                $hash = password_hash($password, PASSWORD_DEFAULT);

                try {
                    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    $stmt->execute([$username, $hash]);
                    echo "✅ Benutzer '$username' wurde registriert.\n";
                    return (int)$db->lastInsertId();
                } catch (PDOException $e) {
                    echo "❌ Registrierung fehlgeschlagen: Benutzername existiert bereits.\n";
                }
                break;

            case '3':
                exit("👋 Auf Wiedersehen!\n");

            default:
                echo "Ungültige Eingabe.\n";
        }
    }
}

function zeigeAktuellenRaum(int $userId): void {
    $db = getDb();
    $stmt = $db->prepare("SELECT room_name FROM user_locations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $room = $stmt->fetchColumn();

    if (!$room) {
        $room = 'Start';
        $stmt = $db->prepare("INSERT OR REPLACE INTO user_locations (user_id, room_name) VALUES (?, ?)");
        $stmt->execute([$userId, $room]);
    }

    $stmt = $db->prepare("SELECT description FROM rooms WHERE name = ?");
    $stmt->execute([$room]);
    $desc = $stmt->fetchColumn();

    echo "\n📍 Du bist in: $room\n📝 $desc\n";

    $stmt = $db->prepare("SELECT to_room FROM room_links WHERE from_room = ?");
    $stmt->execute([$room]);
    $exits = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "🚪 Ausgänge: " . implode(', ', $exits) . "\n";
}

function wechselRaum(int $userId, string $ziel): void {
    $db = getDb();
    $stmt = $db->prepare("SELECT room_name FROM user_locations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $current = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM room_links WHERE from_room = ? AND to_room = ?");
    $stmt->execute([$current, $ziel]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $db->prepare("UPDATE user_locations SET room_name = ? WHERE user_id = ?");
        $stmt->execute([$ziel, $userId]);
        echo "\n➡️ Du gehst nach $ziel.\n";
        zeigeAktuellenRaum($userId);
    } else {
        echo "\n❌ Du kannst nicht nach '$ziel' gehen.\n";
    }
}

function inventarHinzufuegen(int $userId, string $item): void {
    $db = getDb();
    $stmt = $db->prepare("INSERT OR IGNORE INTO inventory (user_id, item) VALUES (?, ?)");
    $stmt->execute([$userId, $item]);
    echo "🧰 '$item' wurde deinem Inventar hinzugefügt.\n";
}

function inventarAnzeigen(int $userId): void {
    $db = getDb();
    $stmt = $db->prepare("SELECT item FROM inventory WHERE user_id = ?");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "\n🎒 Dein Inventar:\n";
    if (count($items) === 0) {
        echo "(leer)\n";
    } else {
        foreach ($items as $item) {
            echo "- $item\n";
        }
    }
}

function inventarEntfernen(int $userId, string $item): void {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM inventory WHERE user_id = ? AND item = ?");
    $stmt->execute([$userId, $item]);
    echo "🗑️ '$item' wurde entfernt.\n";
}
