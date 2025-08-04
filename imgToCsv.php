<?php

$imagesUpdateFolder = "/home/drive/ftp/IMGS-Picking";

$aFilesUpdate = scandir($imagesUpdateFolder);


foreach ($aFilesUpdate as $item) {
    echo str_replace('.png', '', $item).";\n";
}


