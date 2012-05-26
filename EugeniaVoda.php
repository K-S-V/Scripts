<?php
  function ShowHeader($header)
    {
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  ShowHeader("KSV EugeniaVoda Downloader");
  $format = "%-8s : ";

  if ($argc <= 2)
    {
      printf($format, "URL");
      $url = trim(fgets(STDIN));
      printf($format, "Filename");
      $filename = trim(fgets(STDIN));
    }
  else
    {
      $url      = $argv[1];
      $filename = $argv[2];
    }

  echo "Retrieving data . . .\n";
  $json   = file_get_contents($url . "/offsets.json");
  $chunks = json_decode($json);
  if (!$chunks)
      die("Failed to decode json");
  $fh = fopen($filename, 'wb');
  fwrite($fh, pack("H*", "464C5601050000000900000000"));
  $total_chunks = count($chunks);
  for ($i = 0; $i < $total_chunks; $i++)
    {
      echo "Downloading " . ($i + 1) . "/$total_chunks chunks\r";
      $data = file_get_contents($url . "/$chunks[$i].flvtags");
      fwrite($fh, $data);
    }
  fclose($fh);

  echo "\nFinished\n";
?>