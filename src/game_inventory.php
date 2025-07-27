<?php
require_once "db.php";

// Inventar-Tabelle anlegen
function initInventory(): void {
    $db = getDb();
    $db->exec("
    CREATE TABLE IF NOT EXISTS inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        item_name TEXT,
        quantity INTEGER DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    ");
}

// Inventar anzeigen
function inventarAnzeigen(int $userId): void {
    $db = getDb();
    $stmt = $db->prepare("SELECT item_name, quantity FROM inventory WHERE user_id = :uid");
    $stmt->execute([':uid' => $userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($items)) {
        echo "👜 Dein Inventar ist leer.\n";
        return;
    }
    echo "👜 Dein Inventar:\n";
    foreach ($items as $item) {
        echo "- {$item['item_name']} (x{$item['quantity']})\n";
    }
}

// Item hinzufügen
function addItem(int $userId, string $itemName, int $qty = 1): void {
    $db = getDb();
    // Prüfe ob Item schon im Inventar ist
    $stmt = $db->prepare("SELECT id, quantity FROM inventory WHERE user_id = :uid AND item_name = :item");
    $stmt->execute([':uid' => $userId, ':item' => $itemName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Menge erhöhen
        $newQty = $row['quantity'] + $qty;
        $update = $db->prepare("UPDATE inventory SET quantity = :qty WHERE id = :id");
        $update->execute([':qty' => $newQty, ':id' => $row['id']]);
    } else {
        // Neu anlegen
        $insert = $db->prepare("INSERT INTO inventory (user_id, item_name, quantity) VALUES (:uid, :item, :qty)");
        $insert->execute([':uid' => $userId, ':item' => $itemName, ':qty' => $qty]);
    }
    echo "✅ '$itemName' wurde deinem Inventar hinzugefügt.\n";
}

// Item entfernen (Menge oder ganz)
function removeItem(int $userId, string $itemName, int $qty = 1): void {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, quantity FROM inventory WHERE user_id = :uid AND item_name = :item");
    $stmt->execute([':uid' => $userId, ':item' => $itemName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "❌ Du besitzt kein '$itemName'.\n";
        return;
    }

    if ($row['quantity'] <= $qty) {
        // Komplett löschen
        $del = $db->prepare("DELETE FROM inventory WHERE id = :id");
        $del->execute([':id' => $row['id']]);
    } else {
        // Menge verringern
        $newQty = $row['quantity'] - $qty;
        $update = $db->prepare("UPDATE inventory SET quantity = :qty WHERE id = :id");
        $update->execute([':qty' => $newQty, ':id' => $row['id']]);
    }
    echo "✅ '$itemName' wurde aus deinem Inventar entfernt.\n";
}

