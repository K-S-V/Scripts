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
              'help'   => 'displays this help',
              'debug'  => 'show debug ouput',
              'delete' => 'delete fragments after processing',
              'rename' => 'rename fragments sequentially before processing'
          ),
          1 => array(
              'auth'      => 'authentication string for fragment requests',
              'fragments' => 'base filename for fragments',
              'manifest'  => 'manifest file for downloading of fragments',
              'parallel'  => 'number of fragments to download simultaneously',
              'proxy'     => 'use proxy for downloading of fragments',
              'quality'   => 'selected quality level (low|medium|high) or exact bitrate',
              'useragent' => 'User-Agent to use for emulation of browser requests'
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
              printf(" --%-9s%-9s%s\n", $key, " [param]", $value);
        }
    }

  class cURL
    {
      var $headers;
      var $user_agent;
      var $compression;
      var $cookie_file;
      var $proxy;
      var $active;
      var $cert_check;
      var $response;
      var $mh, $ch, $mrc;

      function cURL($cookies = true, $cookie = 'Cookies.txt', $compression = 'gzip', $proxy = '')
        {
          $this->headers[]   = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg';
          $this->headers[]   = 'Connection: Keep-Alive';
          $this->headers[]   = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
          $this->user_agent  = 'Mozilla/5.0 (Windows NT 5.1; rv:12.0) Gecko/20100101 Firefox/12.0';
          $this->compression = $compression;
          $this->proxy       = $proxy;
          $this->cookies     = $cookies;
          $this->cert_check  = true;
          if ($this->cookies == true)
              $this->cookie($cookie);
        }

      function cookie($cookie_file)
        {
          if (file_exists($cookie_file))
            {
              $this->cookie_file = $cookie_file;
            }
          else
            {
              $file = fopen($cookie_file, 'w') or $this->error('The cookie file could not be opened. Make sure this directory has the correct permissions');
              $this->cookie_file = $cookie_file;
              fclose($file);
            }
        }

      function get($url)
        {
          $process = curl_init($url);
          curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
          curl_setopt($process, CURLOPT_HEADER, 0);
          curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
          if ($this->cookies == true)
              curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
          if ($this->cookies == true)
              curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
          curl_setopt($process, CURLOPT_ENCODING, $this->compression);
          curl_setopt($process, CURLOPT_TIMEOUT, 60);
          if ($this->proxy)
              $this->setProxy($process, $this->proxy);
          curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
          if (!$this->cert_check)
              curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 0);
          $this->response = curl_exec($process);
          if ($this->response !== false)
              $status = curl_getinfo($process, CURLINFO_HTTP_CODE);
          curl_close($process);
          if (isset($status))
              return $status;
          else
              return false;
        }

      function post($url, $data)
        {
          $process = curl_init($url);
          curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
          curl_setopt($process, CURLOPT_HEADER, 1);
          curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
          if ($this->cookies == true)
              curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
          if ($this->cookies == true)
              curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
          curl_setopt($process, CURLOPT_ENCODING, $this->compression);
          curl_setopt($process, CURLOPT_TIMEOUT, 60);
          if ($this->proxy)
              $this->setProxy($process, $this->proxy);
          curl_setopt($process, CURLOPT_POSTFIELDS, $data);
          curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($process, CURLOPT_POST, 1);
          if (!$this->cert_check)
              curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 0);
          $return = curl_exec($process);
          curl_close($process);
          return $return;
        }

      function setProxy(&$process, $proxy)
        {
          $type  = substr($proxy, 0, stripos($proxy, ":"));
          $proxy = substr($proxy, stripos($proxy, "//") + 2);
          switch ($type)
          {
              case "socks4":
                  $type = CURLPROXY_SOCKS4;
                  break;
              case "socks5":
                  $type = CURLPROXY_SOCKS5;
                  break;
              default:
                  $type = CURLPROXY_HTTP;
          }
          curl_setopt($process, CURLOPT_PROXY, $this->proxy);
          curl_setopt($process, CURLOPT_PROXYTYPE, $type);
        }

      function addDownload($url, $id)
        {
          if (!isset($this->mh))
              $this->mh = curl_multi_init();
          if (isset($this->ch[$id]))
              return;
          else
              $download =& $this->ch[$id];
          $download['id']  = $id;
          $download['url'] = $url;
          $download['ch']  = curl_init($url);
          curl_setopt($download['ch'], CURLOPT_HTTPHEADER, $this->headers);
          curl_setopt($download['ch'], CURLOPT_HEADER, 0);
          curl_setopt($download['ch'], CURLOPT_USERAGENT, $this->user_agent);
          curl_setopt($download['ch'], CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($download['ch'], CURLOPT_TIMEOUT, 300);
          curl_setopt($download['ch'], CURLOPT_BINARYTRANSFER, 1);
          curl_setopt($download['ch'], CURLOPT_RETURNTRANSFER, 1);
          curl_multi_add_handle($this->mh, $download['ch']);
          do
            {
              $this->mrc = curl_multi_exec($this->mh, $this->active);
            } while ($this->mrc == CURLM_CALL_MULTI_PERFORM);
        }

      function checkDownloads()
        {
          if (isset($this->mh))
            {
              $this->mrc = curl_multi_exec($this->mh, $this->active);
              if ($this->mrc != CURLM_OK)
                  return false;
              while ($info = curl_multi_info_read($this->mh))
                {
                  foreach ($this->ch as $download)
                    {
                      if ($download['ch'] == $info['handle'])
                          break;
                    }
                  $info         = curl_getinfo($download['ch']);
                  $array['id']  = $download['id'];
                  $array['url'] = $download['url'];
                  if ($info['size_download'] && ($info['size_download'] >= $info['download_content_length']))
                    {
                      $array['status']   = $info['http_code'];
                      $array['response'] = curl_multi_getcontent($download['ch']);
                    }
                  else
                    {
                      $array['status']   = false;
                      $array['response'] = "";
                    }
                  $downloads[] = $array;
                  curl_multi_remove_handle($this->mh, $download['ch']);
                  curl_close($download['ch']);
                  unset($this->ch[$download['id']]);
                }
              if (isset($downloads) and (count($downloads) > 0))
                  return $downloads;
            }
          return false;
        }

      function stopDownloads()
        {
          if (isset($this->mh))
              curl_multi_close($this->mh);
        }

      function error($error)
        {
          echo "cURL Error : $error";
          die;
        }
    }

  function ShowHeader($header)
    {
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  function KeyName(array $a, $pos)
    {
      $temp = array_slice($a, $pos, 1, true);
      return key($temp);
    }

  function GetString($xmlObject)
    {
      return trim((string) $xmlObject);
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

  function value_in_array_field($needle, $needle_field, $value_field, $haystack, $strict = false)
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
      $drmData          = ReadString($bootstrapInfo, $pos);
      $metadata         = ReadString($bootstrapInfo, $pos);
      $segRunTableCount = ReadByte($bootstrapInfo, $pos++);
      for ($i = 0; $i < $segRunTableCount; $i++)
        {
          ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
          if ($boxType == "asrt")
              ParseAsrtBox($bootstrapInfo, $pos);
          $pos += $boxSize;
        }
      $fragRunTableCount = ReadByte($bootstrapInfo, $pos++);
      for ($i = 0; $i < $fragRunTableCount; $i++)
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
      global $baseUrl, $cc, $media, $quality;
      $status = $cc->get($manifest);
      if ($status == 403)
          die("Access Denied! Unable to download manifest.");
      else if ($status != 200)
          die("Unable to download manifest");
      $xml       = simplexml_load_string($cc->response);
      $namespace = $xml->getDocNamespaces();
      $namespace = $namespace[''];
      $xml->registerXPathNamespace("ns", $namespace);
      $streams = $xml->xpath("/ns:manifest/ns:media");
      foreach ($streams as $stream)
        {
          $bitrate   = isset($stream['bitrate']) ? (int) $stream['bitrate'] : 1;
          $streamId  = GetString($stream['streamId']);
          $bootstrap = $xml->xpath("/ns:manifest/ns:bootstrapInfo[@id='" . $stream['bootstrapInfoId'] . "']");
          if (isset($bootstrap[0]['url']))
            {
              $bootstrapUrl = $xml->xpath("/ns:manifest/ns:bootstrapInfo[@id='" . $stream['bootstrapInfoId'] . "']/@url");
              $bootstrapUrl = GetString($bootstrapUrl[0]['url']);
              if (strncasecmp($bootstrapUrl, "http", 4) == 0)
                  $cc->get($bootstrapUrl);
              else
                  $cc->get("$baseUrl/$bootstrapUrl");
              $media[$bitrate]['bootstrap'] = $cc->response;
            }
          else
              $media[$bitrate]['bootstrap'] = base64_decode(GetString($bootstrap[0]));
          $metadata = $xml->xpath("/ns:manifest/ns:media[@streamId='" . $streamId . "']/ns:metadata");
          if (isset($metadata[0]))
              $media[$bitrate]['metadata'] = GetString($metadata[0]);
          else
              $media[$bitrate]['metadata'] = "";
          $media[$bitrate]['url'] = GetString($stream['url']);
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

      $bootstrapInfo = $media['bootstrap'];
      ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
      if ($boxType == "abst")
          ParseBootstrapBox($bootstrapInfo, $pos);
      else
          die("Failed to parse bootstrap info");
    }

  function VerifyFragment($frag)
    {
      $fragPos = 0;
      $fragLen = strlen($frag);
      while ($fragPos < $fragLen)
        {
          ReadBoxHeader($frag, $fragPos, $boxType, $boxSize);
          if ($boxType == "mdat")
            {
              $frag    = substr($frag, $fragPos, $boxSize);
              $fragLen = strlen($frag);
              if ($fragLen == $boxSize)
                  return true;
              else
                  return false;
            }
          $fragPos += $boxSize;
        }
      return false;
    }

  function DownloadFragments($manifest)
    {
      global $auth, $baseFilename, $baseUrl, $cc, $fragCount, $fragTable, $media, $parallel, $rename;
      $fragNum = 0;

      if (strpos($manifest, '?') !== false)
        {
          $baseUrl = substr($manifest, 0, strpos($manifest, '?'));
          $baseUrl = substr($baseUrl, 0, strrpos($baseUrl, '/'));
        }
      else
          $baseUrl = substr($manifest, 0, strrpos($manifest, '/'));
      ParseManifest($manifest);
      if (strncasecmp($media['url'], "http", 4) == 0)
        {
          $baseUrl      = substr($media['url'], 0, strrpos($media['url'], '/'));
          $baseFilename = substr($media['url'], strrpos($media['url'], '/') + 1) . "Seg1-Frag";
        }
      else
          $baseFilename = $media['url'] . "Seg1-Frag";
      DebugLog("Downloading Fragments:\n");

      while (($fragNum < $fragCount) or $cc->active)
        {
          echo "Downloading $fragNum/$fragCount fragments\r";
          if (in_array_field($fragNum, "firstFragment", $fragTable, true))
            {
              $discontinuity = value_in_array_field($fragNum, "firstFragment", "discontinuityIndicator", $fragTable, true);
              if (($discontinuity == 1) or ($discontinuity == 3))
                {
                  $fragNum += 1;
                  $rename = true;
                  echo "Downloading $fragNum/$fragCount fragments\r";
                  continue;
                }
            }
          while (count($cc->ch) < $parallel)
            {
              if ($fragNum < $fragCount)
                {
                  $fragNum += 1;
                  if (file_exists("$baseFilename$fragNum"))
                    {
                      DebugLog("Fragment $fragNum is already downloaded");
                      echo "Downloading $fragNum/$fragCount fragments\r";
                      continue;
                    }
                  DebugLog("Adding fragment $fragNum to download queue");
                  $cc->addDownload("$baseUrl/$baseFilename$fragNum$auth", "$baseFilename$fragNum");
                }
              else
                  break;
            }

          $downloads = $cc->checkDownloads();
          if ($downloads !== false)
            {
              foreach ($downloads as $download)
                {
                  if ($download['status'] == 200)
                    {
                      if (VerifyFragment($download['response']))
                        {
                          DebugLog("Fragment " . $download['id'] . " successfully downloaded");
                          file_put_contents($download['id'], $download['response']);
                        }
                      else
                          $cc->addDownload($download['url'], $download['id']);
                    }
                  else if ($download['status'] == 403)
                      die("Access Denied! Unable to download fragments.");
                  else if ($download['status'] === false)
                    {
                      DebugLog("Fragment " . $download['id'] . " failed to download");
                      $cc->addDownload($download['url'], $download['id']);
                    }
                  else
                      $rename = true;
                }
            }
          usleep(50000);
        }

      echo "\n";
      DebugLog("\nAll fragments downloaded successfully\n");
      $cc->stopDownloads();
    }

  ShowHeader("KSV Adobe HDS Downloader");
  $flvHeader    = pack("H*", "464c5601050000000900000000");
  $flvHeaderLen = strlen($flvHeader);
  $format       = "%s\t%s\t\t%s\t\t%s";
  $auth         = "";
  $baseFilename = "";
  $debug        = false;
  $delete       = false;
  $fileExt      = ".f4f";
  $parallel     = 8;
  $quality      = "high";
  $rename       = false;
  $prevTagSize  = 4;
  $tagHeaderLen = 11;
  $prevAudioTS  = -1;
  $prevVideoTS  = -1;
  $pAudioTagLen = 0;
  $pVideoTagLen = 0;
  $pAudioTagPos = 0;
  $pVideoTagPos = 0;

  $cc  = new cURL();
  $cli = new CLI();
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }
  if ($cli->getParam('debug'))
      $debug = true;
  if ($cli->getParam('delete'))
      $delete = true;
  if ($cli->getParam('auth'))
      $auth = "?" . $cli->getParam('auth');
  if ($cli->getParam('fragments'))
      $baseFilename = $cli->getParam('fragments');
  if ($cli->getParam('parallel'))
      $parallel = $cli->getParam('parallel');
  if ($cli->getParam('proxy'))
      $cc->proxy = $cli->getParam('proxy');
  if ($cli->getParam('quality'))
      $quality = $cli->getParam('quality');
  if ($cli->getParam('useragent'))
      $cc->user_agent = $cli->getParam('useragent');
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
      $flv = fopen($outputFile, "w+b");
      fwrite($flv, $flvHeader, $flvHeaderLen);
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
      while ($fragPos < $fragLen)
        {
          ReadBoxHeader($frag, $fragPos, $boxType, $boxSize);
          if ($boxType == "mdat")
            {
              $frag    = substr($frag, $fragPos, $boxSize);
              $fragLen = strlen($frag);
              if ($fragLen == $boxSize)
                  $mdat = true;
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
                  if ($packetTS >= $prevAudioTS - TIMECODE_DURATION * 5)
                    {
                      $FrameInfo = ReadByte($frag, $fragPos + $tagHeaderLen);
                      $CodecID   = ($FrameInfo & 0xF0) >> 4;
                      if ($CodecID == CODEC_ID_AAC)
                        {
                          $AAC_PacketType = ReadByte($frag, $fragPos + $tagHeaderLen + 1);
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
                              WriteInt24($frag, $fragPos + 4, $packetTS);
                              DebugLog(sprintf($format, "Fixing audio timestamp\nAUDIO", $packetTS, $prevAudioTS, $packetSize));
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
                  if ($packetTS >= $prevVideoTS - TIMECODE_DURATION * 5)
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
                              WriteInt24($frag, $fragPos + 4, $packetTS);
                              DebugLog(sprintf($format, "Fixing video timestamp\nVIDEO", $packetTS, $prevVideoTS, $packetSize));
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