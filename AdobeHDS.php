<?php
  define('AUDIO', 0x08);
  define('VIDEO', 0x09);
  define('SCRIPT_DATA', 0x12);
  define('FRAME_TYPE_INFO', 0x05);
  define('CODEC_ID_AVC', 0x07);
  define('CODEC_ID_AAC', 0x0A);
  define('AVC_SEQUENCE_HEADER', 0x00);
  define('AAC_SEQUENCE_HEADER', 0x00);

  class CLI
    {
      protected static $ACCEPTED = array(
          0 => array(
              'help'         => 'displays this help',
              'debug'        => 'show debug ouput',
              'delete'       => 'delete fragments after processing',
              'no-frameskip' => 'do not filter any frames',
              'rename'       => 'rename fragments sequentially before processing'
          ),
          1 => array(
              'fragments' => 'base filename for fragments',
              'manifest'  => 'manifest file for downloading of fragments',
              'quality'   => 'selected quality level (low|medium|high) or exact bitrate'
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

  function ShowHeader($headers)
    {
      $len    = strlen($headers);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $headers);
    }

  function KeyName(array $a, $pos)
    {
      $temp = array_slice($a, $pos, 1, true);
      return key($temp);
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

  function ReadString($frag, &$fragPos)
    {
      $strlen = 0;
      while ($frag[$fragPos] != 0x00)
          $strlen++;
      $str = substr($frag, $fragPos, $strlen);
      $fragPos += $strlen + 1;
      return $str;
    }

  function ReadBoxHeader($frag, &$fragPos, &$boxType, &$boxSize)
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
          fwrite(STDERR, $msg . "\n");
    }

  function RenameFragments($baseFilename, $fileExt)
    {
      $files     = array();
      $fragCount = 0;
      $retries   = 0;

      if (!file_exists($baseFilename . ($fragCount + 1) . $fileExt))
          $fileExt = "";
      while (true)
        {
          if ($retries >= 50)
              break;
          $file = $baseFilename . ++$fragCount . $fileExt;
          if (file_exists($file))
            {
              $files[] = $file;
              $retries = 0;
            }
          else
              $retries++;
        }

      $fragCount = count($files);
      natsort($files);
      for ($i = 0; $i < $fragCount; $i++)
          rename($files[$i], $baseFilename . ($i + 1) . $fileExt);
    }

  function in_array_field($needle, $needle_field, $haystack, $strict = false)
    {
      if ($strict)
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) && $item[$needle_field] === $needle)
                  return true;
        }
      else
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) && $item[$needle_field] == $needle)
                  return true;
        }
      return false;
    }

  function value_array_field($needle, $needle_field, $value_field, $haystack, $strict = false)
    {
      if ($strict)
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) && $item[$needle_field] === $needle)
                  return $item[$value_field];
        }
      else
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) && $item[$needle_field] == $needle)
                  return $item[$value_field];
        }
      return false;
    }

  function ParseBootstrapBox($bootstrapInfo, $pos)
    {
      $version             = ReadByte($bootstrapInfo, $pos);
      $flags               = ReadInt24($bootstrapInfo, $pos + 1);
      $bootstrapVersion    = ReadInt32($bootstrapInfo, $pos + 4);
      $byte                = ReadByte($bootstrapInfo, $pos + 8);
      $profile             = ($byte & 0xC0) >> 6;
      $live                = ($byte & 0x20) >> 5;
      $update              = ($byte & 0x10) >> 4;
      $timescale           = ReadInt32($bootstrapInfo, $pos + 9);
      $currentMediaTime    = ReadInt32($bootstrapInfo, 17);
      $smpteTimeCodeOffset = ReadInt32($bootstrapInfo, 25);
      $pos += 29;
      $movieIdentifier  = ReadString($bootstrapInfo, $pos);
      $serverEntryCount = ReadByte($bootstrapInfo, $pos++);
      for ($i = 0; $i < $serverEntryCount; $i++)
          $serverEntryTable[$i] = ReadString($bootstrapInfo, $pos);
      $qualityEntryCount = ReadByte($bootstrapInfo, $pos++);
      for ($i = 0; $i < $qualityEntryCount; $i++)
          $qualityEntryTable[$i] = ReadString($bootstrapInfo, $pos);
      $drmData              = ReadString($bootstrapInfo, $pos);
      $metadata             = ReadString($bootstrapInfo, $pos);
      $segmentRunTableCount = ReadByte($bootstrapInfo, $pos++);
      for ($i = 0; $i < $segmentRunTableCount; $i++)
        {
          ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
          if ($boxType == "asrt")
              ParseAsrtBox($bootstrapInfo, $pos);
          $pos += $boxSize;
        }
      $fragmentRunTableCount = ReadByte($bootstrapInfo, $pos++);
      for ($i = 0; $i < $fragmentRunTableCount; $i++)
        {
          ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
          if ($boxType == "afrt")
              ParseAfrtBox($bootstrapInfo, $pos);
          $pos += $boxSize;
        }
    }

  function ParseAsrtBox($asrt, $pos)
    {
      global $fragCount;
      $version           = ReadByte($asrt, $pos);
      $flags             = ReadInt24($asrt, $pos + 1);
      $qualityEntryCount = ReadByte($asrt, $pos + 4);
      $pos += 5;
      for ($i = 0; $i < $qualityEntryCount; $i++)
        {
          $qualitySegmentUrlModifiers[$i] = ReadString($asrt, $pos);
        }
      $segCount = ReadInt32($asrt, $pos);
      $pos += 4;
      DebugLog("Segment Entries:\n\nNumber\tFragments");
      for ($i = 0; $i < $segCount; $i++)
        {
          $segTable[$i]['firstSegment']        = ReadInt32($asrt, $pos);
          $segTable[$i]['fragmentsPerSegment'] = ReadInt32($asrt, $pos + 4);
          $pos += 8;
          DebugLog($segTable[$i]['firstSegment'] . "\t" . $segTable[$i]['fragmentsPerSegment']);
        }
      DebugLog("");
      $fragCount = $segTable[0]['fragmentsPerSegment'];
    }

  function ParseAfrtBox($afrt, $pos)
    {
      global $fragTable;
      $version           = ReadByte($afrt, $pos);
      $flags             = ReadInt24($afrt, $pos + 1);
      $timescale         = ReadInt32($afrt, $pos + 4);
      $qualityEntryCount = ReadByte($afrt, $pos + 8);
      $pos += 9;
      for ($i = 0; $i < $qualityEntryCount; $i++)
        {
          $qualitySegmentUrlModifiers[$i] = ReadString($afrt, $pos);
        }
      $fragCount = ReadInt32($afrt, $pos);
      $pos += 4;
      DebugLog("Fragment Entries:\n\nNumber\tTimestamp\tDuration\tDiscontinuity");
      for ($i = 0; $i < $fragCount; $i++)
        {
          $fragTable[$i]['firstFragment']          = ReadInt32($afrt, $pos);
          $fragTable[$i]['firstFragmentTimestamp'] = ReadInt32($afrt, $pos + 8);
          $fragTable[$i]['fragmentDuration']       = ReadInt32($afrt, $pos + 12);
          $fragTable[$i]['discontinuityIndicator'] = "";
          $pos += 16;
          if ($fragTable[$i]['fragmentDuration'] == 0)
              $fragTable[$i]['discontinuityIndicator'] = ReadByte($afrt, $pos++);
          DebugLog($fragTable[$i]['firstFragment'] . "\t" . $fragTable[$i]['firstFragmentTimestamp'] . "\t\t" . $fragTable[$i]['fragmentDuration'] . "\t\t" . $fragTable[$i]['discontinuityIndicator']);
        }
      DebugLog("");
    }

  function ParseManifest($manifest)
    {
      global $media, $quality;
      $xml = simplexml_load_file($manifest);
      $xml->registerXPathNamespace("ns", "http://ns.adobe.com/f4m/1.0");
      $streams = $xml->xpath("/ns:manifest/ns:media");
      foreach ($streams as $stream)
        {
          $bitrate   = (int) $stream['bitrate'];
          $bootstrap = $xml->xpath("/ns:manifest/ns:bootstrapInfo[@id='" . $stream['bootstrapInfoId'] . "']");
          $metadata  = $xml->xpath("/ns:manifest/ns:media[@bitrate=" . $bitrate . "]/ns:metadata");
          if (isset($metadata[0]))
              $media[$bitrate]['metadata'] = trim((string) $metadata[0]);
          else
              $media[$bitrate]['metadata'] = "";
          $media[$bitrate]['bootstrap'] = trim((string) $bootstrap[0]);
          $media[$bitrate]['url']       = trim((string) $stream['url']);
        }

      krsort($media, SORT_NUMERIC);
      if (is_numeric($quality))
          $media = $media[$quality];
      else
        {
          $quality = strtolower($quality);
          if ($quality == "high")
              $quality = 0;
          else if ($quality == "medium")
              $quality = 1;
          else if ($quality == "low")
              $quality = 2;
          while (true)
            {
              $key = KeyName($media, $quality);
              if ($key)
                {
                  $media = $media[$key];
                  break;
                }
              else
                  $quality -= 1;
            }
        }

      $bootstrapInfo = base64_decode($media['bootstrap']);
      ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
      if ($boxType == "abst")
          ParseBootstrapBox($bootstrapInfo, $pos);
      else
          die("Failed to parse bootstrap info");
    }

  function DownloadFragments($manifest)
    {
      global $baseFilename, $fragCount, $fragTable, $media, $rename;
      ParseManifest($manifest);
      $baseUrl      = substr($manifest, 0, strrpos($manifest, '/'));
      $baseFilename = $media['url'] . "Seg1-Frag";
      for ($i = 1; $i <= $fragCount; $i++)
        {
          echo "Downloading fragment $i/$fragCount\r";
          if (in_array_field($i, "firstFragment", $fragTable, true))
            {
              $discontinuity = value_array_field($i, "firstFragment", "discontinuityIndicator", $fragTable, true);
              if (($discontinuity == 1) or ($discontinuity == 3))
                {
                  $rename = true;
                  continue;
                }
            }
          if (!file_exists("$baseFilename$i"))
            {
              $data = @file_get_contents("$baseUrl/$baseFilename$i");
              if ($data != false)
                  file_put_contents("$baseFilename$i", $data);
              else
                  $rename = true;
            }
        }
      echo "\n";
    }

  ShowHeader("KSV Adobe HDS Downloader");
  $flvHeader    = pack("H*", "464c5601050000000900000000");
  $format       = "%s\t%s\t\t%s\t\t%s";
  $baseFilename = "";
  $debug        = false;
  $delete       = false;
  $fileExt      = ".f4f";
  $noFrameSkip  = false;
  $quality      = "high";
  $rename       = false;
  $prevTagSize  = 4;
  $tagHeaderLen = 11;
  $prevAudioTS  = -1;
  $prevVideoTS  = -1;
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
  if ($cli->getParam('delete'))
      $delete = true;
  if ($cli->getParam('no-frameskip'))
      $noFrameSkip = true;
  if ($cli->getParam('quality'))
      $quality = $cli->getParam('quality');
  if ($cli->getParam('manifest'))
      DownloadFragments($cli->getParam('manifest'));
  if ($cli->getParam('rename') or $rename)
      RenameFragments($baseFilename, $fileExt);

  $fragCount = 0;
  $baseFilename != "" ? $outputFile = "$baseFilename.flv" : $outputFile = "Joined.flv";
  while (true)
    {
      if (file_exists($baseFilename . ($fragCount + 1) . $fileExt))
          $fragCount++;
      else if (file_exists($baseFilename . ($fragCount + 1)))
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
      if (isset($media) and $media['metadata'])
        {
          $media['metadata'] = base64_decode($media['metadata']);
          $metadataSize      = strlen($media['metadata']);
          WriteByte($metadata, 0, SCRIPT_DATA);
          WriteInt24($metadata, 1, $metadataSize);
          WriteInt24($metadata, 4, 0);
          WriteInt32($metadata, 7, 0);
          $metadata = implode("", $metadata) . $media['metadata'];
          WriteByte($metadata, $tagHeaderLen + $metadataSize - 1, 0x09);
          WriteInt32($metadata, $tagHeaderLen + $metadataSize, $tagHeaderLen + $metadataSize);
          fwrite($flv, $metadata, $tagHeaderLen + $metadataSize + $prevTagSize);
        }
    }
  else
      exit(1);

  $prevAVC_Header    = false;
  $prevAAC_Header    = false;
  $AVC_HeaderWritten = false;
  $AAC_HeaderWritten = false;
  $timeStart         = microtime(true);
  for ($i = 1; $i <= $fragCount; $i++)
    {
      $fragLen = 0;
      $fragPos = 0;
      $mdat    = false;

      $frag    = file_get_contents($baseFilename . $i . $fileExt);
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

      DebugLog(sprintf("%s\t%s\t%s\t%s\t\t%s", "Type", "CurrentTS", "PreviousTS", "Size", "Position"));
      while ($fragPos < $fragLen)
        {
          $packetType  = ReadByte($frag, $fragPos);
          $packetSize  = ReadInt24($frag, $fragPos + 1);
          $packetTS    = ReadInt24($frag, $fragPos + 4);
          $totalTagLen = $tagHeaderLen + $packetSize + $prevTagSize;
          switch ($packetType)
          {
              case AUDIO:
                  if ($packetTS >= $prevAudioTS)
                    {
                      $FrameInfo = ReadByte($frag, $fragPos + $tagHeaderLen);
                      $CodecID   = ($FrameInfo & 0xF0) >> 4;
                      if ($CodecID == CODEC_ID_AAC)
                        {
                          $AAC_PacketType = ReadByte($frag, $fragPos + $tagHeaderLen + 1);
                          if (($AAC_PacketType == AAC_SEQUENCE_HEADER) and $AAC_HeaderWritten)
                            {
                              DebugLog(sprintf($format, "Skipping AAC sequence header\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
                              break;
                            }
                          else
                            {
                              $AAC_HeaderWritten = true;
                              $prevAAC_Header    = true;
                            }
                        }
                      if ($noFrameSkip or ($packetSize > 0))
                        {
                          // Check for packets with monotonic timestamps and presereve the larger audio packet
                          if (!$noFrameSkip and (!$prevAAC_Header) and ($packetTS == $prevAudioTS))
                            {
                              if ($totalTagLen <= $pAudioTagLen)
                                {
                                  DebugLog(sprintf($format, "Skipping overwrite of audio packet\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
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
                          if (($CodecID == CODEC_ID_AAC) and ($AAC_PacketType != AAC_SEQUENCE_HEADER))
                              $prevAAC_Header = false;
                          $pAudioTagPos = ftell($flv);
                          fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                          DebugLog(sprintf($format, "AUDIO", $packetTS, $prevAudioTS, $packetSize . "\t\t" . $pAudioTagPos));
                          $prevAudioTS  = $packetTS;
                          $pAudioTagLen = $totalTagLen;
                        }
                      else
                          DebugLog(sprintf($format, "Skipping small sized audio packet\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
                    }
                  else
                      DebugLog(sprintf($format, "Skipping audio packet in fragment $i\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
                  break;
              case VIDEO:
                  if ($packetTS >= $prevVideoTS)
                    {
                      $FrameInfo = ReadByte($frag, $fragPos + $tagHeaderLen);
                      $FrameType = ($FrameInfo & 0xF0) >> 4;
                      $CodecID   = $FrameInfo & 0x0F;
                      if ($FrameType == FRAME_TYPE_INFO)
                        {
                          DebugLog(sprintf($format, "Skipping video info frame\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                          break;
                        }
                      if ($CodecID == CODEC_ID_AVC)
                        {
                          $AVC_PacketType = ReadByte($frag, $fragPos + $tagHeaderLen + 1);
                          if (($AVC_PacketType == AVC_SEQUENCE_HEADER) and $AVC_HeaderWritten)
                            {
                              DebugLog(sprintf($format, "Skipping AVC sequence header\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                              break;
                            }
                          else
                            {
                              $AVC_HeaderWritten = true;
                              $prevAVC_Header    = true;
                            }
                        }
                      if ($noFrameSkip or ($packetSize > 0))
                        {
                          // Check for packets with monotonic timestamps and presereve the larger video packet
                          if (!$noFrameSkip and (!$prevAVC_Header) and ($packetTS == $prevVideoTS))
                            {
                              if ($totalTagLen <= $pVideoTagLen)
                                {
                                  DebugLog(sprintf($format, "Skipping overwrite of video packet\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
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
                          if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType != AVC_SEQUENCE_HEADER))
                              $prevAVC_Header = false;
                          $pVideoTagPos = ftell($flv);
                          fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                          DebugLog(sprintf($format, "VIDEO", $packetTS, $prevVideoTS, $packetSize . "\t\t" . $pVideoTagPos));
                          $prevVideoTS  = $packetTS;
                          $pVideoTagLen = $totalTagLen;
                        }
                      else
                          DebugLog(sprintf($format, "Skipping small sized video packet\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                    }
                  else
                      DebugLog(sprintf($format, "Skipping video packet in fragment $i\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                  break;
              case SCRIPT_DATA:
                  break;
          }
          $fragPos += $totalTagLen;
        }
      echo "Processed $i fragments\r";
    }

  fclose($flv);
  $timeEnd   = microtime(true);
  $timeTaken = sprintf("%.2f", $timeEnd - $timeStart);
  echo "Joined $fragCount fragments in $timeTaken seconds\n";

  if ($delete)
      for ($i = 1; $i <= $fragCount; $i++)
        {
          if (file_exists($baseFilename . $i . $fileExt))
              unlink($baseFilename . $i . $fileExt);
        }
  echo "Finished\n";
?>