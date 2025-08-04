<?php

// Configuration des chemins
$paths = [
    'origin' => '/home/drive/ftp',
    'backup' => [
        'stock' => '/home/drive/ftp/backup/stock/',
        'ref' => '/home/drive/ftp/backup/ref/',
        'price' => '/home/drive/ftp/backup/price/'
    ],
    'src' => [
        'stock' => '/home/drive/ftp/src/stock/',
        'ref' => '/home/drive/ftp/src/ref/',
        'price' => '/home/drive/ftp/src/price/',
        'product' => '/home/drive/ftp/src/product'
    ],
    'last_version' => [
        'stock' => '/home/drive/ftp/last_version/stock/',
        'ref' => '/home/drive/ftp/last_version/ref/',
        'price' => '/home/drive/ftp/last_version/price/'
    ]
];

// Création des dossiers si nécessaire
$dirs_to_create = [
    $paths['src']['product'],
    $paths['last_version']['stock'],
    $paths['last_version']['ref'],
    $paths['last_version']['price']
];

foreach ($dirs_to_create as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Map des préfixes aux dossiers
$prefixMap = [
    '2000' => '2000/',
    '2001' => '2001/',
    '2002' => '2002/',
    '2003' => '2003/'
];

try {
    $dir = opendir($paths['origin']);
    if (!$dir) {
        throw new Exception("Impossible d'ouvrir le dossier {$paths['origin']}");
    }

    $counts = [
        'stock' => 0,
        'ref' => 0,
        'price' => 0
    ];
    $hasCopied = false;

    while (($file = readdir($dir)) !== false) {
        // Ignorer . et ..
        if ($file === '.' || $file === '..') {
            continue;
        }

        $fileParts = explode('.', $file);
        if (count($fileParts) < 2 || !isset($fileParts[1])) {
            continue;
        }

        $prefixParts = explode('_', $fileParts[0]);
        if (count($prefixParts) < 2 || !isset($prefixParts[0], $prefixParts[1])) {
            continue;
        }

        $prefix = $prefixParts[0];
        $subPrefix = substr($prefixParts[1], 0, 4);
        $folder = $prefixMap[$subPrefix] ?? null;

        if (!$folder) {
            continue;
        }

        $filePath = "{$paths['origin']}/{$file}";

        switch (strtolower($prefix)) {
            case 'stock':
                $srcDest = "{$paths['src']['stock']}{$folder}source_stock.csv";
                $lastVersionDest = "{$paths['last_version']['stock']}source_stock_{$subPrefix}.csv";
                handleFileCopy($filePath, $paths['backup']['stock'], $file, $srcDest, $lastVersionDest);
                $counts['stock']++;
                $hasCopied = true;
                break;

            case 'ref':
                $srcDest = "{$paths['src']['ref']}{$folder}source_ref.csv";
                $lastVersionDest = "{$paths['last_version']['ref']}source_ref_{$subPrefix}.csv";
                handleFileCopy($filePath, $paths['backup']['stock'], $file, $srcDest, $lastVersionDest);
                $counts['ref']++;
                $hasCopied = true;
                break;

            case 'price':
                $srcDest = "{$paths['src']['price']}{$folder}source_price.csv";
                $lastVersionDest = "{$paths['last_version']['price']}source_price_{$subPrefix}.csv";
                handlePriceFileCopy($filePath, $paths['backup']['price'], $file, $srcDest, $lastVersionDest, $folder);
                $counts['price']++;
                $hasCopied = true;
                break;
        }
    }

    closedir($dir);

    // Affichage des résultats
    echo $hasCopied
        ? "Copie terminée :\n" .
        "Stock => {$counts['stock']}\n" .
        "Ref => {$counts['ref']}\n" .
        "Price => {$counts['price']}\n"
        : "Aucun nouveau fichier.\n";

} catch (Exception $e) {
    echo "Erreur : {$e->getMessage()}\n";
}

/**
 * Gère la copie d'un fichier avec sauvegarde de la dernière version
 */
function handleFileCopy(string $source, string $backupPath, string $fileName, string $srcDest, string $lastVersionDest): void {
    // Sauvegarde de la version précédente si elle existe
    if (file_exists($srcDest)) {
        if (!copy($srcDest, $lastVersionDest)) {
            throw new Exception("Échec de la sauvegarde de la dernière version vers $lastVersionDest");
        }
    }

    // Copie vers backup et source
    if (!copy($source, "{$backupPath}{$fileName}") || !copy($source, $srcDest)) {
        throw new Exception("Échec de la copie du fichier $source");
    }

    // Suppression du fichier original
    if (!unlink($source)) {
        throw new Exception("Échec de la suppression du fichier $source");
    }
}

/**
 * Gère la copie des fichiers price avec concatenation et sauvegarde de la dernière version
 */
function handlePriceFileCopy(string $source, string $backupPath, string $fileName, string $srcDest, string $lastVersionDest, string $folder): void {
    // Sauvegarde de la version précédente si elle existe
    if (file_exists($srcDest)) {
        if (!copy($srcDest, $lastVersionDest)) {
            throw new Exception("Échec de la sauvegarde de la dernière version vers $lastVersionDest");
        }
    }

    // Copie vers backup
    if (!copy($source, "{$backupPath}{$fileName}")) {
        throw new Exception("Échec de la copie vers {$backupPath}{$fileName}");
    }

    // Gestion de la copie vers source (avec concatenation si nécessaire)
    if (file_exists($srcDest)) {
        $content = file_get_contents($source);
        if ($content === false || !file_put_contents($srcDest, $content, FILE_APPEND)) {
            throw new Exception("Échec de l'écriture dans $srcDest");
        }
    } else {
        if (!copy($source, $srcDest)) {
            throw new Exception("Échec de la copie vers $srcDest");
        }
    }

    // Suppression du fichier original
    if (!unlink($source)) {
        throw new Exception("Échec de la suppression du fichier $source");
    }
}

?>
