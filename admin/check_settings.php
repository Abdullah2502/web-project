<?php
echo "<h3>Loaded Configuration File: " . php_ini_loaded_file() . "</h3>";
echo "POST Max Size: " . ini_get('post_max_size') . "<br>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
?>