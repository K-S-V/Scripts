<?php
  function hexlify($str)
    {
      $str = unpack("H*", $str);
      $str = $str[1];
      return $str;
    }

  function LogDebug($msg, $display = true)
    {
      global $debug, $showHeader;
      if ($showHeader)
        {
          ShowHeader();
          $showHeader = false;
        }
      if ($display and $debug)
          fwrite(STDERR, $msg . "\n");
    }

  function LogError($msg, $code = 1)
    {
      LogInfo($msg);
      exit($code);
    }

  function LogInfo($msg, $progress = false)
    {
      global $quiet, $showHeader;
      if ($showHeader)
        {
          ShowHeader();
          $showHeader = false;
        }
      if (!$quiet)
          PrintLine($msg, $progress);
    }

  function PrintLine($msg, $progress = false)
    {
      if ($msg)
        {
          printf("\r%-79s\r", "");
          if ($progress)
              printf("%s\r", $msg);
          else
              printf("%s\n", $msg);
        }
      else
          printf("\n");
    }

  function ShowHeader()
    {
      $header = "KSV UVD Converter";
      $len    = strlen($header);
      $width  = floor((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  // Global code starts here
  $debug      = true;
  $encrypted  = false;
  $showHeader = true;

  if ($argc < 5)
      die("Usage: php UVDConvert.php <m3u8_file> <ts.prdy_file> <key_file> <output_file>");

  // Read .m3u8 file to retrieve encrypted blob start location and size
  $m3u8 = file($argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($m3u8 === false)
      die("Failed to open m3u8 file.");
  foreach ($m3u8 as $line)
    {
      if (strncasecmp("#EXT-X-KEY", $line, 10) == 0)
          $encrypted = true;
      if (strncasecmp("#EXT-X-BYTERANGE", $line, 16) == 0)
        {
          $range = explode(':', $line, 2);
          $range = $range[1];
          $range = explode('@', trim($range));
          $t =& $byteRange[];
          $t['start'] = trim($range[1]);
          $t['len']   = trim($range[0]);
        }
    }

  // Read encryption key
  $key = file_get_contents($argv[3]);
  if ($key === false)
      die("Failed to open key file.");
  $iv = $key;
  LogDebug("Key: " . hexlify($key));
  LogDebug("IV : " . hexlify($iv));

  // Retrieve and decrypt encrypted blobs
  $decData = "";
  $td      = mcrypt_module_open('rijndael-128', '', 'cbc', '');
  mcrypt_generic_init($td, $key, $iv);
  $input = fopen($argv[2], 'rb');
  if ($input === false)
      die("Failed to open input file.");
  $output = fopen($argv[4], 'wb');
  if ($output === false)
      die("Failed to open output file.");
  foreach ($byteRange as $range)
    {
      fseek($input, $range['start']);
      $encData = fread($input, $range['len']);
      if ($encrypted)
        {
          $str = mdecrypt_generic($td, $encData);

          // Detect and remove PKCS#7 padding
          $padded = true;
          $len    = strlen($str);
          $pad    = ord($str[$len - 1]);
          for ($i = 1; $i <= $pad; $i++)
              $padded &= ($pad == ord(substr($str, -$i, 1))) ? true : false;
          if ($padded)
              $str = substr($str, 0, $len - $pad);

          $decData = $str;
        }
      else
          $decData = $encData;
      fwrite($output, $decData);
    }
?>
