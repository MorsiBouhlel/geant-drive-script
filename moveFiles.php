<?php

$dirpath = '/home/ubuntu_user/geant.local/ECHANGES/STOCK';
$originPath = '/home/drive/ftp';
$backupStockPath = '/home/drive/ftp/backup/stock/';
$srcStockPath = '/home/drive/ftp/src/stock/';
$backupRefPath = '/home/drive/ftp/backup/ref/';
$srcRefPath = '/home/drive/ftp/src/ref/';
$backupPricePath = '/home/drive/ftp/backup/price/';
$srcPricePath = '/home/drive/ftp/src/price/';
$dirPathSrcProduct = '/home/drive/ftp/src/product';
if(!is_dir($dirPathSrcProduct)){
    mkdir($dirPathSrcProduct, 0755, true);
}

if($dossier = opendir($originPath))
{
    $nbStock = 0;
    $nbRef = 0;
    $nbPrice = 0;
    $bCopy = false;

    while(false !== ($fichier = readdir($dossier)))
    {
        $fileExploade = explode(".", $fichier);
        $prefix = explode("_", $fileExploade[0]);

        $f = "";
        if (strpos($prefix[1], '2000') === 0){
            $f = "2000/";
        }

        if (strpos($prefix[1], '2002') === 0){
            $f = "2002/";
        }

        if (strpos($prefix[1], '2001') === 0){
            $f = "2001/";
        }

        if (strpos($prefix[1], '2003') === 0){
            $f = "2003/";
        }

        if (isset($fileExploade[1]) && $fileExploade[1] && $prefix[0] && $prefix[0] == 'Stock'){

            copy($originPath.'/'.$fichier,$backupStockPath.$fichier);
            copy($originPath.'/'.$fichier,$srcStockPath.$f.'source_stock.csv');
            unlink($originPath.'/'.$fichier);
            $nbStock++;
            $bCopy = true;
            //echo "Copy Ok\n";
        }else {
            //echo "Pas de fichiers stock\n";
        }

        if (isset($fileExploade[1]) && $fileExploade[1] && $prefix[0] && $prefix[0] == 'Ref'){
            copy($originPath.'/'.$fichier,$backupRefPath.$fichier);
            copy($originPath.'/'.$fichier,$srcRefPath.$f.'source_ref.csv');
            unlink($originPath.'/'.$fichier);
            $nbRef++;
            $bCopy = true;
        }

	if (isset($fileExploade[1]) && $fileExploade[1] && $prefix[0] && $prefix[0] == 'price'){
            copy($originPath.'/'.$fichier,$backupPricePath.$fichier);

            if (file_exists($srcPricePath.$f.'source_price.csv')) {
                $file_tmp = file_get_contents($originPath.'/'.$fichier);
                $file_src = fopen($srcPricePath.$f.'source_price.csv','a+');
                fwrite($file_src, $file_tmp);
            } else {
                copy($originPath.'/'.$fichier,$srcPricePath.$f.'source_price.csv');
            }

            unlink($originPath.'/'.$fichier);
            $nbPrice++;
            $bCopy = true;
        }
    }

    if ($bCopy){
        echo "Copie terminée : \n";
        echo "Stock => ".$nbStock."\n";
        echo "Ref => ".$nbRef."\n";
        echo "Price => ".$nbPrice."\n";
    }else{
        echo "Aucun nouveau fichier.\n";
    }

}

?>
