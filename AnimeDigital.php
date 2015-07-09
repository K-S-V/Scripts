<?php
  if ($argc < 2)
      die("Usage: php AnimeDigital.php <encrypted_subtitles>");

  // Read encrypted subtitles
  $file = file_get_contents($argv[1]);
  $file = base64_decode($file);

  // Get decryption key from aes constants
  $constants = "\x66\x38\x64\x33\x66\x66\x32\x35\x37\x63\x32\x61\x32\x61\x30\x33\x66\x34\x62\x61\x33\x61\x37\x34\x34\x36\x33\x30\x30\x31\x65\x66\x64\x32\x38\x34\x38\x66\x64\x35\x32\x35\x36\x30\x36\x35\x32\x62\x65\x33\x62\x37\x33\x38\x31\x63\x35\x33\x38\x66\x35\x34\x30\x39\x65\x35\x39\x32\x31\x30\x66\x34\x63\x32\x30\x62\x63\x63\x66\x65\x30\x64\x30\x33\x30\x66\x33\x64\x34\x39\x62\x33\x65\x31\x39\x33\x38\x36\x62\x36\x62\x66\x66\x33\x30\x62\x33\x36\x36\x30\x37\x30";
  $start     = 56;
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
  $decrypted = mdecrypt_generic($td, $file);
  $decrypted = substr($decrypted, 32);
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
