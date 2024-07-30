<?php 

if (isset($_GET['filename'])) {
   $filename = $_GET['filename'];
   $file_path = 'static/hapaxes/' . $filename;
   if (file_exists($file_path)) {
      $hapaxes = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      echo json_encode($hapaxes);
   } else {
      echo json_encode(["error" => "File not found"]);
   }
}

?>
