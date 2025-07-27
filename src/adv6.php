<?php
// auth.php + adventure.php: Hauptmenü eingebaut

// (Importiere getDb, auth, kampf, shop usw. aus dem bisherigen Code)
require_once "auth.php";

function adventureMain(): void {
    $db = getDb();
    $userId = authFlow();
    zeigeAktuellenRaum($userId);

    while (true) {
        echo "\n📜 Was willst du tun?\n";
        echo "1) gehe <Raum>\n";
        echo "2) inventar\n";
        echo "3) kämpfen\n";
        echo "4) shop\n";
        echo "5) chat anzeigen\n";
        echo "6) chat schreiben\n";
        echo "7) beenden\n";
        $cmd = readline("➡️ Eingabe: ");

        if ($cmd === '7' || strtolower($cmd) === 'beenden') break;

        if (str_starts_with($cmd, 'gehe ')) {
            $ziel = trim(substr($cmd, 5));
            wechselRaum($userId, $ziel);
        } elseif ($cmd === '2' || $cmd === 'inventar') {
            inventarAnzeigen($userId);
        } elseif ($cmd === '3' || $cmd === 'kämpfen') {
            kaempfen($userId);
        } elseif ($cmd === '4' || $cmd === 'shop') {
            shopInteraktion($userId);
        } elseif ($cmd === '5' || $cmd === 'chat anzeigen') {
            chatAnzeigen($userId);
        } elseif ($cmd === '6' || $cmd === 'chat schreiben') {
            $msg = readline("Nachricht: ");
            chatNachricht($userId, $msg);
        } else {
            echo "❓ Unbekannter Befehl.\n";
        }
    }
    echo "👋 Spiel beendet.\n";
}

adventureMain();
