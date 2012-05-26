<?php
  function runAsyncBatch($command, $filename)
    {
      $BatchFile = fopen("Power106.bat", 'w');
      fwrite($BatchFile, "@Echo off\r\n");
      fwrite($BatchFile, "Title $filename\r\n");
      fwrite($BatchFile, "$command\r\n");
      fwrite($BatchFile, "Pause\r\n");
      fclose($BatchFile);
      $WshShell = new COM("WScript.Shell");
      $oExec    = $WshShell->Run("Power106.bat", 1, false);
      unset($WshShell, $oExec);
    }

  function SafeFileName($filename)
    {
      $len = strlen($filename);
      for ($i = 0; $i < $len; $i++)
        {
          $char = ord($filename[$i]);
          if (($char < 32) || ($char >= 127))
              $filename = substr_replace($filename, ' ', $i, 1);
        }
      $filename = preg_replace('/[\/\\\?\*\:\|\<\>]/i', ' - ', $filename);
      $filename = preg_replace('/\s\s+/i', ' ', $filename);
      $filename = trim($filename);
      return $filename;
    }

  function ShowHeader($header)
    {
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  ShowHeader("KSV Power106 Downloader");
  $format = "%-8s : %s\n";

  if ($argc <= 2)
    {
      echo "Enter Channel ID : ";
      $channel_id = trim(fgets(STDIN));
      echo "Enter Asset ID   : ";
      $asset_id = trim(fgets(STDIN));
    }
  else
    {
      $channel_id = $argv[1];
      $asset_id   = $argv[2];
    }
  echo "Retrieving html . . .\n";

  $xml   = file_get_contents("http://player.vidaroo.com/initiate/render/channel_id/$channel_id/asset_id/$asset_id/embed_id/2105/log_embed_id/128210038");
  $xml   = simplexml_load_string($xml);
  $token = $xml->xpath('/rsp/msg/session/token');
  $token = (string) $token[0];
  printf($format, "Token", $token);

  $xml      = file_get_contents("http://player.vidaroo.com/render/asset/channel_id/$channel_id/asset_id/$asset_id/token/$token");
  $xml      = simplexml_load_string($xml);
  $title    = $xml->xpath('/rsp/msg/slot/video/asset/title');
  $title    = (string) $title[0];
  $filename = SafeFileName($title);
  $url      = $xml->xpath('/rsp/msg/slot/video/asset/@url');
  $url      = base64_decode((string) $url[0]);
  $host     = substr($url, 0, strpos($url, '/', 8));
  $app      = substr($url, strlen($host) + 1, strrpos($url, '/') - strlen($host));
  $playpath = substr(strrchr($url, '/'), 1);
  $command  = 'rtmpdump -r "' . "$host/$app" . '" -a "' . $app . '" -f "WIN 11,1,102,63" -W "http://assets.vidaroo.com/platform/1331760804/player/shell.swf" -p "http://www.power106.com/powertv/index.aspx" -C B:1 -y "mp4:' . $playpath . '" -o "' . $filename . '.flv"';

  printf($format, "Title", $title);
  printf($format, "Host", $host);
  printf($format, "App", $app);
  printf($format, "Playpath", $playpath);
  printf($format, "Command", $command);

  if ($playpath)
      runAsyncBatch($command, $filename);
  echo "Finished\n";
?>