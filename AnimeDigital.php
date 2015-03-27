<?php
  if ($argc < 2)
      die("Usage: php AnimeDigital.php <encrypted_subtitles>");

  // Read encrypted subtitles
  $file = file_get_contents($argv[1]);
  $file = base64_decode($file);

  // Get initial key from aes constants
  $constants = "\x65\x61\x62\x30\x32\x33\x65\x37\x66\x64\x63\x36\x65\x38\x63\x36\x39\x30\x38\x65\x31\x31\x36\x62\x63\x35\x35\x30\x61\x61\x37\x30\x39\x39\x64\x34\x36\x66\x38\x36\x36\x34\x33\x36\x63\x35\x61\x37\x36\x33\x62\x30\x66\x62\x66\x62\x37\x33\x61\x32\x33\x65\x63\x65\x62\x38\x64\x33\x31\x33\x66\x37\x37\x32\x32\x61\x66\x34\x37\x64\x34\x35\x33\x30\x62\x64\x62\x64\x39\x36\x64\x39\x37\x30\x64\x38\x38\x32\x31\x64\x30\x37\x30\x37\x64\x32\x38\x34\x62\x35\x38\x35\x66\x66\x35";
  $start     = 31;
  $key       = substr($constants, $start, 32);

  // Generate key
  $td  = mcrypt_module_open('rijndael-128', '', 'ecb', '');
  $iv  = str_repeat("\x00", 16);
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
