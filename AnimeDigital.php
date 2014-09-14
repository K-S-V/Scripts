<?php
  if ($argc < 2)
      die("Usage: php AnimeDigital.php <encrypted_subtitles>");

  // Read encrypted subtitles
  $file = file_get_contents($argv[1]);
  $file = base64_decode($file);

  // Generate key
  $td  = mcrypt_module_open('rijndael-128', '', 'ecb', '');
  $iv  = str_repeat("\x00", 16);
  $key = pack("C*", 48, 99, 101, 102, 56, 56, 56, 101, 56, 48, 99, 51, 49, 48, 57, 49, 54, 102, 55, 101, 97, 98, 100, 97, 54, 100, 51, 97, 51, 98, 57, 52);
  $key = $key . str_repeat("\x00", 32 - strlen($key));
  mcrypt_generic_init($td, $key, $iv);
  $key = mcrypt_generic($td, $key);
  $key = str_repeat(substr($key, 0, 16), 2);
  mcrypt_generic_deinit($td);

  /* PHP CTR mode wasn't working as intended so have to decrypt manually */

  // Generate nonce for decryption
  $encrypted = substr($file, 8);
  $encLen    = strlen($encrypted);
  $blocks    = ceil($encLen / 16);
  $nonce     = substr($file, 0, 8) . pack('N', 0);

  // Decrypt subtitles
  $decrypted = " ";
  mcrypt_generic_init($td, $key, $iv);
  for ($i = 0; $i < $blocks; $i++)
    {
      $enc = $nonce . pack('N', $i);
      $dec = mcrypt_generic($td, $enc);
      for ($j = 0; $j < 16; $j++)
        {
          if (!isset($encrypted[($i * 16) + $j]))
              break;
          $decrypted[($i * 16) + $j] = $dec[$j] ^ $encrypted[($i * 16) + $j];
        }
    }
  mcrypt_generic_deinit($td);
  mcrypt_module_close($td);

  // Save decrypted subtitles
  $file = pathinfo($argv[1], PATHINFO_FILENAME) . ".ass";
  file_put_contents($file, $decrypted);
?>
