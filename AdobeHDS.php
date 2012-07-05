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
              'outdir'    => 'destination folder for output file',
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
          $this->user_agent  = 'Mozilla/5.0 (Windows NT 5.1; rv:13.0) Gecko/20100101 Firefox/13.0.1';
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

  class F4F
    {
      var $auth, $baseFilename, $baseUrl, $debug, $format, $media, $parallel, $quality, $rename;
      var $prevTagSize, $tagHeaderLen;
      var $segTable, $fragTable, $fragCount;
      var $prevAudioTS, $prevVideoTS, $pAudioTagLen, $pVideoTagLen, $pAudioTagPos, $pVideoTagPos;
      var $prevAVC_Header, $prevAAC_Header, $AVC_HeaderWritten, $AAC_HeaderWritten;

      public function __construct()
        {
          $this->auth              = "";
          $this->baseFilename      = "";
          $this->debug             = false;
          $this->format            = "";
          $this->parallel          = 8;
          $this->quality           = "high";
          $this->rename            = false;
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

      function ParseManifest($cc, $manifest)
        {
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
                      $cc->get("$this->baseUrl/$bootstrapUrl");
                  $this->media[$bitrate]['bootstrap'] = $cc->response;
                }
              else
                  $this->media[$bitrate]['bootstrap'] = base64_decode(GetString($bootstrap[0]));
              $metadata = $xml->xpath("/ns:manifest/ns:media[@streamId='" . $streamId . "']/ns:metadata");
              if (isset($metadata[0]))
                  $this->media[$bitrate]['metadata'] = GetString($metadata[0]);
              else
                  $this->media[$bitrate]['metadata'] = "";
              $this->media[$bitrate]['url'] = GetString($stream['url']);
            }

          // Available qualities
          krsort($this->media, SORT_NUMERIC);
          DebugLog("Manifest Entries:\n");
          DebugLog(sprintf(" %-8s%s", "Bitrate", "URL"));
          for ($i = 0; $i < count($this->media); $i++)
            {
              $key = KeyName($this->media, $i);
              DebugLog(sprintf(" %-8d%s", $key, $this->media[$key]['url']));
            }
          DebugLog("");

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
                  if ($key = KeyName($this->media, $this->quality))
                    {
                      $this->media = $this->media[$key];
                      break;
                    }
                  else
                      $this->quality -= 1;
                }
            }

          $bootstrapInfo = $this->media['bootstrap'];
          ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
          if ($boxType == "abst")
              $this->ParseBootstrapBox($bootstrapInfo, $pos);
          else
              die("Failed to parse bootstrap info");
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
          $this->fragCount = $this->segTable[1]['fragmentsPerSegment'];
        }

      function ParseAfrtBox($afrt, $pos)
        {
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
          DebugLog(sprintf("%s:\n\n %-8s%-16s%-16s%-16s", "Fragment Entries", "Number", "Timestamp", "Duration", "Discontinuity"));
          for ($i = 0; $i < $fragCount; $i++)
            {
              $firstFragment = ReadInt32($afrt, $pos);
              $fragEntry =& $this->fragTable[$firstFragment];
              $fragEntry['firstFragment']          = $firstFragment;
              $fragEntry['firstFragmentTimestamp'] = ReadInt32($afrt, $pos + 8);
              $fragEntry['fragmentDuration']       = ReadInt32($afrt, $pos + 12);
              $fragEntry['discontinuityIndicator'] = "";
              $pos += 16;
              if ($fragEntry['fragmentDuration'] == 0)
                  $fragEntry['discontinuityIndicator'] = ReadByte($afrt, $pos++);
              DebugLog(sprintf(" %-8s%-16s%-16s%-16s", $fragEntry['firstFragment'], $fragEntry['firstFragmentTimestamp'], $fragEntry['fragmentDuration'], $fragEntry['discontinuityIndicator']));
            }
          DebugLog("");
        }

      function DownloadFragments($cc, $manifest)
        {
          $fragNum = 0;

          // Extract baseUrl and baseFilename from manifest url
          if (strpos($manifest, '?') !== false)
            {
              $this->baseUrl = substr($manifest, 0, strpos($manifest, '?'));
              $this->baseUrl = substr($this->baseUrl, 0, strrpos($this->baseUrl, '/'));
            }
          else
              $this->baseUrl = substr($manifest, 0, strrpos($manifest, '/'));
          $this->ParseManifest($cc, $manifest);
          if (strncasecmp($this->media['url'], "http", 4) == 0)
            {
              if (substr($this->media['url'], -1) == '/')
                  $this->baseFilename = substr($this->media['url'], 0, -1);
              else
                  $this->baseFilename = $this->media['url'];
              $this->baseFilename = substr($this->baseFilename, strrpos($this->baseFilename, '/') + 1) . "Seg1-Frag";
              $fragUrl            = $this->media['url'] . "Seg1-Frag";
            }
          else
            {
              if (substr($this->media['url'], -1) == '/')
                  $this->baseFilename = substr($this->media['url'], 0, -1) . "Seg1-Frag";
              else
                  $this->baseFilename = $this->media['url'] . "Seg1-Frag";
              $fragUrl = $this->baseUrl . "/" . $this->media['url'] . "Seg1-Frag";
            }
          $GLOBALS['baseFilename'] = $this->baseFilename;
          DebugLog("Downloading Fragments:\n");

          while (($fragNum < $this->fragCount) or $cc->active)
            {
              while ((count($cc->ch) < $this->parallel) and ($fragNum < $this->fragCount))
                {
                  $fragNum += 1;
                  echo "Downloading $fragNum/$this->fragCount fragments\r";
                  if (in_array_field($fragNum, "firstFragment", $this->fragTable, true))
                      $discontinuity = value_in_array_field($fragNum, "firstFragment", "discontinuityIndicator", $this->fragTable, true);
                  if (($discontinuity == 1) or ($discontinuity == 3))
                    {
                      $this->rename = true;
                      continue;
                    }
                  if (file_exists("$this->baseFilename$fragNum"))
                    {
                      DebugLog("Fragment $fragNum is already downloaded");
                      continue;
                    }
                  DebugLog("Adding fragment $fragNum to download queue");
                  $cc->addDownload("$fragUrl$fragNum$this->auth", "$this->baseFilename$fragNum");
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
                              DebugLog("Fragment " . $download['id'] . " successfully downloaded");
                              file_put_contents($download['id'], $download['response']);
                            }
                          else
                            {
                              DebugLog("Fragment " . $download['id'] . " failed to verify");
                              DebugLog("Adding fragment " . $download['id'] . " to download queue");
                              $cc->addDownload($download['url'], $download['id']);
                            }
                        }
                      else if ($download['status'] == 403)
                          die("Access Denied! Unable to download fragments.");
                      else if ($download['status'] === false)
                        {
                          DebugLog("Fragment " . $download['id'] . " failed to download");
                          DebugLog("Adding fragment " . $download['id'] . " to download queue");
                          $cc->addDownload($download['url'], $download['id']);
                        }
                      else
                        {
                          DebugLog("Fragment " . $download['id'] . " doesn't exist");
                          $this->rename = true;
                        }
                    }
                }
              usleep(50000);
            }

          echo "\n";
          DebugLog("\nAll fragments downloaded successfully\n");
          $cc->stopDownloads();
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

      function WriteMetadata($flv = false)
        {
          if (isset($this->media) and $this->media['metadata'])
            {
              $this->media['metadata'] = base64_decode($this->media['metadata']);
              $metadataSize            = strlen($this->media['metadata']);
              WriteByte($metadata, 0, SCRIPT_DATA);
              WriteInt24($metadata, 1, $metadataSize);
              WriteInt24($metadata, 4, 0);
              WriteInt32($metadata, 7, 0);
              $metadata = implode("", $metadata) . $this->media['metadata'];
              WriteByte($metadata, $this->tagHeaderLen + $metadataSize - 1, 0x09);
              WriteInt32($metadata, $this->tagHeaderLen + $metadataSize, $this->tagHeaderLen + $metadataSize);
              if ($flv)
                {
                  fwrite($flv, $metadata, $this->tagHeaderLen + $metadataSize + $this->prevTagSize);
                  return true;
                }
              else
                  return $metadata;
            }
          return false;
        }

      function DecodeFragment($frag, $fragNum, $flv = false)
        {
          $flvData = "";
          $fragLen = 0;
          $fragPos = 0;

          $fragLen = strlen($frag);
          if (!$this->VerifyFragment($frag))
            {
              echo "Skipping fragment number $fragNum\n";
              return false;
            }

          while ($fragPos < $fragLen)
            {
              ReadBoxHeader($frag, $fragPos, $boxType, $boxSize);
              if ($boxType == "mdat")
                  break;
              $fragPos += $boxSize;
            }

          DebugLog(sprintf("\nFragment %d:\n" . $this->format . "%-16s", $fragNum, "Type", "CurrentTS", "PreviousTS", "Size", "Position"));
          while ($fragPos < $fragLen)
            {
              $packetType  = ReadByte($frag, $fragPos);
              $packetSize  = ReadInt24($frag, $fragPos + 1);
              $packetTS    = ReadInt24($frag, $fragPos + 4);
              $packetTS    = $packetTS | (ReadByte($frag, $fragPos + 7) << 24);
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
                                      DebugLog(sprintf("%s\n" . $this->format, "Skipping AAC sequence header", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                                      break;
                                    }
                                  else
                                    {
                                      DebugLog("Writing AAC sequence header");
                                      $this->AAC_HeaderWritten = true;
                                      $this->prevAAC_Header    = true;
                                    }
                                }
                              else if (!$this->AAC_HeaderWritten)
                                {
                                  DebugLog(sprintf("%s\n" . $this->format, "Discarding audio packet received before AAC sequence header", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                                  break;
                                }
                            }
                          if ($packetSize > 0)
                            {
                              // Check for packets with non-monotonic audio timestamps and fix them
                              if (!$this->prevAAC_Header and ($packetTS <= $this->prevAudioTS))
                                {
                                  $packetTS += TIMECODE_DURATION + ($this->prevAudioTS - $packetTS);
                                  WriteInt24($frag, $fragPos + 4, ($packetTS & 0x00FFFFFF));
                                  WriteByte($frag, $fragPos + 7, ($packetTS & 0xFF000000) >> 24);
                                  DebugLog(sprintf("%s\n" . $this->format, "Fixing audio timestamp", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                                }
                              if (($CodecID == CODEC_ID_AAC) and ($AAC_PacketType != AAC_SEQUENCE_HEADER))
                                  $this->prevAAC_Header = false;
                              if ($flv)
                                {
                                  $pAudioTagPos = ftell($flv);
                                  fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                                  if ($this->debug)
                                      DebugLog(sprintf($this->format . "%-16s", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize, $pAudioTagPos));
                                }
                              else
                                {
                                  $flvData .= substr($frag, $fragPos, $totalTagLen);
                                  if ($this->debug)
                                      DebugLog(sprintf($this->format, "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                                }
                              $this->prevAudioTS = $packetTS;
                              $pAudioTagLen      = $totalTagLen;
                            }
                          else
                              DebugLog(sprintf("%s\n" . $this->format, "Skipping small sized audio packet", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                        }
                      else
                          DebugLog(sprintf("%s\n" . $this->format, "Skipping audio packet in fragment $fragNum", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                      break;
                  case VIDEO:
                      if ($packetTS >= $this->prevVideoTS - TIMECODE_DURATION * 5)
                        {
                          $FrameInfo = ReadByte($frag, $fragPos + $this->tagHeaderLen);
                          $FrameType = ($FrameInfo & 0xF0) >> 4;
                          $CodecID   = $FrameInfo & 0x0F;
                          if ($FrameType == FRAME_TYPE_INFO)
                            {
                              DebugLog(sprintf("%s\n" . $this->format, "Skipping video info frame", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                              break;
                            }
                          if ($CodecID == CODEC_ID_AVC)
                            {
                              $AVC_PacketType = ReadByte($frag, $fragPos + $this->tagHeaderLen + 1);
                              if ($AVC_PacketType == AVC_SEQUENCE_HEADER)
                                {
                                  if ($this->AVC_HeaderWritten)
                                    {
                                      DebugLog(sprintf("%s\n" . $this->format, "Skipping AVC sequence header", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                                      break;
                                    }
                                  else
                                    {
                                      DebugLog("Writing AVC sequence header");
                                      $this->AVC_HeaderWritten = true;
                                      $this->prevAVC_Header    = true;
                                    }
                                }
                              else if (!$this->AVC_HeaderWritten)
                                {
                                  DebugLog(sprintf("%s\n" . $this->format, "Discarding video packet received before AVC sequence header", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                                  break;
                                }
                            }
                          if ($packetSize > 0)
                            {
                              // Check for packets with non-monotonic video timestamps and fix them
                              if (!$this->prevAVC_Header and (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType != AVC_SEQUENCE_END)) and ($packetTS <= $this->prevVideoTS))
                                {
                                  $packetTS += TIMECODE_DURATION + ($this->prevVideoTS - $packetTS);
                                  WriteInt24($frag, $fragPos + 4, ($packetTS & 0x00FFFFFF));
                                  WriteByte($frag, $fragPos + 7, ($packetTS & 0xFF000000) >> 24);
                                  DebugLog(sprintf("%s\n" . $this->format, "Fixing video timestamp", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                                }
                              if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType != AVC_SEQUENCE_HEADER))
                                  $this->prevAVC_Header = false;
                              if ($flv)
                                {
                                  $pVideoTagPos = ftell($flv);
                                  fwrite($flv, substr($frag, $fragPos, $totalTagLen), $totalTagLen);
                                  if ($this->debug)
                                      DebugLog(sprintf($this->format . "%-16s", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize, $pVideoTagPos));
                                }
                              else
                                {
                                  $flvData .= substr($frag, $fragPos, $totalTagLen);
                                  if ($this->debug)
                                      DebugLog(sprintf($this->format, "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                                }
                              $this->prevVideoTS = $packetTS;
                              $pVideoTagLen      = $totalTagLen;
                            }
                          else
                              DebugLog(sprintf("%s\n" . $this->format, "Skipping small sized video packet", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                        }
                      else
                          DebugLog(sprintf("%s\n" . $this->format, "Skipping video packet in fragment $fragNum", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                      break;
                  case SCRIPT_DATA:
                      break;
                  default:
                      die(sprintf("Unknown packet type %s encountered! Encrypted fragments can't be recovered.", $packetType));
              }
              $fragPos += $totalTagLen;
            }
          if ($flv)
              return true;
          else
              return $flvData;
        }
    }

  function ReadByte($str, $pos)
    {
      $int = unpack("C", substr($str, $pos, 1));
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

  function ReadString($frag, &$fragPos)
    {
      $strlen = 0;
      while ($frag[$fragPos + $strlen] != "\x00")
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

  function GetString($xmlObject)
    {
      return trim((string) $xmlObject);
    }

  function KeyName(array $a, $pos)
    {
      $temp = array_slice($a, $pos, 1, true);
      return key($temp);
    }

  function ShowHeader($header)
    {
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
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
  $flvHeader    = pack("H*", "464c5601050000000900000000");
  $flvHeaderLen = strlen($flvHeader);
  $format       = " %-8s%-16s%-16s%-8s";
  $baseFilename = "";
  $debug        = false;
  $delete       = false;
  $fileExt      = ".f4f";
  $fragCount    = 0;
  $outDir       = "";

  // Initialize classes
  $cc  = new cURL();
  $cli = new CLI();
  $f4f = new F4F();

  // Process command line options
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }
  if ($cli->getParam('debug'))
    {
      $debug      = true;
      $f4f->debug = $debug;
    }
  if ($cli->getParam('delete'))
      $delete = true;
  if ($cli->getParam('auth'))
      $f4f->auth = "?" . $cli->getParam('auth');
  if ($cli->getParam('fragments'))
    {
      $baseFilename      = $cli->getParam('fragments');
      $f4f->baseFilename = $baseFilename;
    }
  if ($cli->getParam('outdir'))
      $outDir = $cli->getParam('outdir');
  if ($cli->getParam('parallel'))
      $f4f->parallel = $cli->getParam('parallel');
  if ($cli->getParam('proxy'))
      $cc->proxy = $cli->getParam('proxy');
  if ($cli->getParam('quality'))
      $f4f->quality = $cli->getParam('quality');
  if ($cli->getParam('useragent'))
      $cc->user_agent = $cli->getParam('useragent');
  if ($cli->getParam('manifest'))
      $f4f->DownloadFragments($cc, $cli->getParam('manifest'));
  if ($cli->getParam('rename') or $f4f->rename)
      $f4f->RenameFragments($baseFilename, $fileExt);

  $f4f->format  = $format;
  $baseFilename = str_replace('\\', '/', $baseFilename);
  if ((substr($baseFilename, -1) != '/') and (substr($baseFilename, -1) != ':'))
    {
      if (strrpos($baseFilename, '/'))
          $outFile = substr($baseFilename, strrpos($baseFilename, '/') + 1) . ".flv";
      else
          $outFile = $baseFilename . ".flv";
    }
  else
      $outFile = "Joined.flv";
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
  $outFile = $outDir . $outFile;

  // Check for available fragments
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

  // Write flv header and metadata
  if ($fragCount)
    {
      $flv = fopen($outFile, "w+b");
      if (!$flv)
          die("Failed to open " . $outFile);
      fwrite($flv, $flvHeader, $flvHeaderLen);
      $f4f->WriteMetadata($flv);
    }
  else
      exit(1);

  // Process available fragments
  $timeStart = microtime(true);
  DebugLog("Joining Fragments:");
  for ($i = 1; $i <= $fragCount; $i++)
    {
      $frag = file_get_contents($baseFilename . $i . $fileExt);
      $f4f->DecodeFragment($frag, $i, $flv);
      echo "Processed $i fragments\r";
    }
  fclose($flv);
  $timeEnd   = microtime(true);
  $timeTaken = sprintf("%.2f", $timeEnd - $timeStart);
  echo "Joined $fragCount fragments in $timeTaken seconds\n";

  // Delete fragments after processing
  if ($delete)
      for ($i = 1; $i <= $fragCount; $i++)
        {
          if (file_exists($baseFilename . $i . $fileExt))
              unlink($baseFilename . $i . $fileExt);
        }

  echo "Finished\n";
?>
