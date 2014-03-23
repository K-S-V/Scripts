<?php
  define('AUDIO', 0x08);
  define('VIDEO', 0x09);
  define('SCRIPT_DATA', 0x12);
  define('FRAME_TYPE_INFO', 0x05);
  define('CODEC_ID_AVC', 0x07);
  define('CODEC_ID_AAC', 0x0A);
  define('AVC_SEQUENCE_HEADER', 0x00);
  define('AAC_SEQUENCE_HEADER', 0x00);
  define('AVC_NALU', 0x01);
  define('AVC_SEQUENCE_END', 0x02);
  define('FRAMEFIX_STEP', 40);
  define('INVALID_TIMESTAMP', -1);

  class CLI
    {
      protected static $ACCEPTED = array();
      var $params = array();

      function __construct($options = false, $handleUnknown = false)
        {
          global $argc, $argv;

          if ($options !== false)
              self::$ACCEPTED = $options;

          // Parse params
          if ($argc > 1)
            {
              $paramSwitch = false;
              for ($i = 1; $i < $argc; $i++)
                {
                  $arg      = $argv[$i];
                  $isSwitch = preg_match('/^-+/', $arg);

                  if ($isSwitch)
                      $arg = preg_replace('/^-+/', '', $arg);

                  if ($paramSwitch && $isSwitch)
                      $this->error("[param] expected after '$paramSwitch' switch (" . self::$ACCEPTED[1][$paramSwitch] . ')');
                  else if (!$paramSwitch && !$isSwitch)
                    {
                      if ($handleUnknown)
                          $this->params['unknown'][] = $arg;
                      else
                          $this->error("'$arg' is an invalid option, use --help to display valid switches.");
                    }
                  else if (!$paramSwitch && $isSwitch)
                    {
                      if (isset($this->params[$arg]))
                          $this->error("'$arg' switch can't occur more than once");

                      $this->params[$arg] = true;
                      if (isset(self::$ACCEPTED[1][$arg]))
                          $paramSwitch = $arg;
                      else if (!isset(self::$ACCEPTED[0][$arg]))
                          $this->error("there's no '$arg' switch, use --help to display all switches.");
                    }
                  else if ($paramSwitch && !$isSwitch)
                    {
                      $this->params[$paramSwitch] = $arg;
                      $paramSwitch                = false;
                    }
                }
            }

          // Final check
          foreach ($this->params as $k => $v)
              if (isset(self::$ACCEPTED[1][$k]) && $v === true)
                  $this->error("[param] expected after '$k' switch (" . self::$ACCEPTED[1][$k] . ')');
        }

      function displayHelp()
        {
          printf("%s:\n\n", "You can use script with following switches");
          foreach (self::$ACCEPTED[0] as $key => $value)
              printf(" --%-18s%s\n", $key, $value);
          foreach (self::$ACCEPTED[1] as $key => $value)
              printf(" --%-9s%-9s%s\n", $key, " [param]", $value);
        }

      function error($msg)
        {
          printf("%s\n", $msg);
          exit(1);
        }

      function getParam($name)
        {
          if (isset($this->params[$name]))
              return $this->params[$name];
          else
              return false;
        }
    }

  function ReadByte($str, $pos)
    {
      $int = unpack('C', $str[$pos]);
      return $int[1];
    }

  function ReadInt24($str, $pos)
    {
      $int32 = unpack('N', "\x00" . substr($str, $pos, 3));
      return $int32[1];
    }

  function ReadInt32($str, $pos)
    {
      $int32 = unpack('N', substr($str, $pos, 4));
      return $int32[1];
    }

  function WriteByte(&$str, $pos, $int)
    {
      $str[$pos] = pack('C', $int);
    }

  function WriteInt24(&$str, $pos, $int)
    {
      $str[$pos]     = pack('C', ($int & 0xFF0000) >> 16);
      $str[$pos + 1] = pack('C', ($int & 0xFF00) >> 8);
      $str[$pos + 2] = pack('C', $int & 0xFF);
    }

  function WriteInt32(&$str, $pos, $int)
    {
      $str[$pos]     = pack('C', ($int & 0xFF000000) >> 24);
      $str[$pos + 1] = pack('C', ($int & 0xFF0000) >> 16);
      $str[$pos + 2] = pack('C', ($int & 0xFF00) >> 8);
      $str[$pos + 3] = pack('C', $int & 0xFF);
    }

  function WriteFlvTimestamp(&$flvTag, $tagPos, $packetTS)
    {
      WriteInt24($flvTag, $tagPos + 4, ($packetTS & 0x00FFFFFF));
      WriteByte($flvTag, $tagPos + 7, ($packetTS & 0xFF000000) >> 24);
    }

  function LogDebug($msg)
    {
      global $debug;
      if ($debug)
          fwrite(STDERR, $msg . "\n");
    }

  function ShowHeader()
    {
      $header = "KSV FLV Fixer";
      $len    = strlen($header);
      $width  = floor((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  // Global code starts here
  $flvHeader         = pack("H*", "464c5601050000000900000000");
  $flvHeaderLen      = strlen($flvHeader);
  $format            = " %-8s%-16s%-16s%-8s";
  $audio             = false;
  $debug             = false;
  $fixWindow         = 1000;
  $metadata          = true;
  $video             = false;
  $prevTagSize       = 4;
  $tagHeaderLen      = 11;
  $baseTS            = INVALID_TIMESTAMP;
  $negTS             = INVALID_TIMESTAMP;
  $prevAudioTS       = INVALID_TIMESTAMP;
  $prevVideoTS       = INVALID_TIMESTAMP;
  $pAudioTagLen      = 0;
  $pVideoTagLen      = 0;
  $pAudioTagPos      = 0;
  $pVideoTagPos      = 0;
  $prevAVC_Header    = false;
  $prevAAC_Header    = false;
  $AVC_HeaderWritten = false;
  $AAC_HeaderWritten = false;

  ShowHeader();
  $options = array(
      0 => array(
          'help' => 'displays this help',
          'debug' => 'show debug output',
          'nometa' => 'do not save metadata in repaired file'
      ),
      1 => array(
          'fixwindow' => 'timestamp gap between frames to consider as timeshift',
          'in' => 'input filename of flv file to be repaired',
          'out' => 'output filename for repaired file'
      )
  );
  $cli     = new CLI($options);

  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }
  if ($cli->getParam('debug'))
      $debug = true;
  if ($cli->getParam('nometa'))
      $metadata = false;
  if ($cli->getParam('fixwindow'))
      $fixWindow = $cli->getParam('fixwindow');
  if ($cli->getParam('in'))
      $in = $cli->getParam('in');
  else
      die("You must specify an input file\n");
  if ($cli->getParam('out'))
      $out = $cli->getParam('out');
  else
      die("You must specify an output file\n");

  $timeStart = microtime(true);
  if (file_exists($in))
    {
      $flvIn = fopen($in, "rb");
      if (function_exists("stream_set_chunk_size"))
          stream_set_chunk_size($flvIn, 8 * 1024 * 1024);
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
  if (!$flvOut)
      die("Failed to create output file\n");
  fwrite($flvOut, $flvHeader, $flvHeaderLen);

  LogDebug(sprintf($format . "%-16s", "Type", "CurrentTS", "PreviousTS", "Size", "Position"));
  while ($filePos < $fileLen)
    {
      $flvTag     = fread($flvIn, $tagHeaderLen);
      $tagPos     = 0;
      $packetType = ReadByte($flvTag, $tagPos);
      $packetSize = ReadInt24($flvTag, $tagPos + 1);
      $packetTS   = ReadInt24($flvTag, $tagPos + 4);
      $packetTS   = $packetTS | (ReadByte($flvTag, $tagPos + 7) << 24);

      // Try to fix the odd timestamps and make them zero based
      $currentTS = $packetTS;
      $lastTS    = $prevVideoTS >= $prevAudioTS ? $prevVideoTS : $prevAudioTS;
      $fixedTS   = $lastTS + FRAMEFIX_STEP;
      if (($baseTS == INVALID_TIMESTAMP) and (($packetType == AUDIO) or ($packetType == VIDEO)))
          $baseTS = $packetTS;
      if (($baseTS > 1000) and ($packetTS >= $baseTS))
          $packetTS -= $baseTS;
      if ($lastTS != INVALID_TIMESTAMP)
        {
          $timeShift = $packetTS - $lastTS;
          if ($timeShift > $fixWindow)
            {
              LogDebug("Timestamp gap detected: PacketTS=" . $packetTS . " LastTS=" . $lastTS . " Timeshift=" . $timeShift);
              if ($baseTS < $packetTS)
                  $baseTS += $timeShift - FRAMEFIX_STEP;
              else
                  $baseTS = $timeShift - FRAMEFIX_STEP;
              $packetTS = $fixedTS;
            }
          else
            {
              $lastTS = $packetType == VIDEO ? $prevVideoTS : $prevAudioTS;
              if ($packetTS < ($lastTS - $fixWindow))
                {
                  if (($negTS != INVALID_TIMESTAMP) and (($packetTS + $negTS) < ($lastTS - $fixWindow)))
                      $negTS = INVALID_TIMESTAMP;
                  if ($negTS == INVALID_TIMESTAMP)
                    {
                      $negTS = $fixedTS - $packetTS;
                      LogDebug("Negative timestamp detected: PacketTS=" . $packetTS . " LastTS=" . $lastTS . " NegativeTS=" . $negTS);
                      $packetTS = $fixedTS;
                    }
                  else
                    {
                      if (($packetTS + $negTS) <= ($lastTS + $fixWindow))
                          $packetTS += $negTS;
                      else
                        {
                          $negTS = $fixedTS - $packetTS;
                          LogDebug("Negative timestamp override: PacketTS=" . $packetTS . " LastTS=" . $lastTS . " NegativeTS=" . $negTS);
                          $packetTS = $fixedTS;
                        }
                    }
                }
            }
        }
      if ($packetTS != $currentTS)
          WriteFlvTimestamp($flvTag, $tagPos, $packetTS);

      $flvTag      = $flvTag . fread($flvIn, $packetSize + $prevTagSize);
      $totalTagLen = $tagHeaderLen + $packetSize + $prevTagSize;
      if (strlen($flvTag) != $totalTagLen)
        {
          LogDebug("Broken FLV tag encountered! Aborting further processing.");
          break;
        }
      switch ($packetType)
      {
          case AUDIO:
              if ($packetTS > $prevAudioTS - $fixWindow)
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
                              LogDebug(sprintf("%s\n" . $format, "Skipping AAC sequence header", "AUDIO", $packetTS, $prevAudioTS, $packetSize));
                              break;
                            }
                          else
                            {
                              LogDebug("Writing AAC sequence header");
                              $AAC_HeaderWritten = true;
                            }
                        }
                      else if (!$AAC_HeaderWritten)
                        {
                          LogDebug(sprintf("%s\n" . $format, "Discarding audio packet received before AAC sequence header", "AUDIO", $packetTS, $prevAudioTS, $packetSize));
                          break;
                        }
                    }
                  if ($packetSize > 0)
                    {
                      // Check for packets with non-monotonic audio timestamps and fix them
                      if (!(($CodecID == CODEC_ID_AAC) and (($AAC_PacketType == AAC_SEQUENCE_HEADER) or $prevAAC_Header)))
                          if (($prevAudioTS != INVALID_TIMESTAMP) and ($packetTS <= $prevAudioTS))
                            {
                              LogDebug(sprintf("%s\n" . $format, "Fixing audio timestamp", "AUDIO", $packetTS, $prevAudioTS, $packetSize));
                              $packetTS += (FRAMEFIX_STEP / 5) + ($prevAudioTS - $packetTS);
                              WriteFlvTimestamp($flvTag, $tagPos, $packetTS);
                            }
                      $pAudioTagPos = ftell($flvOut);
                      fwrite($flvOut, $flvTag, $totalTagLen);
                      if ($debug)
                          LogDebug(sprintf($format . "%-16s", "AUDIO", $packetTS, $prevAudioTS, $packetSize, $pAudioTagPos));
                      if (($CodecID == CODEC_ID_AAC) and ($AAC_PacketType == AAC_SEQUENCE_HEADER))
                          $prevAAC_Header = true;
                      else
                          $prevAAC_Header = false;
                      $prevAudioTS  = $packetTS;
                      $pAudioTagLen = $totalTagLen;
                    }
                  else
                      LogDebug(sprintf("%s\n" . $format, "Skipping small sized audio packet", "AUDIO", $packetTS, $prevAudioTS, $packetSize));
                }
              else
                  LogDebug(sprintf("%s\n" . $format, "Skipping audio packet", "AUDIO", $packetTS, $prevAudioTS, $packetSize));
              if (!$audio)
                  $audio = true;
              break;
          case VIDEO:
              if ($packetTS > $prevVideoTS - $fixWindow)
                {
                  $FrameInfo = ReadByte($flvTag, $tagPos + $tagHeaderLen);
                  $FrameType = ($FrameInfo & 0xF0) >> 4;
                  $CodecID   = $FrameInfo & 0x0F;
                  if ($FrameType == FRAME_TYPE_INFO)
                    {
                      LogDebug(sprintf("%s\n" . $format, "Skipping video info frame", "VIDEO", $packetTS, $prevVideoTS, $packetSize));
                      break;
                    }
                  if ($CodecID == CODEC_ID_AVC)
                    {
                      $AVC_PacketType = ReadByte($flvTag, $tagPos + $tagHeaderLen + 1);
                      if ($AVC_PacketType == AVC_SEQUENCE_HEADER)
                        {
                          if ($AVC_HeaderWritten)
                            {
                              LogDebug(sprintf("%s\n" . $format, "Skipping AVC sequence header", "VIDEO", $packetTS, $prevVideoTS, $packetSize));
                              break;
                            }
                          else
                            {
                              LogDebug("Writing AVC sequence header");
                              $AVC_HeaderWritten = true;
                            }
                        }
                      else if (!$AVC_HeaderWritten)
                        {
                          LogDebug(sprintf("%s\n" . $format, "Discarding video packet received before AVC sequence header", "VIDEO", $packetTS, $prevVideoTS, $packetSize));
                          break;
                        }
                    }
                  if ($packetSize > 0)
                    {
                      $pts = $packetTS;
                      if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType == AVC_NALU))
                        {
                          $cts = ReadInt24($flvTag, $tagPos + $tagHeaderLen + 2);
                          $cts = ($cts + 0xff800000) ^ 0xff800000;
                          $pts = $packetTS + $cts;
                          if ($cts != 0)
                              LogDebug("DTS: $packetTS CTS: $cts PTS: $pts");
                        }

                      // Check for packets with non-monotonic video timestamps and fix them
                      if (!(($CodecID == CODEC_ID_AVC) and (($AVC_PacketType == AVC_SEQUENCE_HEADER) or ($AVC_PacketType == AVC_SEQUENCE_END) or $prevAVC_Header)))
                          if (($prevVideoTS != INVALID_TIMESTAMP) and ($packetTS <= $prevVideoTS))
                            {
                              LogDebug(sprintf("%s\n" . $format, "Fixing video timestamp", "VIDEO", $packetTS, $prevVideoTS, $packetSize));
                              $packetTS += (FRAMEFIX_STEP / 5) + ($prevVideoTS - $packetTS);
                              WriteFlvTimestamp($flvTag, $tagPos, $packetTS);
                            }
                      $pVideoTagPos = ftell($flvOut);
                      fwrite($flvOut, $flvTag, $totalTagLen);
                      if ($debug)
                          LogDebug(sprintf($format . "%-16s", "VIDEO", $packetTS, $prevVideoTS, $packetSize, $pVideoTagPos));
                      if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType == AVC_SEQUENCE_HEADER))
                          $prevAVC_Header = true;
                      else
                          $prevAVC_Header = false;
                      $prevVideoTS  = $packetTS;
                      $pVideoTagLen = $totalTagLen;
                    }
                  else
                      LogDebug(sprintf("%s\n" . $format, "Skipping small sized video packet", "VIDEO", $packetTS, $prevVideoTS, $packetSize));
                }
              else
                  LogDebug(sprintf("%s\n" . $format, "Skipping video packet", "VIDEO", $packetTS, $prevVideoTS, $packetSize));
              if (!$video)
                  $video = true;
              break;
          case SCRIPT_DATA:
              if ($metadata)
                {
                  $pMetaTagPos = ftell($flvOut);
                  fwrite($flvOut, $flvTag, $totalTagLen);
                  LogDebug(sprintf($format . "%-16s", "META", $packetTS, 0, $packetSize, $pMetaTagPos));
                }
              break;
      }
      $filePos += $totalTagLen;
      $cFilePos = floor($filePos / (1024 * 1024));
      if ($cFilePos > $pFilePos)
        {
          printf("Processed %d/%.2f MB\r", $cFilePos, $fileSize);
          $pFilePos = $cFilePos;
        }
    }

  // Set proper Audio/Video marker
  WriteByte($flvHeader, 4, $audio << 2 | $video);
  fseek($flvOut, 0);
  fwrite($flvOut, $flvHeader, $flvHeaderLen);

  fclose($flvIn);
  fclose($flvOut);
  $timeEnd   = microtime(true);
  $timeTaken = sprintf("%.2f", $timeEnd - $timeStart);
  printf("Processed input file in %s seconds\n", $timeTaken);
  printf("Finished\n");
?>
