<?php
// Kopiere diese Datei nach config.php und passe sie an. config.php wird nicht ins Repository übernommen.
return [
    // SQLite (Standard): eine einzige Datei, keine Einrichtung nötig.
    // Am sichersten liegt sie außerhalb des Webroots.
    'db' => [
        'dsn' => 'sqlite:' . __DIR__ . '/data/signed.sqlite',
        'user' => null,
        'pass' => null,
    ],

    // MySQL/MariaDB, falls der Webspace das anbietet:
    // 'db' => [
    //     'dsn' => 'mysql:host=localhost;dbname=signed;charset=utf8mb4',
    //     'user' => 'signed',
    //     'pass' => 'geheim',
    // ],

    // true zeigt Fehlermeldungen im Browser. Auf dem echten Server ausgeschaltet lassen.
    'debug' => false,
];
