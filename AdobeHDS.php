<?php
  define('AUDIO', 0x08);
  define('VIDEO', 0x09);
  define('DATA', 0x12);

  class CLI
    {
      protected static $ACCEPTED = array(
          0 => array(
              'help'         => 'displays this help',
              'debug'        => 'show debug ouput',
              'no-frameskip' => 'do not filter any frames',
              'rename'       => 'rename fragments sequentially before processing'
          ),
          1 => array(
              'fragments' => 'base filename for fragments'
          )
      );
      var $params = array();

      public function __construct()
        {
          global $argc, $argv;

          // Parse params
          if ($argc > 1)
            {
              $doubleParam = false;
              for ($i = 1; $i < $argc; $i++)
                {
                  $arg     = $argv[$i];
                  $isparam = preg_match('/^--/', $arg);

                  if ($isparam)
                      $arg = preg_replace('/^--/', '', $arg);

                  if ($doubleParam && $isparam)
                    {
                      echo "[param] expected after '$doubleParam' switch (" . self::$ACCEPTED[1][$doubleParam] . ")\n";
                      exit(1);
                    }
                  else if (!$doubleParam && !$isparam)
                    {
                      if (!$GLOBALS['baseFilename'])
                          $GLOBALS['baseFilename'] = $arg;
                      else
                        {
                          echo "'$arg' is an invalid switch, use --help to display valid switches\n";
                          exit(1);
                        }
                    }
                  else if (!$doubleParam && $isparam)
                    {
                      if (isset($this->params[$arg]))
                        {
                          echo "'$arg' switch cannot occur more than once\n";
                          die;
                        }

                      $this->params[$arg] = true;
                      if (isset(self::$ACCEPTED[1][$arg]))
                          $doubleParam = $arg;
                      else if (!isset(self::$ACCEPTED[0][$arg]))
                        {
                          echo "there's no '$arg' switch, use --help to display all switches\n";
                          exit(1);
                        }
                    }
                  else if ($doubleParam && !$isparam)
                    {
                      $this->params[$doubleParam] = $arg;
                      $doubleParam                = false;
                    }
                }
            }

          // Final check
          foreach ($this->params as $k => $v)
              if (isset(self::$ACCEPTED[1][$k]) && $v === true)
                {
                  echo "[param] expected after '$k' switch (" . self::$ACCEPTED[1][$k] . ")\n";
                  die;
                }
        }

      public function getParam($name)
        {
          if (isset($this->params[$name]))
              return $this->params[$name];
          else
              return "";
        }

      public function displayHelp()
        {
          echo "You can use script with following switches: \n\n";
          foreach (self::$ACCEPTED[0] as $key => $value)
              printf(" --%-18s%s\n", $key, $value);
          foreach (self::$ACCEPTED[1] as $key => $value)
              printf(" --%-18s%s\n", $key . " [param]", $value);
        }
    }

  function ReadByte($str, $pos)
    {
      return intval(bin2hex(substr($str, $pos, 1)), 16);
    }

  function ReadInt24($str, $pos)
    {
      return intval(bin2hex(substr($str, $pos, 3)), 16);
    }

  function ReadInt32($str, $pos)
    {
      $int32 = unpack("N", substr($str, $pos, 4));
      return $int32[1];
    }

  function ReadString(&$frag, &$fragPos)
    {
      $strlen = 0;
      while ($frag[$fragPos] != 0x00)
          $strlen++;
      $str = substr($frag, $fragPos, $strlen);
      $fragPos += $strlen + 1;
      return $str;
    }

  function ReadBoxHeader(&$frag, &$fragPos, &$boxType, &$boxSize)
    {
      $boxSize = ReadInt32($frag, $fragPos);
      $boxType = substr($frag, $fragPos + 4, 4);
      if ($boxSize == 1)
        {
          $boxSize = ReadInt32($frag, $fragPos + 12) - 16;
          $fragPos += 16;
        }
      else
        {
          $boxSize -= 8;
          $fragPos += 8;
        }
    }

  function WriteByte(&$str, $pos, $int)
    {
      $str[$pos] = pack("C", $int);
    }

  function WriteInt24(&$str, $pos, $int)
    {
      $str[$pos]     = pack("C", ($int & 0xFF0000) >> 16);
      $str[$pos + 1] = pack("C", ($int & 0xFF00) >> 8);
      $str[$pos + 2] = pack("C", $int & 0xFF);
    }

  function WriteInt32(&$str, $pos, $int)
    {
      $str[$pos]     = pack("C", ($int & 0xFF000000) >> 24);
      $str[$pos + 1] = pack("C", ($int & 0xFF0000) >> 16);
      $str[$pos + 2] = pack("C", ($int & 0xFF00) >> 8);
      $str[$pos + 3] = pack("C", $int & 0xFF);
    }

  function DebugLog($msg)
    {
      global $debug;
      if ($debug)
          echo $msg . "\n";
    }

  function RenameFragments($baseFilename)
    {
      $files     = array();
      $fragCount = 0;
      $retries   = 0;

      while (true)
        {
          if ($retries >= 50)
              break;
          if (file_exists($baseFilename . ($fragCount + 1)))
            {
              $files[] = $baseFilename . ($fragCount + 1);
              $retries = 0;
            }
          else
              $retries += 1;
          $fragCount += 1;
        }

      $fragCount = count($files);
      natsort($files);
      for ($i = 0; $i < $fragCount; $i++)
          rename($files[$i], $baseFilename . ($i + 1));
    }

  echo "\nKSV Adobe HDS Downloader\n\n";
  $flvHeader    = pack("H*", "464c5601050000000900000000");
  $baseFilename = "";
  $debug        = false;
  $fileExt      = ".f4f";
  $tagHeaderLen = 11;
  $prevTagSize  = 4;
  $fragCount    = 0;
  $initialAP    = true;
  $initialVP    = true;
  $noFrameSkip  = false;
  $pAudioTS     = -1;
  $pVideoTS     = -1;
  $pAudioTagPos = 0;
  $pAudioTagLen = 0;
  $pVideoTagPos = 0;
  $pVideoTagLen = 0;

  $cli = new CLI();
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }
  if ($cli->getParam('fragments'))
      $baseFilename = $cli->getParam('fragments');
  if ($cli->getParam('debug'))
      $debug = true;
  if ($cli->getParam('no-frameskip'))
      $noFrameSkip = true;
  if ($cli->getParam('rename'))
      RenameFragments($baseFilename);

  $baseFilename != "" ? $outputFile = "$baseFilename.flv" : $outputFile = "Joined.flv";
  while (true)
    {
      if (file_exists("$baseFilename" . ($fragCount + 1) . $fileExt))
          $fragCount++;
      else if (file_exists("$baseFilename" . ($fragCount + 1)))
        {
          $fileExt = "";
          $fragCount++;
        }
      else
          break;
    }
  echo "Found $fragCount fragments\n";
  if ($fragCount)
    {
      $flv = fopen("$outputFile", "w+b");
      fwrite($flv, $flvHeader, 13);
    }
  else
      exit(1);

  for ($i = 1; $i <= $fragCount; $i++)
    {
      $firstAP = true;
      $firstVP = true;
      $fragLen = 0;
      $fragPos = 0;
      $mdat    = false;

      $frag    = file_get_contents("$baseFilename" . $i . $fileExt);
      $fragLen = strlen($frag);
      while (!$mdat and ($fragPos < $fragLen))
        {
          ReadBoxHeader($frag, $fragPos, $boxType, $boxSize);
          if ($boxType == "mdat")
            {
              $mdat    = true;
              $frag    = substr($frag, $fragPos, $boxSize);
              $fragPos = 0;
              $fragLen = $boxSize;
              break;
            }
          $fragPos += $boxSize;
        }
      if (!$mdat)
        {
          echo "Skipping fragment number $i\n";
          continue;
        }

      while ($fragPos < $fragLen)
        {
          $packetType  = ReadByte($frag, $fragPos);
          $packetSize  = ReadInt24($frag, $fragPos + 1);
          $packetTS    = ReadInt24($frag, $fragPos + 4);
          $totalTagLen = $tagHeaderLen + $packetSize + $prevTagSize;
          switch ($packetType)
          {
              case AUDIO:
                  if ($packetTS >= $pAudioTS)
                    {
                      if ($noFrameSkip or $initialAP or (!$firstAP and ($packetSize > 8)))
                        {
                          if (!$noFrameSkip and (($packetTS > 0) and ($packetTS == $pAudioTS)))
                            {
                              if ($totalTagLen <= $pAudioTagLen)
                                {
                                  DebugLog("Skipping overwrite of audio packet\nAUDIO\t$packetTS\t$pAudioTS\t$packetSize");
                                  break;
                                }
                              fseek($flv, $pAudioTagPos + $pAudioTagLen, SEEK_SET);
                              $data = fread($flv, 1048576);
                              fseek($flv, $pAudioTagPos, SEEK_SET);
                              fwrite($flv, $data);
                              ftruncate($flv, ftell($flv) + 1);
                              if ($pVideoTagPos > $pAudioTagPos)
                                  $pVideoTagPos -= $pAudioTagLen;
                            }
                          $pAudioTagPos = ftell($flv);
                          fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                          DebugLog("AUDIO\t$packetTS\t$pAudioTS\t$packetSize\t$pAudioTagPos\t1");
                          $pAudioTS     = $packetTS;
                          $pAudioTagLen = $totalTagLen;
                        }
                      else
                          DebugLog("Skipping small sized audio packet\nAUDIO\t$packetTS\t$pAudioTS\t$packetSize");
                      if ($initialAP)
                          $initialAP = false;
                      if ($firstAP)
                          $firstAP = false;
                    }
                  else
                      DebugLog("Skipping audio packet in fragment $i\nAUDIO\t$packetTS\t$pAudioTS\t$packetSize");
                  break;
              case VIDEO:
                  if ($packetTS >= $pVideoTS)
                    {
                      if ($noFrameSkip or $initialVP or (!$firstVP and ($packetSize > 0)))
                        {
                          if (!$noFrameSkip and (($packetTS > 0) and ($packetTS == $pVideoTS)))
                            {
                              if ($totalTagLen <= $pVideoTagLen)
                                {
                                  DebugLog("Skipping overwrite of video packet\nVIDEO\t$packetTS\t$pVideoTS\t$packetSize");
                                  break;
                                }
                              fseek($flv, $pVideoTagPos + $pVideoTagLen, SEEK_SET);
                              $data = fread($flv, 1048576);
                              fseek($flv, $pVideoTagPos, SEEK_SET);
                              fwrite($flv, $data);
                              ftruncate($flv, ftell($flv) + 1);
                              if ($pAudioTagPos > $pVideoTagPos)
                                  $pAudioTagPos -= $pVideoTagLen;
                            }
                          $pVideoTagPos = ftell($flv);
                          fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                          DebugLog("VIDEO\t$packetTS\t$pVideoTS\t$packetSize\t$pVideoTagPos\t1");
                          $pVideoTS     = $packetTS;
                          $pVideoTagLen = $totalTagLen;
                        }
                      else
                          DebugLog("Skipping small sized video packet\nVIDEO\t$packetTS\t$pVideoTS\t$packetSize");
                      if ($initialVP)
                          $initialVP = false;
                      if ($firstVP)
                          $firstVP = false;
                    }
                  else
                      DebugLog("Skipping video packet in fragment $i\nVIDEO\t$packetTS\t$pVideoTS\t$packetSize");
                  break;
              case DATA:
                  break;
          }
          $fragPos += $totalTagLen;
        }
      echo "Processed $i fragments\r";
    }

  fclose($flv);
  echo "\nFinished\n";
?>