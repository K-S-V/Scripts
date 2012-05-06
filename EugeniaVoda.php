<?php
  echo "\nKSV EugeniaVoda Downloader\n\n";
  $format = "%-7s : %s\n";

  if ($argc <= 2)
    {
      echo "Enter URL : ";
      $url = trim(fgets(STDIN));
      echo "Enter Filename : ";
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
  fwrite($fh, "\x46\x4C\x56\x01\x05\x00\x00\x00\x09\x00\x00\x00\x00");
  $total_chunks = count($chunks);
  for ($i = 0; $i < $total_chunks; $i++)
    {
      echo "Downloading " . ($i + 1) . " of $total_chunks chunks\r";
      $data = file_get_contents($url . "/$chunks[$i].flvtags");
      fwrite($fh, $data);
    }
  fclose($fh);

  echo "\nFinished\n";
?>