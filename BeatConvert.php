<?php
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
          LogInfo("You can use script with following switches:\n");
          foreach (self::$ACCEPTED[0] as $key => $value)
              LogInfo(sprintf(" --%-15s %s", $key, $value));
          foreach (self::$ACCEPTED[1] as $key => $value)
              LogInfo(sprintf(" --%-7s%-8s %s", $key, " [param]", $value));
        }

      function error($msg)
        {
          LogError($msg);
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

  function ReadInt16($str, $pos)
    {
      $int32 = unpack('N', "\x00\x00" . substr($str, $pos, 2));
      return $int32[1];
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

  function ReadInt64($str, $pos)
    {
      $hi    = sprintf("%u", ReadInt32($str, $pos));
      $lo    = sprintf("%u", ReadInt32($str, $pos + 4));
      $int64 = bcadd(bcmul($hi, "4294967296"), $lo);
      return $int64;
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

  function WriteFlvTimestamp(&$str, $strPos, $packetTS)
    {
      WriteInt24($str, $strPos + 4, ($packetTS & 0x00FFFFFF));
      WriteByte($str, $strPos + 7, ($packetTS & 0xFF000000) >> 24);
    }

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

  function RemoveExtension($outFile)
    {
      preg_match("/\.\w{1,4}$/i", $outFile, $extension);
      if (isset($extension[0]))
        {
          $extension = $extension[0];
          $outFile   = substr($outFile, 0, -strlen($extension));
          return $outFile;
        }
      return $outFile;
    }

  function ShowHeader()
    {
      $header = "KSV Beat Converter";
      $len    = strlen($header);
      $width  = floor((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  function WriteFlvFile($outFile, $audio = true, $video = true)
    {
      $flvHeader    = pack("H*", "464c5601050000000900000000");
      $flvHeaderLen = strlen($flvHeader);

      // Set proper Audio/Video marker
      WriteByte($flvHeader, 4, $audio << 2 | $video);

      if (is_resource($outFile))
          $flv = $outFile;
      else
          $flv = fopen($outFile, "w+b");
      if (!$flv)
          LogError("Failed to open " . $outFile);
      fwrite($flv, $flvHeader, $flvHeaderLen);
      return $flv;
    }

  // Global code starts here
  $avcCfgW    = false;
  $aacCfgW    = false;
  $beatFile   = "";
  $debug      = false;
  $flv        = "";
  $flvData    = "";
  $outFile    = "";
  $showHeader = true;

  $options = array(
      0 => array(
          'help' => 'displays this help',
          'debug' => 'show debug output'
      ),
      1 => array(
          'infile' => 'input beat file to convert',
          'outfile' => 'filename to use for output file'
      )
  );
  $cli     = new CLI($options, true);

  // Process command line options
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }
  if (isset($cli->params['unknown']))
      $beatFile = $cli->params['unknown'][0];
  if ($cli->getParam('debug'))
      $debug = true;
  if ($cli->getParam('infile'))
      $beatFile = $cli->getParam('infile');
  if ($cli->getParam('outfile'))
      $outFile = $cli->getParam('outfile');

  // Read input file
  $timeStart = microtime(true);
  $file      = false;
  if (file_exists($beatFile))
      $file = file_get_contents($beatFile);
  if ($file === false)
      LogError("Failed to open input file");
  $fileLen  = filesize($beatFile);
  $filePos  = 0;
  $fileSize = $fileLen / (1024 * 1024);
  $pFilePos = 0;

  // Parse beat file header
  $flags      = ReadByte($file, 0);
  $quality    = $flags & 15;
  $version    = $flags >> 4;
  $lookupSize = ReadInt16($file, 1);
  $encTable   = substr($file, 3, $lookupSize);
  $filePos    = $filePos + 3 + $lookupSize;
  LogDebug("Version: $version, Quality: $quality, LookupSize: $lookupSize");

  // Retrieve encryption key and iv
  $key = "";
  $iv  = "";
  for ($i = 0; $i < 32; $i += 2)
    {
      $key .= $file[$filePos + $i];
      $iv .= $file[$filePos + $i + 1];
    }
  $filePos += 32;
  LogDebug("Key: " . hexlify($key));
  LogDebug("IV : " . hexlify($iv));

  // Decrypt lookup table
  $td = mcrypt_module_open('rijndael-128', '', 'cbc', '');
  mcrypt_generic_init($td, $key, $iv);
  $decTable = mdecrypt_generic($td, $encTable);

  // Check for existing flv file
  if (!$outFile)
      $outFile = "Final.flv";
  if (file_exists($outFile))
    {
      $flv     = fopen($outFile, 'a');
      $avcCfgW = true;
      $aacCfgW = true;
    }

  // Decode lookup table
  $decPos      = 0;
  $decTableLen = strlen($decTable);
  while ($decPos < $decTableLen)
    {
      // Read table entry
      $flags = ReadByte($decTable, $decPos);
      if ($flags == 0)
          break;
      $type      = $flags >> 4;
      $encrypted = ($flags & 4) > 0 ? 1 : 0;
      $keyframe  = ($flags & 2) > 0 ? 1 : 0;
      $config    = ($flags & 1) > 0 ? 1 : 0;
      LogDebug("\nType: $type, Encrypted: $encrypted, KeyFrame: $keyframe, Config: $config");
      $time       = ReadInt32($decTable, $decPos + 1);
      $dataLength = ReadInt32($decTable, $decPos + 5);
      $decPos += 9;
      if ($encrypted)
        {
          $rawLength = ReadInt32($decTable, $decPos);
          $decPos += 4;
        }
      else
          $rawLength = $dataLength;
      LogDebug("Time: $time, DataLength: $dataLength, RawLength: $rawLength");

      // Decrypt encrypted tags
      $data = substr($file, $filePos, $dataLength);
      if ($encrypted)
        {
          LogDebug("Encrypted Packet: " . hexlify($data));
          mcrypt_generic_init($td, $key, $iv);
          $data = mdecrypt_generic($td, $data);
          LogDebug("Decrypted Packet: " . hexlify($data));
          $data = substr($data, 0, $rawLength);
        }

      // Create video tag
      if ($type == 1)
        {
          // Create codec tag
          if ($version == 2)
            {
              $codecTag = " ";
              WriteByte($codecTag, 0, 7 | ($keyframe ? 16 : 32));
              WriteByte($codecTag, 1, ($config ? 0 : 1));
              WriteInt24($codecTag, 2, 0);
            }
          else
              $codecTag = "";

          // Create flv tag
          $flvTag = " ";
          WriteByte($flvTag, 0, 9);
          WriteInt24($flvTag, 1, $rawLength + strlen($codecTag));
          WriteFlvTimestamp($flvTag, 0, $time);
          WriteInt24($flvTag, 8, 0);

          // Write flv tag footer
          $videoTag = $flvTag . $codecTag . $data;
          WriteInt32($videoTag, strlen($videoTag), strlen($videoTag));

          if ($config)
              $avcCfgW ? ($videoTag = "") : ($avcCfgW = true);
          $flvData .= $videoTag;
        }

      // Create audio tag
      if ($type == 2)
        {
          // Create codec tag
          if ($version == 2)
            {
              $codecTag = " ";
              WriteByte($codecTag, 0, 175);
              WriteByte($codecTag, 1, ($config ? 0 : 1));
            }
          else
              $codecTag = "";

          // Create flv tag
          $flvTag = " ";
          WriteByte($flvTag, 0, 8);
          WriteInt24($flvTag, 1, $rawLength + strlen($codecTag));
          WriteFlvTimestamp($flvTag, 0, $time);
          WriteInt24($flvTag, 8, 0);

          // Write flv tag footer
          $audioTag = $flvTag . $codecTag . $data;
          WriteInt32($audioTag, strlen($audioTag), strlen($audioTag));

          if ($config)
              $aacCfgW ? ($audioTag = "") : ($aacCfgW = true);
          $flvData .= $audioTag;
        }

      $filePos += $dataLength;
      $cFilePos = floor($filePos / (1024 * 1024));
      if ($cFilePos > $pFilePos)
        {
          LogInfo(sprintf("Processed %d/%.2f MB", $cFilePos, $fileSize), true);
          $pFilePos = $cFilePos;
        }
    }

  // Write output file
  if (!is_resource($flv))
      $flv = WriteFlvFile($outFile);
  fwrite($flv, $flvData);
  fclose($flv);

  $timeEnd   = microtime(true);
  $timeTaken = sprintf("%.2f", $timeEnd - $timeStart);
  LogInfo(sprintf("Processed input file in %s seconds", $timeTaken));
  LogInfo("Finished");
?>
