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
              'debug'  => 'show debug output',
              'delete' => 'delete fragments after processing',
              'fproxy' => 'force proxy for downloading of fragments',
              'play'   => 'dump flv data to stderr for piping to another program',
              'rename' => 'rename fragments sequentially before processing'
          ),
          1 => array(
              'auth'      => 'authentication string for fragment requests',
              'duration'  => 'stop live recording after specified number of seconds',
              'filesize'  => 'split output file in chunks of specified size (MB)',
              'fragments' => 'base filename for fragments',
              'manifest'  => 'manifest file for downloading of fragments',
              'outdir'    => 'destination folder for output file',
              'parallel'  => 'number of fragments to download simultaneously',
              'proxy'     => 'proxy for downloading of manifest',
              'quality'   => 'selected quality level (low|medium|high) or exact bitrate',
              'start'     => 'start from specified fragment',
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
                      if (isset($GLOBALS['baseFilename']) and (!$GLOBALS['baseFilename']))
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
                          die("'$arg' switch cannot occur more than once\n");

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
                  die("[param] expected after '$k' switch (" . self::$ACCEPTED[1][$k] . ")\n");
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
      var $headers, $user_agent, $compression, $cookie_file;
      var $active, $cert_check, $fragProxy, $proxy, $response;
      var $mh, $ch, $mrc;

      function cURL($cookies = true, $cookie = 'Cookies.txt', $compression = 'gzip', $proxy = '')
        {
          $this->headers[]   = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg';
          $this->headers[]   = 'Connection: Keep-Alive';
          $this->headers[]   = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
          $this->user_agent  = 'Mozilla/5.0 (Windows NT 5.1; rv:14.0) Gecko/20100101 Firefox/14.0.1';
          $this->compression = $compression;
          $this->proxy       = $proxy;
          $this->fragProxy   = false;
          $this->cookies     = $cookies;
          $this->cert_check  = true;
          if ($this->cookies == true)
              $this->cookie($cookie);
        }

      function cookie($cookie_file)
        {
          if (file_exists($cookie_file))
              $this->cookie_file = $cookie_file;
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
          $type = substr($proxy, 0, stripos($proxy, "://"));
          if ($type)
            {
              $type  = strtolower($type);
              $proxy = substr($proxy, stripos($proxy, "://") + 3);
            }
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
          curl_setopt($process, CURLOPT_PROXY, $proxy);
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
          if ($this->cookies == true)
              curl_setopt($download['ch'], CURLOPT_COOKIEFILE, $this->cookie_file);
          if ($this->cookies == true)
              curl_setopt($download['ch'], CURLOPT_COOKIEJAR, $this->cookie_file);
          curl_setopt($download['ch'], CURLOPT_ENCODING, $this->compression);
          curl_setopt($download['ch'], CURLOPT_TIMEOUT, 300);
          if ($this->fragProxy and $this->proxy)
              $this->setProxy($download['ch'], $this->proxy);
          curl_setopt($download['ch'], CURLOPT_BINARYTRANSFER, 1);
          curl_setopt($download['ch'], CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($download['ch'], CURLOPT_FOLLOWLOCATION, 1);
          if (!$this->cert_check)
              curl_setopt($download['ch'], CURLOPT_SSL_VERIFYPEER, 0);
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
                      if ($download['ch'] == $info['handle'])
                          break;
                  $info         = curl_getinfo($download['ch']);
                  $array['id']  = $download['id'];
                  $array['url'] = $download['url'];
                  if ($info['http_code'] == 200)
                    {
                      if ($info['size_download'] >= $info['download_content_length'])
                        {
                          $array['status']   = $info['http_code'];
                          $array['response'] = curl_multi_getcontent($download['ch']);
                        }
                      else
                        {
                          $array['status']   = false;
                          $array['response'] = "";
                        }
                    }
                  else
                    {
                      $array['status']   = $info['http_code'];
                      $array['response'] = curl_multi_getcontent($download['ch']);
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
            {
              curl_multi_close($this->mh);
              unset($this->mh);
            }
        }

      function error($error)
        {
          die("cURL Error : $error");
        }
    }

  class F4F
    {
      var $audio, $auth, $baseFilename, $baseTS, $bootstrapUrl, $baseUrl, $debug, $duration, $fileCount, $filesize;
      var $format, $live, $media, $outDir, $parallel, $play, $quality, $rename, $video;
      var $prevTagSize, $tagHeaderLen;
      var $segTable, $fragTable, $segNum, $fragNum, $frags, $fragCount, $fragsPerSeg, $lastFrag, $fragUrl, $discontinuity;
      var $prevAudioTS, $prevVideoTS, $pAudioTagLen, $pVideoTagLen, $pAudioTagPos, $pVideoTagPos;
      var $prevAVC_Header, $prevAAC_Header, $AVC_HeaderWritten, $AAC_HeaderWritten;

      public function __construct()
        {
          $this->auth          = "";
          $this->baseFilename  = "";
          $this->bootstrapUrl  = "";
          $this->debug         = false;
          $this->duration      = 0;
          $this->fileCount     = 1;
          $this->format        = "";
          $this->live          = false;
          $this->outDir        = "";
          $this->parallel      = 8;
          $this->play          = false;
          $this->quality       = "high";
          $this->rename        = false;
          $this->segNum        = 1;
          $this->fragNum       = false;
          $this->frags         = array();
          $this->fragCount     = 0;
          $this->fragsPerSeg   = 0;
          $this->lastFrag      = 0;
          $this->discontinuity = "";
          $this->InitDecoder();
        }

      function InitDecoder()
        {
          $this->audio             = false;
          $this->baseTS            = false;
          $this->filesize          = 0;
          $this->video             = false;
          $this->prevTagSize       = 4;
          $this->tagHeaderLen      = 11;
          $this->prevAudioTS       = -1;
          $this->prevVideoTS       = -1;
          $this->pAudioTagLen      = 0;
          $this->pVideoTagLen      = 0;
          $this->pAudioTagPos      = 0;
          $this->pVideoTagPos      = 0;
          $this->prevAVC_Header    = false;
          $this->prevAAC_Header    = false;
          $this->AVC_HeaderWritten = false;
          $this->AAC_HeaderWritten = false;
        }

      function GetManifest($cc, $manifest)
        {
          $status = $cc->get($manifest);
          if ($status == 403)
              Quit("Access Denied! Unable to download manifest.");
          else if ($status != 200)
              Quit("Unable to download manifest");
          $xml = simplexml_load_string(trim($cc->response));
          if (!$xml)
              Quit("Failed to load xml");
          $namespace = $xml->getDocNamespaces();
          $namespace = $namespace[''];
          $xml->registerXPathNamespace("ns", $namespace);
          return $xml;
        }

      function ParseManifest($cc, $manifest)
        {
          Message("Processing manifest info....");
          $xml     = $this->GetManifest($cc, $manifest);
          $baseUrl = $xml->xpath("/ns:manifest/ns:baseURL");
          if (isset($baseUrl[0]))
              $baseUrl = GetString($baseUrl[0]);
          else
              $baseUrl = "";
          $url = $xml->xpath("/ns:manifest/ns:media[@*]");
          if (isset($url[0]['href']))
            {
              foreach ($url as $manifest)
                {
                  $bitrate                        = (int) $manifest['bitrate'];
                  $manifests[$bitrate]['bitrate'] = $bitrate;
                  $manifests[$bitrate]['url']     = NormalizePath($baseUrl . GetString($manifest['href']));
                  $manifests[$bitrate]['xml']     = $this->GetManifest($cc, $manifests[$bitrate]['url']);
                }
            }
          else
            {
              $manifests[0]['bitrate'] = 0;
              $manifests[0]['url']     = $manifest;
              $manifests[0]['xml']     = $xml;
            }

          foreach ($manifests as $manifest)
            {
              $xml     = $manifest['xml'];
              $streams = $xml->xpath("/ns:manifest/ns:media");
              foreach ($streams as $stream)
                {
                  $stream   = (array) $stream;
                  $stream   = array_change_key_case($stream['@attributes']);
                  $bitrate  = isset($stream['bitrate']) ? (int) $stream['bitrate'] : $manifest['bitrate'];
                  $streamId = isset($stream[strtolower('streamId')]) ? GetString($stream[strtolower('streamId')]) : "";
                  $mediaEntry =& $this->media[$bitrate];

                  // Extract baseUrl from manifest url
                  $baseUrl = $manifest['url'];
                  if (strpos($baseUrl, '?') !== false)
                    {
                      $baseUrl = substr($baseUrl, 0, strpos($baseUrl, '?'));
                      $baseUrl = substr($baseUrl, 0, strrpos($baseUrl, '/'));
                    }
                  else
                      $baseUrl = substr($baseUrl, 0, strrpos($baseUrl, '/'));
                  $mediaEntry['baseUrl'] = $baseUrl;

                  $mediaEntry['url'] = GetString($stream['url']);
                  if (isset($stream[strtolower('bootstrapInfoId')]))
                      $bootstrap = $xml->xpath("/ns:manifest/ns:bootstrapInfo[@id='" . $stream[strtolower('bootstrapInfoId')] . "']");
                  else
                      $bootstrap = $xml->xpath("/ns:manifest/ns:bootstrapInfo");
                  if (isset($bootstrap[0]['url']))
                    {
                      $bootstrapUrl = GetString($bootstrap[0]['url']);
                      if (strncasecmp($bootstrapUrl, "http", 4) != 0)
                          $bootstrapUrl = $mediaEntry['baseUrl'] . "/$bootstrapUrl";
                      $mediaEntry['bootstrapUrl'] = NormalizePath($bootstrapUrl);
                      if ($cc->get($mediaEntry['bootstrapUrl']) != 200)
                          Quit("Failed to get bootstrap info");
                      $mediaEntry['bootstrap'] = $cc->response;
                    }
                  else
                      $mediaEntry['bootstrap'] = base64_decode(GetString($bootstrap[0]));
                  $metadata = $xml->xpath("/ns:manifest/ns:media[@url='" . $mediaEntry['url'] . "']/ns:metadata");
                  if (isset($metadata[0]))
                      $mediaEntry['metadata'] = base64_decode(GetString($metadata[0]));
                  else
                      $mediaEntry['metadata'] = "";
                }
            }

          // Available qualities
          $bitrates = array();
          if (!count($this->media))
              Quit("No media entry found");
          krsort($this->media, SORT_NUMERIC);
          DebugLog("Manifest Entries:\n");
          DebugLog(sprintf(" %-8s%s", "Bitrate", "URL"));
          for ($i = 0; $i < count($this->media); $i++)
            {
              $key        = KeyName($this->media, $i);
              $bitrates[] = $key;
              DebugLog(sprintf(" %-8d%s", $key, $this->media[$key]['url']));
            }
          DebugLog("");
          echo "Available Bitrates: " . implode(' ', $bitrates) . "\n";

          // Quality selection
          if (is_numeric($this->quality) and isset($this->media[$this->quality]))
              $this->media = $this->media[$this->quality];
          else
            {
              $this->quality = strtolower($this->quality);
              switch ($this->quality)
              {
                  case "low":
                      $this->quality = 2;
                      break;
                  case "medium":
                      $this->quality = 1;
                      break;
                  default:
                      $this->quality = 0;
              }
              while ($this->quality >= 0)
                {
                  $key = KeyName($this->media, $this->quality);
                  if ($key !== NULL)
                    {
                      $this->media = $this->media[$key];
                      break;
                    }
                  else
                      $this->quality -= 1;
                }
            }

          $this->baseUrl = $this->media['baseUrl'];
          if (isset($this->media['bootstrapUrl']))
              $this->bootstrapUrl = $this->media['bootstrapUrl'];
          $bootstrapInfo = $this->media['bootstrap'];
          ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
          if ($boxType == "abst")
              $this->ParseBootstrapBox($bootstrapInfo, $pos);
          else
              Quit("Failed to parse bootstrap info");
        }

      function UpdateBootstrapInfo($cc, $bootstrapUrl)
        {
          $fragNum = $this->fragCount;
          $retries = 0;
          while (($fragNum == $this->fragCount) and ($retries < 30))
            {
              $bootstrapPos = 0;
              DebugLog("Updating bootstrap info, Available fragments: " . $this->fragCount);
              if ($cc->get($bootstrapUrl) != 200)
                  Quit("Failed to refresh bootstrap info");
              $bootstrapInfo = $cc->response;
              ReadBoxHeader($bootstrapInfo, $bootstrapPos, $boxType, $boxSize);
              if ($boxType == "abst")
                  $this->ParseBootstrapBox($bootstrapInfo, $bootstrapPos);
              else
                  Quit("Failed to parse bootstrap info");
              DebugLog("Update complete, Available fragments: " . $this->fragCount);
              if ($fragNum == $this->fragCount)
                {
                  printf("%-79s\r", "Updating bootstrap info, Retries: " . ++$retries);
                  usleep(4000000);
                }
            }
        }

      function ParseBootstrapBox($bootstrapInfo, $pos)
        {
          $version          = ReadByte($bootstrapInfo, $pos);
          $flags            = ReadInt24($bootstrapInfo, $pos + 1);
          $bootstrapVersion = ReadInt32($bootstrapInfo, $pos + 4);
          $byte             = ReadByte($bootstrapInfo, $pos + 8);
          $profile          = ($byte & 0xC0) >> 6;
          if (($byte & 0x20) >> 5)
              $this->live = true;
          $update              = ($byte & 0x10) >> 4;
          $timescale           = ReadInt32($bootstrapInfo, $pos + 9);
          $currentMediaTime    = ReadInt64($bootstrapInfo, 13);
          $smpteTimeCodeOffset = ReadInt64($bootstrapInfo, 21);
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
                  $this->ParseAsrtBox($bootstrapInfo, $pos);
              $pos += $boxSize;
            }
          $fragRunTableCount = ReadByte($bootstrapInfo, $pos++);
          for ($i = 0; $i < $fragRunTableCount; $i++)
            {
              ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
              if ($boxType == "afrt")
                  $this->ParseAfrtBox($bootstrapInfo, $pos);
              $pos += $boxSize;
            }
        }

      function ParseAsrtBox($asrt, $pos)
        {
          $this->segTable    = array();
          $version           = ReadByte($asrt, $pos);
          $flags             = ReadInt24($asrt, $pos + 1);
          $qualityEntryCount = ReadByte($asrt, $pos + 4);
          $pos += 5;
          for ($i = 0; $i < $qualityEntryCount; $i++)
              $qualitySegmentUrlModifiers[$i] = ReadString($asrt, $pos);
          $segCount = ReadInt32($asrt, $pos);
          $pos += 4;
          DebugLog(sprintf("%s:\n\n %-8s%-10s", "Segment Entries", "Number", "Fragments"));
          for ($i = 0; $i < $segCount; $i++)
            {
              $firstSegment = ReadInt32($asrt, $pos);
              $segEntry =& $this->segTable[$firstSegment];
              $segEntry['firstSegment']        = $firstSegment;
              $segEntry['fragmentsPerSegment'] = ReadInt32($asrt, $pos + 4);
              $pos += 8;
              DebugLog(sprintf(" %-8s%-10s", $segEntry['firstSegment'], $segEntry['fragmentsPerSegment']));
            }
          DebugLog("");
          $lastSegment     = end($this->segTable);
          $this->fragCount = $lastSegment['fragmentsPerSegment'];

          // Use segment table in case of multiple segments
          if ($this->live and (count($this->segTable) > 1))
            {
              $secondLastSegment = prev($this->segTable);
              if ($this->fragNum === false)
                {
                  $this->segNum      = $lastSegment['firstSegment'];
                  $this->fragsPerSeg = $secondLastSegment['fragmentsPerSegment'];
                  $this->fragNum     = $secondLastSegment['firstSegment'] * $this->fragsPerSeg + $this->fragCount - 2;
                  $this->fragCount   = $secondLastSegment['firstSegment'] * $this->fragsPerSeg + $this->fragCount;
                }
              else
                  $this->fragCount = $secondLastSegment['firstSegment'] * $this->fragsPerSeg + $this->fragCount;
            }
        }

      function ParseAfrtBox($afrt, $pos)
        {
          $this->fragTable   = array();
          $version           = ReadByte($afrt, $pos);
          $flags             = ReadInt24($afrt, $pos + 1);
          $timescale         = ReadInt32($afrt, $pos + 4);
          $qualityEntryCount = ReadByte($afrt, $pos + 8);
          $pos += 9;
          for ($i = 0; $i < $qualityEntryCount; $i++)
              $qualitySegmentUrlModifiers[$i] = ReadString($afrt, $pos);
          $fragEntries = ReadInt32($afrt, $pos);
          $pos += 4;
          DebugLog(sprintf("%s:\n\n %-8s%-16s%-16s%-16s", "Fragment Entries", "Number", "Timestamp", "Duration", "Discontinuity"));
          for ($i = 0; $i < $fragEntries; $i++)
            {
              $firstFragment = ReadInt32($afrt, $pos);
              $fragEntry =& $this->fragTable[$firstFragment];
              $fragEntry['firstFragment']          = $firstFragment;
              $fragEntry['firstFragmentTimestamp'] = ReadInt64($afrt, $pos + 4);
              $fragEntry['fragmentDuration']       = ReadInt32($afrt, $pos + 12);
              $fragEntry['discontinuityIndicator'] = "";
              $pos += 16;
              if ($fragEntry['fragmentDuration'] == 0)
                  $fragEntry['discontinuityIndicator'] = ReadByte($afrt, $pos++);
              DebugLog(sprintf(" %-8s%-16s%-16s%-16s", $fragEntry['firstFragment'], $fragEntry['firstFragmentTimestamp'], $fragEntry['fragmentDuration'], $fragEntry['discontinuityIndicator']));
            }
          DebugLog("");

          // Use fragment table in case of single segment
          if (count($this->segTable) == 1)
            {
              $firstFragment = reset($this->fragTable);
              $lastFragment  = end($this->fragTable);
              if ($this->live)
                {
                  if ($this->fragNum === false)
                    {
                      $this->fragNum   = $lastFragment['firstFragment'] - 2;
                      $this->fragCount = $lastFragment['firstFragment'];
                    }
                  else
                      $this->fragCount = $lastFragment['firstFragment'];
                }
              else if ($this->fragNum === false)
                  $this->fragNum = $firstFragment['firstFragment'] - 1;
            }
        }

      function DownloadFragments($cc, $manifest, $opt = array())
        {
          $start = 0;
          extract($opt, EXTR_IF_EXISTS);

          $this->ParseManifest($cc, $manifest);
          $segNum  = $this->segNum;
          $fragNum = $this->fragNum;
          if ($start)
            {
              if ($segNum > 1)
                  if ($start % $this->fragsPerSeg)
                      $segNum = (int) ($start / $this->fragsPerSeg + 1);
                  else
                      $segNum = (int) ($start / $this->fragsPerSeg);
              $fragNum       = $start - 1;
              $this->segNum  = $segNum;
              $this->fragNum = $fragNum;
            }
          $this->lastFrag  = $fragNum;
          $opt['cc']       = $cc;
          $opt['duration'] = 0;

          // Extract baseFilename
          if (substr($this->media['url'], -1) == '/')
              $this->baseFilename = substr($this->media['url'], 0, -1);
          else
              $this->baseFilename = $this->media['url'];
          if (strrpos($this->baseFilename, '/'))
              $this->baseFilename = substr($this->baseFilename, strrpos($this->baseFilename, '/') + 1) . "Seg" . $segNum . "-Frag";
          else
              $this->baseFilename .= "Seg" . $segNum . "-Frag";

          if ($fragNum >= $this->fragCount)
              Quit("No fragment available for downloading");

          if (strncasecmp($this->media['url'], "http", 4) == 0)
              $this->fragUrl = $this->media['url'];
          else
              $this->fragUrl = $this->baseUrl . "/" . $this->media['url'];
          $this->fragUrl = NormalizePath($this->fragUrl);
          DebugLog("Downloading Fragments:\n");

          while (($fragNum < $this->fragCount) or $cc->active)
            {
              while ((count($cc->ch) < $this->parallel) and ($fragNum < $this->fragCount))
                {
                  $fragNum += 1;
                  printf("%-79s\r", "Downloading $fragNum/$this->fragCount fragments");
                  if (in_array_field($fragNum, "firstFragment", $this->fragTable, true))
                      $this->discontinuity = value_in_array_field($fragNum, "firstFragment", "discontinuityIndicator", $this->fragTable, true);
                  if (($this->discontinuity == 1) or ($this->discontinuity == 3))
                    {
                      if ($this->live)
                          $this->frags[$download['id']] = false;
                      $this->rename = true;
                      continue;
                    }
                  if (file_exists($this->baseFilename . $fragNum))
                    {
                      DebugLog("Fragment $fragNum is already downloaded");
                      continue;
                    }

                  /* Increase or decrease segment number if current fragment is not available */
                  /* in selected segment range                                                */
                  if ($this->segNum > 1)
                      if ($fragNum > ($segNum * $this->fragsPerSeg))
                          $segNum++;
                      else if ($fragNum <= (($segNum - 1) * $this->fragsPerSeg))
                          $segNum--;

                  DebugLog("Adding fragment $fragNum to download queue");
                  $cc->addDownload($this->fragUrl . "Seg" . $segNum . "-Frag" . $fragNum . $this->auth, $fragNum);
                }

              $downloads = $cc->checkDownloads();
              if ($downloads !== false)
                {
                  foreach ($downloads as $download)
                    {
                      if ($download['status'] == 200)
                        {
                          if ($this->VerifyFragment($download['response']))
                            {
                              DebugLog("Fragment " . $this->baseFilename . $download['id'] . " successfully downloaded");
                              if ($this->live)
                                  $this->WriteLiveFragment($download, $opt);
                              else
                                  file_put_contents($this->baseFilename . $download['id'], $download['response']);
                            }
                          else
                            {
                              DebugLog("Fragment " . $download['id'] . " failed to verify");
                              DebugLog("Adding fragment " . $download['id'] . " to download queue");
                              $cc->addDownload($download['url'], $download['id']);
                            }
                        }
                      else if ($download['status'] === false)
                        {
                          DebugLog("Fragment " . $download['id'] . " failed to download");
                          DebugLog("Adding fragment " . $download['id'] . " to download queue");
                          $cc->addDownload($download['url'], $download['id']);
                        }
                      else if ($download['status'] == 403)
                          Quit("Access Denied! Unable to download fragments.");
                      else
                        {
                          DebugLog("Fragment " . $download['id'] . " doesn't exist, Status: " . $download['status']);
                          if ($this->live)
                              $this->frags[$download['id']] = false;
                          $this->rename = true;

                          /* Resync with latest available fragment when we are left behind due to */
                          /* slow connection and short live window on streaming server. make sure */
                          /* to reset the last written fragment.                                  */
                          if ($this->live and !$cc->active)
                            {
                              DebugLog("Trying to resync with latest available fragment");
                              $this->UpdateBootstrapInfo($cc, $this->bootstrapUrl);
                              $fragNum        = $this->fragCount - 1;
                              $this->lastFrag = $fragNum;
                              unset($this->frags);
                            }
                        }
                    }
                  unset($download);
                }
              usleep(40000);
            }

          echo "\n";
          DebugLog("\nAll fragments downloaded successfully\n");
          $cc->stopDownloads();
        }

      function VerifyFragment($frag)
        {
          $fragPos = 0;
          $fragLen = strlen($frag);

          // Some moronic live servers add wrong boxSize in header causing verification to fail
          if ($this->live)
              return true;

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

      function RenameFragments($baseFilename, $fragNum, $fileExt)
        {
          $files   = array();
          $retries = 0;

          if (!file_exists($baseFilename . ($fragNum + 1) . $fileExt))
              $fileExt = "";
          while (true)
            {
              if ($retries >= 50)
                  break;
              $file = $baseFilename . ++$fragNum . $fileExt;
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

      function WriteMetadata($flv = false)
        {
          if (isset($this->media) and $this->media['metadata'])
            {
              $metadataSize = strlen($this->media['metadata']);
              WriteByte($metadata, 0, SCRIPT_DATA);
              WriteInt24($metadata, 1, $metadataSize);
              WriteInt24($metadata, 4, 0);
              WriteInt32($metadata, 7, 0);
              $metadata = implode("", $metadata) . $this->media['metadata'];
              WriteByte($metadata, $this->tagHeaderLen + $metadataSize - 1, 0x09);
              WriteInt32($metadata, $this->tagHeaderLen + $metadataSize, $this->tagHeaderLen + $metadataSize);
              if (is_resource($flv))
                {
                  fwrite($flv, $metadata, $this->tagHeaderLen + $metadataSize + $this->prevTagSize);
                  return true;
                }
              else
                  return $metadata;
            }
          return false;
        }

      function WriteFlvTimestamp(&$frag, $fragPos, $packetTS)
        {
          WriteInt24($frag, $fragPos + 4, ($packetTS & 0x00FFFFFF));
          WriteByte($frag, $fragPos + 7, ($packetTS & 0xFF000000) >> 24);
        }

      function DecodeFragment($frag, $fragNum, $opt = array())
        {
          $debug = $this->debug;
          $flv   = false;
          extract($opt, EXTR_IF_EXISTS);

          $flvData = "";
          $fragLen = 0;
          $fragPos = 0;

          $fragLen = strlen($frag);
          if (!$this->VerifyFragment($frag))
            {
              Message("Skipping fragment number $fragNum");
              return false;
            }

          while ($fragPos < $fragLen)
            {
              ReadBoxHeader($frag, $fragPos, $boxType, $boxSize);
              if ($boxType == "mdat")
                  break;
              $fragPos += $boxSize;
            }

          DebugLog(sprintf("\nFragment %d:\n" . $this->format . "%-16s", $fragNum, "Type", "CurrentTS", "PreviousTS", "Size", "Position"), $debug);
          while ($fragPos < $fragLen)
            {
              $packetType = ReadByte($frag, $fragPos);
              $packetSize = ReadInt24($frag, $fragPos + 1);
              $packetTS   = ReadInt24($frag, $fragPos + 4);
              $packetTS   = $packetTS | (ReadByte($frag, $fragPos + 7) << 24);
              if ($packetTS & 0x80000000)
                {
                  $packetTS &= 0x7FFFFFFF;
                  $this->WriteFlvTimestamp($frag, $fragPos, $packetTS);
                }
              if (($this->baseTS === false) and (($packetType == AUDIO) or ($packetType == VIDEO)))
                  $this->baseTS = $packetTS;
              if ($this->baseTS > 1000)
                {
                  $packetTS -= $this->baseTS;
                  $this->WriteFlvTimestamp($frag, $fragPos, $packetTS);
                }
              $totalTagLen = $this->tagHeaderLen + $packetSize + $this->prevTagSize;
              switch ($packetType)
              {
                  case AUDIO:
                      if ($packetTS >= $this->prevAudioTS - TIMECODE_DURATION * 5)
                        {
                          $FrameInfo = ReadByte($frag, $fragPos + $this->tagHeaderLen);
                          $CodecID   = ($FrameInfo & 0xF0) >> 4;
                          if ($CodecID == CODEC_ID_AAC)
                            {
                              $AAC_PacketType = ReadByte($frag, $fragPos + $this->tagHeaderLen + 1);
                              if ($AAC_PacketType == AAC_SEQUENCE_HEADER)
                                {
                                  if ($this->AAC_HeaderWritten)
                                    {
                                      DebugLog(sprintf("%s\n" . $this->format, "Skipping AAC sequence header", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                                      break;
                                    }
                                  else
                                    {
                                      DebugLog("Writing AAC sequence header", $debug);
                                      $this->AAC_HeaderWritten = true;
                                      $this->prevAAC_Header    = true;
                                    }
                                }
                              else if (!$this->AAC_HeaderWritten)
                                {
                                  DebugLog(sprintf("%s\n" . $this->format, "Discarding audio packet received before AAC sequence header", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                                  break;
                                }
                            }
                          if ($packetSize > 0)
                            {
                              // Check for packets with non-monotonic audio timestamps and fix them
                              if (!$this->prevAAC_Header and ($packetTS <= $this->prevAudioTS))
                                {
                                  DebugLog(sprintf("%s\n" . $this->format, "Fixing audio timestamp", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                                  $packetTS += TIMECODE_DURATION + ($this->prevAudioTS - $packetTS);
                                  $this->WriteFlvTimestamp($frag, $fragPos, $packetTS);
                                }
                              if (($CodecID == CODEC_ID_AAC) and ($AAC_PacketType != AAC_SEQUENCE_HEADER))
                                  $this->prevAAC_Header = false;
                              if (is_resource($flv))
                                {
                                  $pAudioTagPos = ftell($flv);
                                  $status       = fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                                  if (!$status)
                                      Quit("Failed to write flv data to file");
                                  if ($debug)
                                      DebugLog(sprintf($this->format . "%-16s", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize, $pAudioTagPos));
                                }
                              else
                                {
                                  $flvData .= substr($frag, $fragPos, $totalTagLen);
                                  if ($debug)
                                      DebugLog(sprintf($this->format, "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                                }
                              $this->prevAudioTS = $packetTS;
                              $pAudioTagLen      = $totalTagLen;
                            }
                          else
                              DebugLog(sprintf("%s\n" . $this->format, "Skipping small sized audio packet", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                        }
                      else
                          DebugLog(sprintf("%s\n" . $this->format, "Skipping audio packet in fragment $fragNum", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                      if (!$this->audio)
                          $this->audio = true;
                      break;
                  case VIDEO:
                      if ($packetTS >= $this->prevVideoTS - TIMECODE_DURATION * 5)
                        {
                          $FrameInfo = ReadByte($frag, $fragPos + $this->tagHeaderLen);
                          $FrameType = ($FrameInfo & 0xF0) >> 4;
                          $CodecID   = $FrameInfo & 0x0F;
                          if ($FrameType == FRAME_TYPE_INFO)
                            {
                              DebugLog(sprintf("%s\n" . $this->format, "Skipping video info frame", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                              break;
                            }
                          if ($CodecID == CODEC_ID_AVC)
                            {
                              $AVC_PacketType = ReadByte($frag, $fragPos + $this->tagHeaderLen + 1);
                              if ($AVC_PacketType == AVC_SEQUENCE_HEADER)
                                {
                                  if ($this->AVC_HeaderWritten)
                                    {
                                      DebugLog(sprintf("%s\n" . $this->format, "Skipping AVC sequence header", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                                      break;
                                    }
                                  else
                                    {
                                      DebugLog("Writing AVC sequence header", $debug);
                                      $this->AVC_HeaderWritten = true;
                                      $this->prevAVC_Header    = true;
                                    }
                                }
                              else if (!$this->AVC_HeaderWritten)
                                {
                                  DebugLog(sprintf("%s\n" . $this->format, "Discarding video packet received before AVC sequence header", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                                  break;
                                }
                            }
                          if ($packetSize > 0)
                            {
                              // Check for packets with non-monotonic video timestamps and fix them
                              if (!$this->prevAVC_Header and (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType != AVC_SEQUENCE_END)) and ($packetTS <= $this->prevVideoTS))
                                {
                                  DebugLog(sprintf("%s\n" . $this->format, "Fixing video timestamp", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                                  $packetTS += TIMECODE_DURATION + ($this->prevVideoTS - $packetTS);
                                  $this->WriteFlvTimestamp($frag, $fragPos, $packetTS);
                                }
                              if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType != AVC_SEQUENCE_HEADER))
                                  $this->prevAVC_Header = false;
                              if (is_resource($flv))
                                {
                                  $pVideoTagPos = ftell($flv);
                                  $status       = fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                                  if (!$status)
                                      Quit("Failed to write flv data to file");
                                  if ($debug)
                                      DebugLog(sprintf($this->format . "%-16s", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize, $pVideoTagPos));
                                }
                              else
                                {
                                  $flvData .= substr($frag, $fragPos, $totalTagLen);
                                  if ($debug)
                                      DebugLog(sprintf($this->format, "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                                }
                              $this->prevVideoTS = $packetTS;
                              $pVideoTagLen      = $totalTagLen;
                            }
                          else
                              DebugLog(sprintf("%s\n" . $this->format, "Skipping small sized video packet", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                        }
                      else
                          DebugLog(sprintf("%s\n" . $this->format, "Skipping video packet in fragment $fragNum", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                      if (!$this->video)
                          $this->video = true;
                      break;
                  case SCRIPT_DATA:
                      break;
                  default:
                      die(sprintf("Unknown packet type %s encountered! Encrypted fragments can't be recovered.\n", $packetType));
              }
              $fragPos += $totalTagLen;
            }
          $this->duration = round($packetTS / 1000, 0);
          if (is_resource($flv))
            {
              $this->filesize = ftell($flv) / (1024 * 1024);
              return true;
            }
          else
              return $flvData;
        }

      function WriteLiveFragment($download, &$opt)
        {
          $this->frags[$download['id']] = $download;

          $available = count($this->frags);
          for ($i = 0; $i < $available; $i++)
            {
              if (isset($this->frags[$this->lastFrag + 1]))
                {
                  $frag = $this->frags[$this->lastFrag + 1];
                  if ($frag !== false)
                    {
                      DebugLog("Writing fragment " . $frag['id'] . " to flv file");
                      if (!isset($opt['file']))
                        {
                          $opt['debug'] = false;
                          if ($this->play)
                              $outFile = STDERR;
                          else
                              $outFile = $this->outDir . "$this->baseFilename-" . $this->fileCount++ . ".flv";
                          $this->InitDecoder();
                          $this->DecodeFragment($frag['response'], $frag['id'], $opt);
                          $opt['file'] = WriteFlvFile($outFile, $this->audio, $this->video);

                          $opt['debug'] = $this->debug;
                          $this->InitDecoder();
                        }
                      $flvData = $this->DecodeFragment($frag['response'], $frag['id'], $opt);
                      $status  = fwrite($opt['file'], $flvData, strlen($flvData));
                      if (!$status)
                          Quit("Failed to write flv data");
                      $this->lastFrag = $frag['id'];
                    }
                  else
                    {
                      $this->lastFrag += 1;
                      DebugLog("Skipping failed fragment " . $this->lastFrag);
                    }
                  unset($this->frags[$this->lastFrag]);
                }
              else
                  break;

              if ($opt['tDuration'] and (($opt['duration'] + $this->duration) >= $opt['tDuration']))
                  die(sprintf("\n" . $GLOBALS['line'], "Finished recording " . ($opt['duration'] + $this->duration) . " seconds of content."));
              if ($opt['filesize'] and ($this->filesize >= $opt['filesize']))
                {
                  $this->filesize = 0;
                  $opt['duration'] += $this->duration;
                  fclose($opt['file']);
                  unset($opt['file']);
                }

              // Update bootstrap info after successful writing of last known fragment
              if ($this->lastFrag == $this->fragCount)
                  $this->UpdateBootstrapInfo($opt['cc'], $this->bootstrapUrl);
            }

          if (!count($this->frags))
              $this->frags = array();
        }
    }

  function ReadByte($str, $pos)
    {
      $int = unpack("C", $str[$pos]);
      return $int[1];
    }

  function ReadInt24($str, $pos)
    {
      $int32 = unpack("N", "\x00" . substr($str, $pos, 3));
      return $int32[1];
    }

  function ReadInt32($str, $pos)
    {
      $int32 = unpack("N", substr($str, $pos, 4));
      return $int32[1];
    }

  function ReadInt64($str, $pos)
    {
      $hi    = sprintf("%u", ReadInt32($str, $pos));
      $lo    = sprintf("%u", ReadInt32($str, $pos + 4));
      $int64 = bcadd(bcmul($hi, "4294967296"), $lo);
      return $int64;
    }

  function ReadString($frag, &$fragPos)
    {
      $strlen = 0;
      while ($frag[$fragPos + $strlen] != "\x00")
          $strlen++;
      $str = substr($frag, $fragPos, $strlen);
      $fragPos += $strlen + 1;
      return $str;
    }

  function ReadBoxHeader($str, &$pos, &$boxType, &$boxSize)
    {
      if (!$pos)
          $pos = 0;
      $boxSize = ReadInt32($str, $pos);
      $boxType = substr($str, $pos + 4, 4);
      if ($boxSize == 1)
        {
          $boxSize = ReadInt64($str, $pos + 8) - 16;
          $pos += 16;
        }
      else
        {
          $boxSize -= 8;
          $pos += 8;
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

  function DebugLog($msg, $display = true)
    {
      global $debug, $logfile;
      if ($display and $debug)
          fwrite($logfile, $msg . "\n");
    }

  function GetString($xmlObject)
    {
      return trim((string) $xmlObject);
    }

  function KeyName(array $a, $pos)
    {
      $temp = array_slice($a, $pos, 1, true);
      return key($temp);
    }

  function Message($msg)
    {
      printf($GLOBALS['line'], $msg);
    }

  function NormalizePath($path)
    {
      $inSegs  = preg_split('/(?<!\/)\/(?!\/)/u', $path);
      $outSegs = array();

      foreach ($inSegs as $seg)
        {
          if ($seg == '' || $seg == '.')
              continue;
          if ($seg == '..')
              array_pop($outSegs);
          else
              array_push($outSegs, $seg);
        }
      $outPath = implode('/', $outSegs);

      if (substr($path, 0, 1) == '/')
          $outPath = '/' . $outPath;
      if (substr($path, -1) == '/')
          $outPath .= '/';
      return $outPath;
    }

  function Quit($msg)
    {
      die(sprintf($GLOBALS['line'], $msg));
    }

  function ShowHeader($header)
    {
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  function WriteFlvFile($outFile, $audio = true, $video = true)
    {
      $flvHeader    = pack("H*", "464c5601050000000900000000");
      $flvHeaderLen = strlen($flvHeader);

      if (!$video or !$audio)
          if ($audio & !$video)
              $flvHeader[4] = "\x04";
          else if ($video & !$audio)
              $flvHeader[4] = "\x01";

      if (is_resource($outFile))
          $flv = $outFile;
      else
          $flv = fopen($outFile, "w+b");
      if (!$flv)
          Quit("Failed to open " . $outFile);
      fwrite($flv, $flvHeader, $flvHeaderLen);
      return $flv;
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

  ShowHeader("KSV Adobe HDS Downloader");
  $format       = " %-8s%-16s%-16s%-8s";
  $line         = "%-79s\n";
  $baseFilename = "";
  $debug        = false;
  $duration     = 0;
  $delete       = false;
  $fileExt      = ".f4f";
  $fileCount    = 1;
  $filesize     = 0;
  $fragCount    = 0;
  $fragNum      = 0;
  $logfile      = STDERR;
  $manifest     = "";
  $outDir       = "";
  $play         = false;
  $rename       = false;
  $start        = 0;

  // Check for required extensions
  $extensions = array(
      "bcmath",
      "curl",
      "SimpleXML"
  );
  foreach ($extensions as $extension)
      if (!extension_loaded($extension))
          Quit("You don't have $extension extension installed. please install it before continuing.");

  // Initialize classes
  $cc  = new cURL();
  $cli = new CLI();
  $f4f = new F4F();

  $f4f->baseFilename =& $baseFilename;
  $f4f->debug =& $debug;
  $f4f->format =& $format;
  $f4f->rename =& $rename;

  // Process command line options
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }
  if ($cli->getParam('debug'))
      $debug = true;
  if ($cli->getParam('delete'))
      $delete = true;
  if ($cli->getParam('fproxy'))
      $cc->fragProxy = true;
  if ($cli->getParam('play'))
      $play = true;
  if ($cli->getParam('rename'))
      $rename = $cli->getParam('rename');
  if ($cli->getParam('auth'))
      $f4f->auth = "?" . $cli->getParam('auth');
  if ($cli->getParam('duration'))
      $duration = $cli->getParam('duration');
  if ($cli->getParam('filesize'))
      $filesize = $cli->getParam('filesize');
  if ($cli->getParam('fragments'))
      $baseFilename = $cli->getParam('fragments');
  if ($cli->getParam('manifest'))
      $manifest = $cli->getParam('manifest');
  if ($cli->getParam('outdir'))
      $outDir = $cli->getParam('outdir');
  if ($cli->getParam('parallel'))
      $f4f->parallel = $cli->getParam('parallel');
  if ($cli->getParam('proxy'))
      $cc->proxy = $cli->getParam('proxy');
  if ($cli->getParam('quality'))
      $f4f->quality = $cli->getParam('quality');
  if ($cli->getParam('start'))
      $start = $cli->getParam('start');
  if ($cli->getParam('useragent'))
      $cc->user_agent = $cli->getParam('useragent');

  // Create output directory
  if ($outDir)
    {
      $outDir = rtrim(str_replace('\\', '/', $outDir));
      if (substr($outDir, -1) != '/')
          $outDir = $outDir . '/';
      if (!file_exists($outDir))
        {
          DebugLog("Creating destination directory " . $outDir);
          mkdir($outDir, 0777, true);
        }
    }
  $f4f->outDir = $outDir;

  // Redirect debug output and disable filesize when piping
  if ($play)
    {
      $filesize  = 0;
      $logfile   = STDOUT;
      $f4f->play = true;
    }

  // Download fragments when manifest is available
  if ($manifest)
    {
      $opt = array(
          'start' => $start,
          'tDuration' => $duration,
          'filesize' => $filesize
      );
      $f4f->DownloadFragments($cc, $manifest, $opt);
    }

  // Determine output filename
  $baseFilename = str_replace('\\', '/', $baseFilename);
  if ($baseFilename and (substr($baseFilename, -1) != '/') and (substr($baseFilename, -1) != ':'))
    {
      if (strrpos($baseFilename, '/'))
          $outFile = substr($baseFilename, strrpos($baseFilename, '/') + 1);
      else
          $outFile = $baseFilename;
    }
  else
      $outFile = "Joined";
  $outFile = $outDir . $outFile;

  // Check for available fragments and rename if required
  if ($f4f->fragNum)
      $fragNum = $f4f->fragNum;
  else if ($start)
      $fragNum = $start - 1;
  if ($rename)
    {
      $f4f->RenameFragments($baseFilename, $fragNum, $fileExt);
      $fragNum = 0;
    }
  $count = $fragNum + 1;
  while (true)
    {
      if (file_exists($baseFilename . $count . $fileExt))
          $fragCount++;
      else if (file_exists($baseFilename . $count))
        {
          $fileExt = "";
          $fragCount++;
        }
      else
          break;
      $count++;
    }
  Message("Found $fragCount fragments");

  // Process available fragments
  if (!$fragCount)
      exit(1);
  $timeStart = microtime(true);
  DebugLog("Joining Fragments:");
  for ($i = $fragNum + 1; $i <= $fragNum + $fragCount; $i++)
    {
      $frag = file_get_contents($baseFilename . $i . $fileExt);
      if (!isset($opt['flv']))
        {
          $opt['debug'] = false;
          $f4f->InitDecoder();
          $f4f->DecodeFragment($frag, $i, $opt);
          $opt['flv'] = WriteFlvFile($outFile . "-" . $fileCount++ . ".flv", $f4f->audio, $f4f->video);
          if (!($fragNum > 0) and !$filesize)
              $f4f->WriteMetadata($opt['flv']);

          $opt['debug'] = $debug;
          $f4f->InitDecoder();
        }
      $f4f->DecodeFragment($frag, $i, $opt);
      if ($filesize and ($f4f->filesize >= $filesize))
        {
          $f4f->filesize = 0;
          fclose($opt['flv']);
          unset($opt['flv']);
        }
      echo "Processed " . ($i - $fragNum) . " fragments\r";
    }
  fclose($opt['flv']);
  $timeEnd   = microtime(true);
  $timeTaken = sprintf("%.2f", $timeEnd - $timeStart);
  Message("Joined $fragCount fragments in $timeTaken seconds");

  // Delete fragments after processing
  if ($delete)
    {
      for ($i = $fragNum + 1; $i <= $fragNum + $fragCount; $i++)
          if (file_exists($baseFilename . $i . $fileExt))
              unlink($baseFilename . $i . $fileExt);
    }

  Message("Finished");
?>
