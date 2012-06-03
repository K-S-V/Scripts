<?php
  define('AUDIO', 0x08);
  define('VIDEO', 0x09);
  define('SCRIPT_DATA', 0x12);
  define('FRAME_TYPE_INFO', 0x05);
  define('CODEC_ID_AVC', 0x07);
  define('CODEC_ID_AAC', 0x0A);
  define('AVC_SEQUENCE_HEADER', 0x00);
  define('AAC_SEQUENCE_HEADER', 0x00);
  define('AVC_SEQUENCE_END', 0x02);
  define('TIMECODE_DURATION', 8);

  class CLI
    {
      protected static $ACCEPTED = array(
          0 => array(
              'help'  => 'displays this help',
              'debug' => 'show debug ouput'
          ),
          1 => array(
              'in'  => 'input filename of flv file to be repaired',
              'out' => 'output filename for repaired file'
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
              printf(" --%-12s%s\n", $key, $value);
          foreach (self::$ACCEPTED[1] as $key => $value)
              printf(" --%-3s%-9s%s\n", $key, " [param]", $value);
        }
    }

  function ShowHeader($header)
    {
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
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

  ShowHeader("KSV FLV Fixer");
  $flvHeader    = pack("H*", "464c5601050000000900000000");
  $flvHeaderLen = strlen($flvHeader);
  $format       = "%s\t%s\t\t%s\t\t%s";
  $debug        = false;
  $prevTagSize  = 4;
  $tagHeaderLen = 11;
  $prevAudioTS  = -1;
  $prevVideoTS  = -1;
  $pAudioTagLen = 0;
  $pVideoTagLen = 0;
  $pAudioTagPos = 0;
  $pVideoTagPos = 0;

  $cli = new CLI();
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }
  if ($cli->getParam('debug'))
      $debug = true;
  if ($cli->getParam('in'))
      $in = $cli->getParam('in');
  else
      die("You must specify an input file\n");
  if ($cli->getParam('out'))
      $out = $cli->getParam('out');
  else
      die("You must specify an output file\n");

  $prevAVC_Header    = false;
  $prevAAC_Header    = false;
  $AVC_HeaderWritten = false;
  $AAC_HeaderWritten = false;
  $timeStart         = microtime(true);

  if (file_exists($in))
    {
      $flvIn  = fopen($in, "rb");
      $flvTag = fread($flvIn, $flvHeaderLen);
      if (strncmp($flvTag, "FLV", 3) != 0)
          die("Input file is not a valid FLV file\n");
      $fileLen  = filesize($in);
      $filePos  = $flvHeaderLen;
      $fileSize = $fileLen / (1024 * 1024);
      $pFilePos = 0;
    }
  else
      die("Input file doesn't exist\n");
  $flvOut = fopen($out, "w+b");
  fwrite($flvOut, $flvHeader, $flvHeaderLen);

  DebugLog(sprintf("%s\t%s\t%s\t%s\t\t%s", "Type", "CurrentTS", "PreviousTS", "Size", "Position"));
  while ($filePos < $fileLen)
    {
      $flvTag     = fread($flvIn, $tagHeaderLen);
      $tagPos     = 0;
      $packetType = ReadByte($flvTag, $tagPos);
      $packetSize = ReadInt24($flvTag, $tagPos + 1);
      $packetTS   = ReadInt24($flvTag, $tagPos + 4);
      $flvTag .= fread($flvIn, $packetSize + $prevTagSize);
      $totalTagLen = $tagHeaderLen + $packetSize + $prevTagSize;
      if (strlen($flvTag) != $totalTagLen)
        {
          DebugLog("Broken FLV tag encountered! Aborting further processing.");
          break;
        }
      switch ($packetType)
      {
          case AUDIO:
              if ($packetTS >= $prevAudioTS - TIMECODE_DURATION * 5)
                {
                  $FrameInfo = ReadByte($flvTag, $tagPos + $tagHeaderLen);
                  $CodecID   = ($FrameInfo & 0xF0) >> 4;
                  if ($CodecID == CODEC_ID_AAC)
                    {
                      $AAC_PacketType = ReadByte($flvTag, $tagPos + $tagHeaderLen + 1);
                      if ($AAC_PacketType == AAC_SEQUENCE_HEADER)
                        {
                          if ($AAC_HeaderWritten)
                            {
                              DebugLog(sprintf($format, "Skipping AAC sequence header\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
                              break;
                            }
                          else
                            {
                              DebugLog("Writing AAC sequence header");
                              $AAC_HeaderWritten = true;
                              $prevAAC_Header    = true;
                            }
                        }
                      else if (!$AAC_HeaderWritten)
                        {
                          DebugLog(sprintf($format, "Discarding audio packet received before AAC sequence header\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
                          break;
                        }
                    }
                  if ($packetSize > 0)
                    {
                      // Check for packets with non-monotonic audio timestamps and fix them
                      if (!$prevAAC_Header and ($packetTS <= $prevAudioTS))
                        {
                          $packetTS += TIMECODE_DURATION + ($prevAudioTS - $packetTS);
                          WriteInt24($flvTag, $tagPos + 4, $packetTS);
                          DebugLog(sprintf($format, "Fixing audio timestamp\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
                        }
                      if (($CodecID == CODEC_ID_AAC) and ($AAC_PacketType != AAC_SEQUENCE_HEADER))
                          $prevAAC_Header = false;
                      $pAudioTagPos = ftell($flvOut);
                      fwrite($flvOut, $flvTag, $totalTagLen);
                      DebugLog(sprintf($format, "AUDIO", $packetTS, $prevAudioTS, $packetSize . "\t\t" . $pAudioTagPos));
                      $prevAudioTS  = $packetTS;
                      $pAudioTagLen = $totalTagLen;
                    }
                  else
                      DebugLog(sprintf($format, "Skipping small sized audio packet\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
                }
              else
                  DebugLog(sprintf($format, "Skipping audio packet\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
              break;
          case VIDEO:
              if ($packetTS >= $prevVideoTS - TIMECODE_DURATION * 5)
                {
                  $FrameInfo = ReadByte($flvTag, $tagPos + $tagHeaderLen);
                  $FrameType = ($FrameInfo & 0xF0) >> 4;
                  $CodecID   = $FrameInfo & 0x0F;
                  if ($FrameType == FRAME_TYPE_INFO)
                    {
                      DebugLog(sprintf($format, "Skipping video info frame\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                      break;
                    }
                  if ($CodecID == CODEC_ID_AVC)
                    {
                      $AVC_PacketType = ReadByte($flvTag, $tagPos + $tagHeaderLen + 1);
                      if ($AVC_PacketType == AVC_SEQUENCE_HEADER)
                        {
                          if ($AVC_HeaderWritten)
                            {
                              DebugLog(sprintf($format, "Skipping AVC sequence header\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                              break;
                            }
                          else
                            {
                              DebugLog("Writing AVC sequence header");
                              $AVC_HeaderWritten = true;
                              $prevAVC_Header    = true;
                            }
                        }
                      else if (!$AVC_HeaderWritten)
                        {
                          DebugLog(sprintf($format, "Discarding video packet received before AVC sequence header\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                          break;
                        }
                    }
                  if ($packetSize > 0)
                    {
                      // Check for packets with non-monotonic video timestamps and fix them
                      if (!$prevAVC_Header and (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType != AVC_SEQUENCE_END)) and ($packetTS <= $prevVideoTS))
                        {
                          $packetTS += TIMECODE_DURATION + ($prevVideoTS - $packetTS);
                          WriteInt24($flvTag, $tagPos + 4, $packetTS);
                          DebugLog(sprintf($format, "Fixing video timestamp\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                        }
                      if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType != AVC_SEQUENCE_HEADER))
                          $prevAVC_Header = false;
                      $pVideoTagPos = ftell($flvOut);
                      fwrite($flvOut, $flvTag, $totalTagLen);
                      DebugLog(sprintf($format, "VIDEO", $packetTS, $prevVideoTS, $packetSize . "\t\t" . $pVideoTagPos));
                      $prevVideoTS  = $packetTS;
                      $pVideoTagLen = $totalTagLen;
                    }
                  else
                      DebugLog(sprintf($format, "Skipping small sized video packet\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
                }
              else
                  DebugLog(sprintf($format, "Skipping video packet\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
              break;
          case SCRIPT_DATA:
              $pMetaTagPos = ftell($flvOut);
              fwrite($flvOut, $flvTag, $totalTagLen);
              DebugLog(sprintf($format, "META", $packetTS, 0, $packetSize . "\t\t" . $pMetaTagPos));
              break;
      }
      $filePos += $totalTagLen;
      $cFilePos = (int) $filePos / (1024 * 1024);
      if ($cFilePos > $pFilePos)
        {
          echo sprintf("Processed %d/%.2f MB\r", $cFilePos, $fileSize);
          $pFilePos = $cFilePos;
        }
    }

  fclose($flvIn);
  fclose($flvOut);
  $timeEnd   = microtime(true);
  $timeTaken = sprintf("%.2f", $timeEnd - $timeStart);
  echo "Processed input file in $timeTaken seconds\n";
  echo "Finished\n";
?>