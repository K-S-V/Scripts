<?php
  if ($argc < 2)
      die("Usage: php AnimeDigital.php <encrypted_subtitles>");

  // Read encrypted subtitles
  $file = file_get_contents($argv[1]);
  $file = base64_decode($file);

  // Get decryption key from aes constants
  $constants = "\x31\x65\x30\x30\x66\x64\x33\x61\x38\x32\x30\x65\x62\x31\x66\x38\x35\x36\x36\x39\x38\x38\x36\x65\x37\x34\x62\x66\x35\x37\x35\x62\x61\x35\x61\x66\x39\x33\x31\x36\x61\x31\x30\x63\x37\x64\x35\x35\x36\x30\x62\x61\x66\x31\x64\x62\x38\x64\x65\x64\x61\x32\x33\x37\x31\x63\x31\x35\x33\x37\x61\x61\x64\x66\x62\x30\x37\x66\x30\x31\x61\x36\x65\x38\x34\x61\x63\x31\x34\x65\x36\x65\x61\x66\x66\x61\x61\x31\x31\x39\x30\x37";
  $start     = 44;
  $key       = substr($constants, $start, 32);
  $salt      = substr($file, 8, 8);
  $key       = $key . $salt;
  $hash1     = md5($key, true);
  $hash2     = md5($hash1 . $key, true);
  $iv        = md5($hash2 . $key, true);
  $key       = $hash1 . $hash2;

  // Decrypt subtitles
  $td = mcrypt_module_open('rijndael-128', '', 'cbc', '');
  mcrypt_generic_init($td, $key, $iv);
  $file      = substr($file, 16);
  $decrypted = mdecrypt_generic($td, $file);
  mcrypt_generic_deinit($td);
  mcrypt_module_close($td);

  // Detect and remove PKCS#7 padding
  $padded = true;
  $len    = strlen($decrypted);
  $pad    = ord($decrypted[$len - 1]);
  for ($i = 1; $i <= $pad; $i++)
      $padded &= ($pad == ord(substr($decrypted, -$i, 1))) ? true : false;
  if ($padded)
      $decrypted = substr($decrypted, 0, $len - $pad);

  // Save decrypted subtitles
  $file = pathinfo($argv[1], PATHINFO_FILENAME) . ".ass";
  file_put_contents($file, $decrypted);
?>
