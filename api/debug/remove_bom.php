<?php
// Function to remove BOM
function removeBOM($path) {
    if (!file_exists($path)) {
        echo "File not found: $path<br>";
        return;
    }
    $content = file_get_contents($path);
    $bom = pack('H*','EFBBBF');
    if (substr($content, 0, 3) === $bom) {
        $content = substr($content, 3);
        file_put_contents($path, $content);
        echo "BOM removed from: $path<br>";
    } else {
        echo "No BOM found in: $path<br>";
    }
}

removeBOM(__DIR__ . '/../cast/seat_operation.php');
removeBOM(__DIR__ . '/../../../common/config.php');
removeBOM(__DIR__ . '/../../../common/dbconnect.php');
