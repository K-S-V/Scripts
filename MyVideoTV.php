<?php
  function ShowHeader($header)
    {
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  ShowHeader("KSV MyVideoTV Downloader");

  /* Open the cipher */
  $td = mcrypt_module_open('arcfour', '', 'stream', '');
  $iv = "";

  /* Create key */
  $id  = $argv[1];
  $key = md5("c8407a08b3c71ea418ec9dc662f2a56e40cbd6d5a114aa50fb1e1079e17f2b83" . md5($id));

  /* Intialize encryption */
  mcrypt_generic_init($td, $key, $iv);

  /* Encrypted data */
  $enc_xml   = file_get_contents("http://www.myvideo.de/dynamic/get_player_video_xml.php?ID=$id&flash_playertype=D&autorun=yes");
  $enc_xml   = explode("=", $enc_xml, 2);
  $enc_xml   = $enc_xml[1];
  $encrypted = pack("H*", $enc_xml);

  /* Decrypt encrypted string */
  $decrypted = mdecrypt_generic($td, $encrypted);

  /* Terminate decryption handle and close module */
  mcrypt_generic_deinit($td);
  mcrypt_module_close($td);

  /* Show info */
  $xml          = simplexml_load_string($decrypted);
  $video_params = $xml->{"playlist"}->{"videos"}->{"video"}->attributes();
  echo "Title    : " . rawurldecode($video_params->{"title"}) . "\n";
  echo "RTMP     : " . rawurldecode($video_params->{"connectionurl"}) . "\n";
  echo "Playpath : " . rawurldecode($video_params->{"source"}) . "\n";
  echo "HTTP     : " . rawurldecode($video_params->{"path"} . $video_params->{"source"}) . "\n";
?>