<?php

// Configuration
const SOURCE_FOLDER = "/home/drive/ftp/src/ref";

const NEW_PRODUCTS_SOURCE_FOLDER = "/home/drive/ftp/last_version/ref/";
const DEST_FOLDER = "/home/drive/ftp/dest/product";
const IMAGES_UPDATE_FOLDER = "/home/drive/ftp/IMGS-Picking";
const IMAGES_FOLDER = "/home/drive/ftp/IMGS";
const IMAGE_URL_PREFIX = 'http://10.42.1.8/drive/IMGS-Picking/';
const LOGS_FOLDER = "/home/drive/ftp/logs";

const SHOPS = [
    '2000' => 2,
    '2001' => 4,
    '2002' => 3,
    '2003' => 6
];

const CSV_HEADER = [
    'name',
    'active',
    'categories',
    'pv',
    'reference',
    'fournisseur',
    'marque',
    'ean',
    'saveur',
    'libcom2',
    'contenance',
    'description',
    'images',
    'coeficient',
    'default_cat',
    'id_shop',
    'pv_prem'
];

/**
 * Process categories string into an array of integers
 */
function processCategories(string $categoryString): array {
    $categories = [];
    foreach (explode('/', trim($categoryString, '/')) as $category) {
        if ($category !== '') {
            $categories = array_merge($categories, str_split(str_pad($category, 9, "0"), 3));
        }
    }
    return array_map('intval', $categories);
}

/**
 * Find images matching reference
 */
function imageFindByRef(string $needle, array $imageFiles, string $prefix): array {
    return array_map(
        function($file) use ($prefix) {
            return $prefix . $file;
        },
        array_filter($imageFiles, function($file) use ($needle) {
            return strpos($file, $needle) === 0;
        })
    );
}

/**
 * Read CSV file into array, skipping header
 */
function csvToArray(string $filename, string $delimiter = ';'): array {
    if (!file_exists($filename) || !is_readable($filename)) {
        throw new RuntimeException("Cannot read file: $filename");
    }

    $data = [];
    if (($handle = fopen($filename, 'r')) !== false) {
        fgetcsv($handle, 1000, $delimiter); // Skip header
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
    }
    return $data;
}

/**
 * Process products for a shop
 */
function processShop(string $shop, array $imageFiles, array $imageFilesUpdate): void {
    $shopId = str_replace('/', '', $shop);
    echo "Processing shop $shopId\n";

    // File paths
    $timestamp = date('d-m-Y_Hi');
    $imagesLogFile = LOGS_FOLDER . DIRECTORY_SEPARATOR . "{$shopId}_{$timestamp}_images.csv";
    $destFileName = DEST_FOLDER . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . "{$shop}{$timestamp}_newProducts.csv";
    $destFileNameUpdate = DEST_FOLDER . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . "{$shop}{$timestamp}_updateProducts.csv";
    $sourceFile = SOURCE_FOLDER . DIRECTORY_SEPARATOR . "{$shop}source_ref.csv";
    $newProductsSourceFile = NEW_PRODUCTS_SOURCE_FOLDER . DIRECTORY_SEPARATOR . "{$shop}source_ref.csv";

    // Ensure directories exist
    $backupDir = dirname($destFileName);
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
        throw new RuntimeException("Cannot create backup directory: $backupDir");
    }

    // Initialize CSV files
    $fImagesCSV = fopen($imagesLogFile, 'w');
    if (!$fImagesCSV) {
        throw new RuntimeException("Cannot open images log file: $imagesLogFile");
    }
    fputcsv($fImagesCSV, ['ref', 'categories'], ';');

    $fp = fopen($destFileName, 'w');
    $fpSourceFile = fopen($sourceFile, 'a+');
    $fpUpdate = fopen($destFileNameUpdate, 'w');
    if (!$fp || !$fpUpdate) {
        throw new RuntimeException("Cannot open output files");
    }

    fputcsv($fp, CSV_HEADER, ';');
    fputcsv($fpUpdate, CSV_HEADER, ';');

    // Read source data
    try {
        $sourceContent = csvToArray($sourceFile);
        $newProductsSourceContent = csvToArray($newProductsSourceFile);
        echo "Source file: $sourceFile\n";
        echo "Number of references: " . count($sourceContent) . "\n";
        echo "New Products source file = $newProductsSourceFile\n";
        echo "Number of references: " . count($newProductsSourceContent) . "\n";
    } catch (RuntimeException $e) {
        echo "Error: {$e->getMessage()}\n";
        fclose($fImagesCSV);
        fclose($fp);
        fclose($fpUpdate);
        return;
    }

    foreach ($newProductsSourceContent as $line) {
        // New products
        $images = imageFindByRef($line[3], $imageFiles, IMAGE_URL_PREFIX);
        if ($images) {
            $categories = processCategories($line[1]);
            fputcsv($fp, [
                $line[0],
                0,
                implode(',', $categories),
                floatval(str_replace(',', '.', $line[2])),
                $line[3],
                $line[4],
                $line[5],
                strval($line[6]),
                $line[7],
                $line[8],
                $line[8],
                $line[8] . ' ' . $line[7],
                implode(',', $images),
                $line[10],
                $categories[2] ?? 0,
                SHOPS[$shopId],
                $line[11] ?? ''
            ], ';');

            fputcsv($fpSourceFile, $line, ';');
        }
    }

    // Process products
    foreach ($sourceContent as $line) {

        // Updated products
        $imagesUpdate = imageFindByRef($line[3], $imageFilesUpdate, IMAGE_URL_PREFIX);
        if (!$imagesUpdate) {
            fputcsv($fImagesCSV, [$line[3], $line[1]], ';');
            continue;
        }

        $categories = processCategories($line[1]);
        fputcsv($fpUpdate, [
            $line[0],
            1,
            implode(',', $categories),
            floatval(str_replace(',', '.', $line[2])),
            $line[3],
            $line[4],
            $line[5],
            strval($line[6]),
            $line[7],
            $line[8],
            $line[8],
            $line[8] . ' ' . $line[7],
            implode(',', $imagesUpdate),
            $line[10],
            $categories[2] ?? 0,
            SHOPS[$shopId],
            $line[11] ?? ''
        ], ';');
    }

    // Close files
    fclose($fImagesCSV);
    fclose($fp);
    fclose($fpSourceFile);
    fclose($fpUpdate);

    // Copy files to final destination
    $finalDest = DEST_FOLDER . DIRECTORY_SEPARATOR . "{$shop}newProducts.csv";
    $finalDestUpdate = DEST_FOLDER . DIRECTORY_SEPARATOR . "{$shop}updateProducts.csv";
    if (!copy($destFileName, $finalDest) || !copy($destFileNameUpdate, $finalDestUpdate)) {
        echo "Error: Failed to copy files to final destination\n";
    }

    echo "Shop $shopId processing completed\n";
}

// Main execution
try {
    // Cache directory scans
    $imageFiles = scandir(IMAGES_FOLDER) ?: [];
    $imageFilesUpdate = scandir(IMAGES_UPDATE_FOLDER) ?: [];
    echo "Number of images: " . count($imageFiles) . "\n";

    // Process each shop
    foreach (array_keys(SHOPS) as $shopId) {
        processShop("{$shopId}/", $imageFiles, $imageFilesUpdate);
    }
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}

echo "Processing complete\n";
exit(0);
