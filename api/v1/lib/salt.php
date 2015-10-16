<?php

  function generate_salt($length)
  {
    $f = @fopen('/dev/urandom', 'r');
    if ($f) {
      $salt = fread($f, $length);
    } else {
      $salt = null;
    }
    return $salt;
  }
  
?>