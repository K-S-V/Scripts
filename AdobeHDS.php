<?php
  define('AUDIO', 0x08);
  define('VIDEO', 0x09);
  define('AKAMAI_ENC_AUDIO', 0x0A);
  define('AKAMAI_ENC_VIDEO', 0x0B);
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
  define('STOP_PROCESSING', 2);

  class CLI
    {
      protected static $ACCEPTED = array();
      var $params = array();

      function __construct($options = array(), $handleUnknown = false)
        {
          global $argc, $argv;

          if (count($options))
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

                  if ($paramSwitch and $isSwitch)
                      $this->error("[param] expected after '$paramSwitch' switch (" . self::$ACCEPTED[1][$paramSwitch] . ')');
                  else if (!$paramSwitch and !$isSwitch)
                    {
                      if ($handleUnknown)
                          $this->params['unknown'][] = $arg;
                      else
                          $this->error("'$arg' is an invalid option, use --help to display valid switches.");
                    }
                  else if (!$paramSwitch and $isSwitch)
                    {
                      if (isset($this->params[$arg]))
                          $this->error("'$arg' switch can't occur more than once");

                      $this->params[$arg] = true;
                      if (isset(self::$ACCEPTED[1][$arg]))
                          $paramSwitch = $arg;
                      else if (!isset(self::$ACCEPTED[0][$arg]))
                          $this->error("there's no '$arg' switch, use --help to display all switches.");
                    }
                  else if ($paramSwitch and !$isSwitch)
                    {
                      $this->params[$paramSwitch] = $arg;
                      $paramSwitch                = false;
                    }
                }
            }

          // Final check
          foreach ($this->params as $k => $v)
              if (isset(self::$ACCEPTED[1][$k]) and $v === true)
                  $this->error("[param] expected after '$k' switch (" . self::$ACCEPTED[1][$k] . ')');
        }

      function displayHelp()
        {
          LogInfo("You can use the script with following options:\n");
          foreach (self::$ACCEPTED[0] as $key => $value)
              LogInfo(sprintf(" --%-17s %s", $key, $value));
          foreach (self::$ACCEPTED[1] as $key => $value)
              LogInfo(sprintf(" --%-9s%-8s %s", $key, " [param]", $value));
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

  class cURL
    {
      var $headers, $user_agent, $compression, $cookie_file;
      var $active, $cert_check, $fragProxy, $maxSpeed, $proxy, $response;
      var $mh, $ch, $mrc;
      static $ref = 0;

      function __construct($cookies = true, $cookie = 'Cookies.txt', $compression = 'gzip', $proxy = '')
        {
          $this->headers     = $this->headers();
          $this->user_agent  = 'Mozilla/5.0 (Windows NT 5.1; rv:41.0) Gecko/20100101 Firefox/41.0';
          $this->compression = $compression;
          $this->cookies     = $cookies;
          if ($this->cookies == true)
              $this->cookie($cookie);
          $this->cert_check = false;
          $this->fragProxy  = false;
          $this->maxSpeed   = 0;
          $this->proxy      = $proxy;
          self::$ref++;
        }

      function __destruct()
        {
          $this->stopDownloads();
          if ((self::$ref <= 1) and file_exists($this->cookie_file))
              unlink($this->cookie_file);
          self::$ref--;
        }

      function headers()
        {
          $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
          $headers[] = 'Connection: Keep-Alive';
          return $headers;
        }

      function cookie($cookie_file)
        {
          if (file_exists($cookie_file))
              $this->cookie_file = $cookie_file;
          else
            {
              $file = fopen($cookie_file, 'w') or $this->error('The cookie file could not be opened. Make sure this directory has the correct permissions.');
              $this->cookie_file = $cookie_file;
              fclose($file);
            }
        }

      function get($url)
        {
          $process = curl_init($url);
          $options = array(
              CURLOPT_HTTPHEADER => $this->headers,
              CURLOPT_HEADER => 0,
              CURLOPT_USERAGENT => $this->user_agent,
              CURLOPT_ENCODING => $this->compression,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_FOLLOWLOCATION => 1
          );
          curl_setopt_array($process, $options);
          if (!$this->cert_check)
              curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
          if ($this->cookies == true)
            {
              curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
              curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
            }
          if ($this->proxy)
              $this->setProxy($process, $this->proxy);
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
          $process   = curl_init($url);
          $headers   = $this->headers;
          $headers[] = 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8';
          $options   = array(
              CURLOPT_HTTPHEADER => $headers,
              CURLOPT_HEADER => 1,
              CURLOPT_USERAGENT => $this->user_agent,
              CURLOPT_ENCODING => $this->compression,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_FOLLOWLOCATION => 1,
              CURLOPT_POST => 1,
              CURLOPT_POSTFIELDS => $data
          );
          curl_setopt_array($process, $options);
          if (!$this->cert_check)
              curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
          if ($this->cookies == true)
            {
              curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
              curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
            }
          if ($this->proxy)
              $this->setProxy($process, $this->proxy);
          $return = curl_exec($process);
          curl_close($process);
          return $return;
        }

      function setProxy(&$process, $proxy)
        {
          $type      = "";
          $separator = strpos($proxy, "://");
          if ($separator !== false)
            {
              $type  = strtolower(substr($proxy, 0, $separator));
              $proxy = substr($proxy, $separator + 3);
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
              return false;
          $download =& $this->ch[$id];
          $download['id']  = $id;
          $download['url'] = $url;
          $download['ch']  = curl_init($url);
          $options         = array(
              CURLOPT_HTTPHEADER => $this->headers,
              CURLOPT_HEADER => 0,
              CURLOPT_USERAGENT => $this->user_agent,
              CURLOPT_ENCODING => $this->compression,
              CURLOPT_LOW_SPEED_LIMIT => 1024,
              CURLOPT_LOW_SPEED_TIME => 10,
              CURLOPT_BINARYTRANSFER => 1,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_FOLLOWLOCATION => 1
          );
          curl_setopt_array($download['ch'], $options);
          if (!$this->cert_check)
              curl_setopt($download['ch'], CURLOPT_SSL_VERIFYPEER, false);
          if ($this->cookies == true)
            {
              curl_setopt($download['ch'], CURLOPT_COOKIEFILE, $this->cookie_file);
              curl_setopt($download['ch'], CURLOPT_COOKIEJAR, $this->cookie_file);
            }
          if ($this->fragProxy and $this->proxy)
              $this->setProxy($download['ch'], $this->proxy);
          if ($this->maxSpeed > 0)
              curl_setopt($download['ch'], CURLOPT_MAX_RECV_SPEED_LARGE, $this->maxSpeed);
          curl_multi_add_handle($this->mh, $download['ch']);
          do
            {
              $this->mrc = curl_multi_exec($this->mh, $this->active);
            } while ($this->mrc == CURLM_CALL_MULTI_PERFORM);
          return true;
        }

      function checkDownloads()
        {
          if (isset($this->mh))
            {
              curl_multi_select($this->mh);
              $this->mrc = curl_multi_exec($this->mh, $this->active);
              if ($this->mrc != CURLM_OK)
                  return false;
              while ($info = curl_multi_info_read($this->mh))
                {
                  foreach ($this->ch as $download)
                      if ($download['ch'] == $info['handle'])
                          break;
                  $array['id']  = $download['id'];
                  $array['url'] = $download['url'];
                  $info         = curl_getinfo($download['ch']);
                  if ($info['http_code'] == 0)
                    {
                      /* if curl fails due to network connectivity issues or some other reason it's *
                       * better to add some delay before next try to avoid busy loop.               */
                      LogDebug("Fragment " . $download['id'] . ": " . curl_error($download['ch']));
                      usleep(1000000);
                      $array['status']   = false;
                      $array['response'] = "";
                    }
                  else if ($info['http_code'] == 200)
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
              if (isset($this->ch))
                {
                  foreach ($this->ch as $download)
                    {
                      curl_multi_remove_handle($this->mh, $download['ch']);
                      curl_close($download['ch']);
                    }
                  unset($this->ch);
                }
              curl_multi_close($this->mh);
              unset($this->mh);
            }
        }

      function error($error)
        {
          LogError("cURL Error : $error");
        }
    }

  class AkamaiDecryptor
    {
      var $ecmID, $ecmTimestamp, $ecmVersion, $kdfVersion, $dccAccReserved, $prevEcmID;
      var $aes_cbc, $debug, $decryptBytes, $decryptorTest, $lastKeyUrl, $sessionID, $sessionKey, $sessionKeyUrl;
      var $packetIV, $packetKey, $packetSalt, $saltAesKey;

      function __construct()
        {
          $this->aes_cbc       = mcrypt_module_open('rijndael-128', '', 'cbc', '');
          $this->debug         = false;
          $this->decryptorTest = false;
          $this->lastKeyUrl    = "";
          $this->sessionID     = "";
          $this->sessionKey    = "";
          $this->sessionKeyUrl = "";
          $this->InitDecryptor();
        }

      function InitDecryptor()
        {
          $this->dccAccReserved = null;
          $this->decryptBytes   = 0;
          $this->ecmID          = null;
          $this->ecmTimestamp   = null;
          $this->ecmVersion     = null;
          $this->kdfVersion     = null;
          $this->packetIV       = null;
          $this->prevEcmID      = null;
          $this->saltAesKey     = null;
        }

      function KDF()
        {
          $debug = $this->debug;
          if ($this->decryptorTest)
              $debug = false;

          // KDF constants
          $hmacKey   = unhexlify("3b27bdc9e00fd5995d60a1ee0aa057a9f1416ed085b21762110f1c2204ddf80ec8caab003070fd43baafdde27aeb3194ece5c1adff406a51185eb5dd7300c058");
          $hmacData1 = unhexlify("d1ba6371c56ce6b498f1718228b0aa112f24a47bcad757a1d0b3f4c2b8bd637cb8080d9c8e7855b36a85722a60552a6c00");
          $hmacData2 = unhexlify("d1ba6371c56ce6b498f1718228b0aa112f24a47bcad757a1d0b3f4c2b8bd637cb8080d9c8e7855b36a85722a60552a6c01");

          // Decrypt packet salt
          if ($this->ecmID !== $this->prevEcmID)
            {
              $saltHmacKey = hash_hmac("sha1", $this->sessionKey . $this->packetIV, $hmacKey, true);
              LogDebug("SaltHmacKey  : " . hexlify($saltHmacKey), $debug);
              $this->saltAesKey = substr(hash_hmac("sha1", $hmacData1, $saltHmacKey, true), 0, 16);
              LogDebug("SaltAesKey   : " . hexlify($this->saltAesKey), $debug);
              $this->prevEcmID = $this->ecmID;
            }
          mcrypt_generic_init($this->aes_cbc, $this->saltAesKey, $this->packetIV);
          LogDebug("EncryptedSalt: " . hexlify($this->packetSalt), $debug);
          $decryptedSalt = mdecrypt_generic($this->aes_cbc, $this->packetSalt);
          LogDebug("DecryptedSalt: " . hexlify($decryptedSalt), $debug);
          mcrypt_generic_deinit($this->aes_cbc);
          $this->decryptBytes = ReadInt32($decryptedSalt, 0);
          LogDebug("DecryptBytes : " . $this->decryptBytes, $debug);
          $decryptedSalt = substr($decryptedSalt, 4, 16);
          LogDebug("DecryptedSalt: " . hexlify($decryptedSalt), $debug);

          // Generate final packet decryption key
          $finalHmacKey = hash_hmac("sha1", $decryptedSalt, $hmacKey, true);
          LogDebug("FinalHmacKey : " . hexlify($finalHmacKey), $debug);
          $this->packetKey = substr(hash_hmac("sha1", $hmacData2, $finalHmacKey, true), 0, 16);
          LogDebug("PacketKey    : " . hexlify($this->packetKey), $debug);
        }

      function Decrypt($data, $pos, $opt = array())
        {
          /** @var cURL $cc */
          $auth    = "";
          $baseUrl = "";
          $cc      = null;
          extract($opt, EXTR_IF_EXISTS);
          $debug = $this->debug;
          if ($this->decryptorTest)
              $debug = false;
          LogDebug("\n----- Akamai Decryption Start -----", $debug);

          // Parse packet header
          $byte             = ReadByte($data, $pos++);
          $this->ecmVersion = $byte >> 4;
          if ($this->ecmVersion != 11)
              $this->ecmVersion = $byte;
          $this->ecmID        = ReadInt32($data, $pos);
          $this->ecmTimestamp = ReadInt32($data, $pos + 4);
          $this->kdfVersion   = ReadInt16($data, $pos + 8);
          $pos += 10;
          $this->dccAccReserved = ReadByte($data, $pos++);
          LogDebug("ECM Version  : " . $this->ecmVersion . ", ECM ID: " . $this->ecmID . ", ECM Timestamp: " . $this->ecmTimestamp . ", KDF Version: " . $this->kdfVersion . ", DccAccReserved: " . $this->dccAccReserved, $debug);
          $byte = ReadByte($data, $pos++);
          $iv   = (($byte & 2) > 0) ? true : false;
          $key  = (($byte & 4) > 0) ? true : false;
          if ($iv)
            {
              $this->packetIV = substr($data, $pos, 16);
              $pos += 16;
              LogDebug("PacketIV     : " . hexlify($this->packetIV), $debug);
            }
          if ($key)
            {
              $this->sessionKeyUrl = ReadString($data, $pos);
              LogDebug("SessionKeyUrl: " . $this->sessionKeyUrl, $debug);
              $keyUrl          = substr($this->sessionKeyUrl, strrpos($this->sessionKeyUrl, '/'));
              $this->sessionID = "_" . substr($keyUrl, strlen("/key_"));
              $keyUrl          = JoinUrl($baseUrl, $keyUrl) . $auth;

              // Download key file if required
              if ($this->sessionKeyUrl !== $this->lastKeyUrl)
                {
                  if ($baseUrl == "")
                      LogDebug("Unable to download session key without manifest url. you must specify it manually using 'adkey' switch.", $debug);
                  else
                    {
                      if ($cc->get($keyUrl) != 200)
                          LogError("Failed to download akamai session decryption key");
                      $this->sessionKey = $cc->response;
                      LogInfo("SessionKey: " . hexlify($this->sessionKey), $debug);
                      $this->lastKeyUrl = $this->sessionKeyUrl;
                    }
                }
            }
          LogDebug("SessionKey   : " . hexlify($this->sessionKey), $debug);
          $reserved         = ReadByte($data, $pos++);
          $this->packetSalt = substr($data, $pos, 32);
          $pos += 32;
          $reservedBlock1 = substr($data, $pos, 20);
          $reservedBlock2 = substr($data, $pos + 20, 20);
          $pos += 40;
          LogDebug("ReservedByte : " . $reserved . ", ReservedBlock1: " . hexlify($reservedBlock1) . ", ReservedBlock2: " . hexlify($reservedBlock2), $debug);

          // Generate packet decryption key
          if ($this->sessionKey == "")
              LogError("Fragments can't be decrypted properly without corresponding session key.", 2);
          $this->KDF();

          // Decrypt packet data
          $encryptedData = substr($data, $pos);
          LogDebug("EncryptedData: " . hexlify(substr($encryptedData, 0, 64)), $debug);
          $lastBlockData = substr($encryptedData, $this->decryptBytes);
          $encryptedData = substr($encryptedData, 0, $this->decryptBytes);
          $decryptedData = "";
          if ($this->decryptBytes > 0)
            {
              mcrypt_generic_init($this->aes_cbc, $this->packetKey, $this->packetIV);
              $decryptedData = mdecrypt_generic($this->aes_cbc, $encryptedData);
              mcrypt_generic_deinit($this->aes_cbc);
            }
          $decryptedData .= $lastBlockData;
          LogDebug("DecryptedData: " . hexlify(substr($decryptedData, 0, 64)), $debug);

          LogDebug("----- Akamai Decryption End -----\n", $debug);
          return $decryptedData;
        }
    }

  class F4F
    {
      var $audio, $auth, $baseFilename, $baseTS, $baseUrl, $bootstrapUrl, $debug, $decoderTest, $duration, $fileCount, $filesize, $fixWindow;
      var $format, $live, $media, $metadata, $outDir, $outFile, $parallel, $play, $processed, $quality, $rename, $sessionID, $srt, $video;
      var $prevTagSize, $tagHeaderLen;
      var $segTable, $fragTable, $frags, $fragCount, $lastFrag, $fragUrl, $discontinuity;
      var $negTS, $prevAudioTS, $prevVideoTS, $pAudioTagLen, $pVideoTagLen, $pAudioTagPos, $pVideoTagPos;
      var $prevAVC_Header, $prevAAC_Header, $AVC_HeaderWritten, $AAC_HeaderWritten;

      function __construct()
        {
          $this->auth          = "";
          $this->baseFilename  = "";
          $this->baseUrl       = "";
          $this->bootstrapUrl  = "";
          $this->debug         = false;
          $this->decoderTest   = false;
          $this->fileCount     = 1;
          $this->fixWindow     = 1000;
          $this->format        = "";
          $this->live          = false;
          $this->metadata      = true;
          $this->outDir        = "";
          $this->outFile       = "";
          $this->parallel      = 8;
          $this->play          = false;
          $this->processed     = false;
          $this->quality       = "high";
          $this->rename        = false;
          $this->sessionID     = "";
          $this->segTable      = array();
          $this->fragTable     = array();
          $this->segStart      = false;
          $this->fragStart     = false;
          $this->frags         = array();
          $this->fragCount     = 0;
          $this->lastFrag      = 0;
          $this->discontinuity = "";
          $this->InitDecoder();
        }

      function InitDecoder()
        {
          $this->audio             = false;
          $this->duration          = 0;
          $this->filesize          = 0;
          $this->video             = false;
          $this->prevTagSize       = 4;
          $this->tagHeaderLen      = 11;
          $this->baseTS            = INVALID_TIMESTAMP;
          $this->negTS             = INVALID_TIMESTAMP;
          $this->prevAudioTS       = INVALID_TIMESTAMP;
          $this->prevVideoTS       = INVALID_TIMESTAMP;
          $this->pAudioTagLen      = 0;
          $this->pVideoTagLen      = 0;
          $this->pAudioTagPos      = 0;
          $this->pVideoTagPos      = 0;
          $this->prevAVC_Header    = false;
          $this->prevAAC_Header    = false;
          $this->AVC_HeaderWritten = false;
          $this->AAC_HeaderWritten = false;
        }

      function GetManifest(cURL $cc, $manifest)
        {
          $status = $cc->get($manifest);
          if ($status == 403)
              LogError("Access Denied! Unable to download the manifest.");
          else if ($status != 200)
              LogError("Unable to download the manifest");
          $xml = simplexml_load_string(trim($cc->response));
          if (!$xml)
              LogError("Failed to load xml");
          $namespace = $xml->getDocNamespaces();
          $namespace = $namespace[''];
          $xml->registerXPathNamespace("ns", $namespace);
          return $xml;
        }

      function ParseManifest(cURL $cc, $parentManifest)
        {
          /** @var SimpleXMLElement $xml */
          LogInfo("Processing manifest info....");
          $xml = $this->GetManifest($cc, $parentManifest);

          // Extract baseUrl from manifest url
          $baseUrl = $xml->xpath("/ns:manifest/ns:baseURL");
          if (isset($baseUrl[0]))
              $baseUrl = GetString($baseUrl[0]);
          else
            {
              $baseUrl = $parentManifest;
              if (strpos($baseUrl, '?') !== false)
                  $baseUrl = substr($baseUrl, 0, strpos($baseUrl, '?'));
              $baseUrl = substr($baseUrl, 0, strrpos($baseUrl, '/'));
            }

          $childManifests = array();
          $url            = $xml->xpath("/ns:manifest/ns:media[@*]");
          if (isset($url[0]['href']))
            {
              $count = 1;
              foreach ($url as $childManifest)
                {
                  if (isset($childManifest['bitrate']))
                      $bitrate = floor(GetString($childManifest['bitrate']));
                  else
                      $bitrate = $count++;
                  $entry =& $childManifests[$bitrate];
                  $entry['bitrate'] = $bitrate;
                  $entry['url']     = AbsoluteUrl($baseUrl, GetString($childManifest['href']));
                  $entry['xml']     = $this->GetManifest($cc, $entry['url']);
                }
              unset($entry, $childManifest);
            }
          else
            {
              $childManifests[0]['bitrate'] = 0;
              $childManifests[0]['url']     = $parentManifest;
              $childManifests[0]['xml']     = $xml;
            }

          $count = 1;
          foreach ($childManifests as $childManifest)
            {
              $xml = $childManifest['xml'];

              // Extract baseUrl from manifest url
              $baseUrl = $xml->xpath("/ns:manifest/ns:baseURL");
              if (isset($baseUrl[0]))
                  $baseUrl = GetString($baseUrl[0]);
              else
                {
                  $baseUrl = $childManifest['url'];
                  if (strpos($baseUrl, '?') !== false)
                      $baseUrl = substr($baseUrl, 0, strpos($baseUrl, '?'));
                  $baseUrl = substr($baseUrl, 0, strrpos($baseUrl, '/'));
                }

              $streams = $xml->xpath("/ns:manifest/ns:media");
              foreach ($streams as $stream)
                {
                  $array = array();
                  foreach ($stream->attributes() as $k => $v)
                      $array[strtolower($k)] = GetString($v);
                  $array['metadata'] = GetString($stream->{'metadata'});
                  $stream            = $array;

                  if (isset($stream['bitrate']))
                      $bitrate = floor($stream['bitrate']);
                  else if ($childManifest['bitrate'] > 0)
                      $bitrate = $childManifest['bitrate'];
                  else
                      $bitrate = $count++;
                  while (isset($this->media[$bitrate]))
                      $bitrate++;
                  $streamId = isset($stream[strtolower('streamId')]) ? $stream[strtolower('streamId')] : "";
                  $mediaEntry =& $this->media[$bitrate];

                  $mediaEntry['baseUrl'] = $baseUrl;
                  $mediaEntry['url']     = $stream['url'];
                  if (isRtmpUrl($mediaEntry['baseUrl']) or isRtmpUrl($mediaEntry['url']))
                      LogError("Provided manifest is not a valid HDS manifest");

                  // Use embedded auth information when available
                  $idx = strpos($mediaEntry['url'], '?');
                  if ($idx !== false)
                    {
                      $mediaEntry['queryString'] = substr($mediaEntry['url'], $idx);
                      $mediaEntry['url']         = substr($mediaEntry['url'], 0, $idx);
                      if (strlen($this->auth) != 0 and strcmp($this->auth, $mediaEntry['queryString']) != 0)
                          LogDebug("Manifest overrides 'auth': " . $mediaEntry['queryString']);
                    }
                  else
                      $mediaEntry['queryString'] = $this->auth;

                  if (isset($stream[strtolower('bootstrapInfoId')]))
                      $bootstrap = $xml->xpath("/ns:manifest/ns:bootstrapInfo[@id='" . $stream[strtolower('bootstrapInfoId')] . "']");
                  else
                      $bootstrap = $xml->xpath("/ns:manifest/ns:bootstrapInfo");
                  if (isset($bootstrap[0]['url']))
                    {
                      $mediaEntry['bootstrapUrl'] = AbsoluteUrl($mediaEntry['baseUrl'], GetString($bootstrap[0]['url']));
                      if (strpos($mediaEntry['bootstrapUrl'], '?') === false)
                          $mediaEntry['bootstrapUrl'] .= $this->auth;
                    }
                  else
                      $mediaEntry['bootstrap'] = base64_decode(GetString($bootstrap[0]));
                  if (isset($stream['metadata']))
                      $mediaEntry['metadata'] = base64_decode($stream['metadata']);
                  else
                      $mediaEntry['metadata'] = "";
                }
              unset($mediaEntry, $childManifest);
            }

          // Available qualities
          $bitrates = array();
          if (!count($this->media))
              LogError("No media entry found");
          krsort($this->media, SORT_NUMERIC);
          LogDebug("Manifest Entries:\n");
          LogDebug(sprintf(" %-8s%s", "Bitrate", "URL"));
          for ($i = 0; $i < count($this->media); $i++)
            {
              $key        = KeyName($this->media, $i);
              $bitrates[] = $key;
              LogDebug(sprintf(" %-8d%s", $key, $this->media[$key]['url']));
            }
          LogDebug("");
          LogInfo("Quality Selection:\n Available: " . implode(' ', $bitrates));

          // Quality selection
          $key = $this->quality;
          if (is_numeric($key) and isset($this->media[$key]))
              $this->media = $this->media[$key];
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
          LogInfo(" Selected : " . $key);

          // Parse initial bootstrap info
          $this->baseUrl = $this->media['baseUrl'];
          if (isset($this->media['bootstrapUrl']))
            {
              $this->bootstrapUrl = $this->media['bootstrapUrl'];
              $this->UpdateBootstrapInfo($cc, $this->bootstrapUrl);
            }
          else
            {
              $bootstrapInfo = $this->media['bootstrap'];
              ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
              if ($boxType == "abst")
                  $this->ParseBootstrapBox($bootstrapInfo, $pos);
              else
                  LogError("Failed to parse bootstrap info");
            }
        }

      function UpdateBootstrapInfo(cURL $cc, $bootstrapUrl)
        {
          $fragNum = $this->fragCount;
          $retries = 0;

          // Backup original headers and add no-cache directive for fresh bootstrap info
          $headers       = $cc->headers;
          $cc->headers[] = "Cache-Control: no-cache";
          $cc->headers[] = "Pragma: no-cache";

          while (($fragNum == $this->fragCount) and ($retries < 30))
            {
              $bootstrapPos = 0;
              LogDebug("Updating bootstrap info, Available fragments: " . $this->fragCount);
              $status = $cc->get($bootstrapUrl);
              if ($status != 200)
                  LogError("Failed to refresh bootstrap info, Status: " . $status);
              $bootstrapInfo = $cc->response;
              ReadBoxHeader($bootstrapInfo, $bootstrapPos, $boxType, $boxSize);
              if ($boxType == "abst")
                  $this->ParseBootstrapBox($bootstrapInfo, $bootstrapPos);
              else
                  LogError("Failed to parse bootstrap info");
              LogDebug("Update complete, Available fragments: " . $this->fragCount);
              if ($fragNum == $this->fragCount)
                {
                  LogInfo("Updating bootstrap info, Retries: " . ++$retries, true);
                  usleep(4000000);
                }
            }

          // Restore original headers
          $cc->headers = $headers;
        }

      function ParseBootstrapBox($bootstrapInfo, $pos)
        {
          $version          = ReadByte($bootstrapInfo, $pos);
          $flags            = ReadInt24($bootstrapInfo, $pos + 1);
          $bootstrapVersion = ReadInt32($bootstrapInfo, $pos + 4);
          $byte             = ReadByte($bootstrapInfo, $pos + 8);
          $profile          = ($byte & 0xC0) >> 6;
          if (($byte & 0x20) >> 5)
            {
              $this->live     = true;
              $this->metadata = false;
            }
          $update = ($byte & 0x10) >> 4;
          if (!$update)
            {
              $this->segTable  = array();
              $this->fragTable = array();
            }
          $timescale           = ReadInt32($bootstrapInfo, $pos + 9);
          $currentMediaTime    = ReadInt64($bootstrapInfo, $pos + 13);
          $smpteTimeCodeOffset = ReadInt64($bootstrapInfo, $pos + 21);
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
          $segTable         = array();
          LogDebug(sprintf("%s:", "Segment Tables"));
          for ($i = 0; $i < $segRunTableCount; $i++)
            {
              LogDebug(sprintf("\nTable %d:", $i + 1));
              ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
              if ($boxType == "asrt")
                  $segTable[$i] = $this->ParseAsrtBox($bootstrapInfo, $pos);
              $pos += $boxSize;
            }
          $fragRunTableCount = ReadByte($bootstrapInfo, $pos++);
          $fragTable         = array();
          LogDebug(sprintf("%s:", "Fragment Tables"));
          for ($i = 0; $i < $fragRunTableCount; $i++)
            {
              LogDebug(sprintf("\nTable %d:", $i + 1));
              ReadBoxHeader($bootstrapInfo, $pos, $boxType, $boxSize);
              if ($boxType == "afrt")
                  $fragTable[$i] = $this->ParseAfrtBox($bootstrapInfo, $pos);
              $pos += $boxSize;
            }
          $this->segTable  = array_replace($this->segTable, $segTable[0]);
          $this->fragTable = array_replace($this->fragTable, $fragTable[0]);
          $this->ParseSegAndFragTable();
        }

      function ParseAsrtBox($asrt, $pos)
        {
          $segTable          = array();
          $version           = ReadByte($asrt, $pos);
          $flags             = ReadInt24($asrt, $pos + 1);
          $qualityEntryCount = ReadByte($asrt, $pos + 4);
          $pos += 5;
          for ($i = 0; $i < $qualityEntryCount; $i++)
              $qualitySegmentUrlModifiers[$i] = ReadString($asrt, $pos);
          $segCount = ReadInt32($asrt, $pos);
          $pos += 4;
          LogDebug(sprintf(" %-8s%-10s", "Number", "Fragments"));
          for ($i = 0; $i < $segCount; $i++)
            {
              $firstSegment = ReadInt32($asrt, $pos);
              $segEntry =& $segTable[$firstSegment];
              $segEntry['firstSegment']        = $firstSegment;
              $segEntry['fragmentsPerSegment'] = ReadInt32($asrt, $pos + 4);
              if ($segEntry['fragmentsPerSegment'] & 0x80000000)
                  $segEntry['fragmentsPerSegment'] = 0;
              $pos += 8;
            }
          unset($segEntry);
          foreach ($segTable as $segEntry)
              LogDebug(sprintf(" %-8s%-10s", $segEntry['firstSegment'], $segEntry['fragmentsPerSegment']));
          LogDebug("");
          return $segTable;
        }

      function ParseAfrtBox($afrt, $pos)
        {
          $fragTable         = array();
          $version           = ReadByte($afrt, $pos);
          $flags             = ReadInt24($afrt, $pos + 1);
          $timescale         = ReadInt32($afrt, $pos + 4);
          $qualityEntryCount = ReadByte($afrt, $pos + 8);
          $pos += 9;
          for ($i = 0; $i < $qualityEntryCount; $i++)
              $qualitySegmentUrlModifiers[$i] = ReadString($afrt, $pos);
          $fragEntries = ReadInt32($afrt, $pos);
          $pos += 4;
          LogDebug(sprintf(" %-12s%-16s%-16s%-16s", "Number", "Timestamp", "Duration", "Discontinuity"));
          for ($i = 0; $i < $fragEntries; $i++)
            {
              $firstFragment = ReadInt32($afrt, $pos);
              $fragEntry =& $fragTable[$firstFragment];
              $fragEntry['firstFragment']          = $firstFragment;
              $fragEntry['firstFragmentTimestamp'] = ReadInt64($afrt, $pos + 4);
              $fragEntry['fragmentDuration']       = ReadInt32($afrt, $pos + 12);
              $fragEntry['discontinuityIndicator'] = "";
              $pos += 16;
              if ($fragEntry['fragmentDuration'] == 0)
                  $fragEntry['discontinuityIndicator'] = ReadByte($afrt, $pos++);
            }
          unset($fragEntry);
          foreach ($fragTable as $fragEntry)
              LogDebug(sprintf(" %-12s%-16s%-16s%-16s", $fragEntry['firstFragment'], $fragEntry['firstFragmentTimestamp'], $fragEntry['fragmentDuration'], $fragEntry['discontinuityIndicator']));
          LogDebug("");
          return $fragTable;
        }

      function ParseSegAndFragTable()
        {
          $firstSegment  = reset($this->segTable);
          $lastSegment   = end($this->segTable);
          $firstFragment = reset($this->fragTable);
          $lastFragment  = end($this->fragTable);

          // Check if live stream is still live
          if (($lastFragment['fragmentDuration'] == 0) and ($lastFragment['discontinuityIndicator'] == 0))
            {
              $this->live = false;
              array_pop($this->fragTable);
              $lastFragment = end($this->fragTable);
            }

          // Count total fragments by adding all entries in compactly coded segment table
          $invalidFragCount = false;
          $prev             = reset($this->segTable);
          $this->fragCount  = $prev['fragmentsPerSegment'];
          while ($current = next($this->segTable))
            {
              $this->fragCount += ($current['firstSegment'] - $prev['firstSegment'] - 1) * $prev['fragmentsPerSegment'];
              $this->fragCount += $current['fragmentsPerSegment'];
              $prev = $current;
            }
          if (!($this->fragCount & 0x80000000))
              $this->fragCount += $firstFragment['firstFragment'] - 1;
          if ($this->fragCount & 0x80000000)
            {
              $this->fragCount  = 0;
              $invalidFragCount = true;
            }
          if ($this->fragCount < $lastFragment['firstFragment'])
              $this->fragCount = $lastFragment['firstFragment'];

          // Determine starting segment and fragment
          if ($this->segStart === false)
            {
              if ($this->live)
                  $this->segStart = $lastSegment['firstSegment'];
              else
                  $this->segStart = $firstSegment['firstSegment'];
              if ($this->segStart < 1)
                  $this->segStart = 1;
            }
          if ($this->fragStart === false)
            {
              if ($this->live and !$invalidFragCount)
                  $this->fragStart = $this->fragCount - 2;
              else
                  $this->fragStart = $firstFragment['firstFragment'] - 1;
              if ($this->fragStart < 0)
                  $this->fragStart = 0;
            }
        }

      function GetSegmentFromFragment($fragNum)
        {
          $firstSegment  = reset($this->segTable);
          $lastSegment   = end($this->segTable);
          $firstFragment = reset($this->fragTable);
          $lastFragment  = end($this->fragTable);

          if (count($this->segTable) == 1)
              return $firstSegment['firstSegment'];
          else
            {
              $prev  = $firstSegment['firstSegment'];
              $start = $firstFragment['firstFragment'];
              for ($i = $firstSegment['firstSegment']; $i <= $lastSegment['firstSegment']; $i++)
                {
                  if (isset($this->segTable[$i]))
                      $seg = $this->segTable[$i];
                  else
                      $seg = $prev;
                  $end = $start + $seg['fragmentsPerSegment'];
                  if (($fragNum >= $start) and ($fragNum < $end))
                      return $i;
                  $prev  = $seg;
                  $start = $end;
                }
            }
          return $lastSegment['firstSegment'];
        }

      function DownloadFragments($manifest, $opt = array())
        {
          /** @var cURL $cc */
          $cc    = null;
          $start = 0;
          extract($opt, EXTR_IF_EXISTS);

          $this->ParseManifest($cc, $manifest);
          $segNum  = $this->segStart;
          $fragNum = $this->fragStart;
          if ($start)
            {
              $segNum          = $this->GetSegmentFromFragment($start);
              $fragNum         = $start - 1;
              $this->segStart  = $segNum;
              $this->fragStart = $fragNum;
            }
          $this->lastFrag = $fragNum;
          $firstFragment  = reset($this->fragTable);
          LogInfo(sprintf("Fragments Total: %s, First: %s, Start: %s, Parallel: %s", $this->fragCount, $firstFragment['firstFragment'], $fragNum + 1, $this->parallel));

          // Extract baseFilename
          $this->baseFilename = $this->media['url'];
          if (substr($this->baseFilename, -1) == '/')
              $this->baseFilename = substr($this->baseFilename, 0, -1);
          $this->baseFilename = RemoveExtension($this->baseFilename);
          $lastSlash          = strrpos($this->baseFilename, '/');
          if ($lastSlash !== false)
              $this->baseFilename = substr($this->baseFilename, $lastSlash + 1);
          if (strpos($manifest, '?'))
              $manifestHash = md5(substr($manifest, 0, strpos($manifest, '?')));
          else
              $manifestHash = md5($manifest);
          if (strlen($this->baseFilename) > 32)
              $this->baseFilename = md5($this->baseFilename);
          $this->baseFilename = $manifestHash . "_" . $this->baseFilename . "_Seg" . $segNum . "-Frag";

          if ($fragNum >= $this->fragCount)
              LogError("No fragment available for downloading");

          $this->fragUrl = AbsoluteUrl($this->baseUrl, $this->media['url']);
          LogDebug("Base Fragment Url:\n" . $this->fragUrl . "\n");
          LogDebug("Downloading Fragments:\n");

          $firstFrag = true;
          $status    = false;
          while (($fragNum < $this->fragCount) or $cc->active)
            {
              while ((count($cc->ch) < $this->parallel) and ($fragNum < $this->fragCount))
                {
                  // Download first fragment to determine initial parameters
                  if ($firstFrag and (count($cc->ch) > 0))
                      break;

                  $frag       = array();
                  $fragNum    = $fragNum + 1;
                  $frag['id'] = $fragNum;
                  LogInfo("Downloading " . $fragNum . "/" . $this->fragCount . " fragments", true);
                  if (in_array_field($fragNum, "firstFragment", $this->fragTable, true))
                      $this->discontinuity = value_in_array_field($fragNum, "firstFragment", "discontinuityIndicator", $this->fragTable, true);
                  else
                    {
                      $closest = reset($this->fragTable);
                      $closest = $closest['firstFragment'];
                      while ($current = next($this->fragTable))
                        {
                          if ($current['firstFragment'] < $fragNum)
                              $closest = $current['firstFragment'];
                          else
                              break;
                        }
                      $this->discontinuity = value_in_array_field($closest, "firstFragment", "discontinuityIndicator", $this->fragTable, true);
                    }
                  if ($this->discontinuity !== "")
                    {
                      LogDebug("Skipping fragment " . $fragNum . " due to discontinuity, Type: " . $this->discontinuity);
                      $frag['response'] = false;
                      $this->rename     = true;
                    }
                  else if (file_exists($this->baseFilename . $fragNum))
                    {
                      LogDebug("Fragment " . $fragNum . " is already downloaded");
                      $frag['response'] = file_get_contents($this->baseFilename . $fragNum);
                    }
                  if (isset($frag['response']))
                    {
                      $status = $this->WriteFragment($frag, $opt);
                      if ($status === STOP_PROCESSING)
                          break 2;
                      else
                          continue;
                    }

                  LogDebug("Adding fragment " . $fragNum . " to download queue");
                  $segNum = $this->GetSegmentFromFragment($fragNum);
                  $cc->addDownload($this->fragUrl . "Seg" . $segNum . "-Frag" . $fragNum . $this->sessionID . $this->media['queryString'], $fragNum);
                }

              $downloads = $cc->checkDownloads();
              if ($downloads !== false)
                {
                  for ($i = 0; $i < count($downloads); $i++)
                    {
                      $frag       = array();
                      $download   = $downloads[$i];
                      $frag['id'] = $download['id'];
                      if ($download['status'] == 200)
                        {
                          if ($this->VerifyFragment($download['response']))
                            {
                              LogDebug("Fragment " . $this->baseFilename . $download['id'] . " successfully downloaded");
                              if (!($this->live or $this->play))
                                  file_put_contents($this->baseFilename . $download['id'], $download['response']);
                              $frag['response'] = $download['response'];
                              $firstFrag        = false;
                            }
                          else
                            {
                              LogDebug("Fragment " . $download['id'] . " failed to verify");
                              LogDebug("Adding fragment " . $download['id'] . " to download queue");
                              $cc->addDownload($download['url'], $download['id']);
                            }
                        }
                      else if ($download['status'] === false)
                        {
                          LogDebug("Fragment " . $download['id'] . " failed to download");
                          LogDebug("Adding fragment " . $download['id'] . " to download queue");
                          $cc->addDownload($download['url'], $download['id']);
                        }
                      else if ($download['status'] == 403)
                          LogError("Access Denied! Unable to download fragments.");
                      else if ($download['status'] == 503)
                        {
                          LogDebug("Fragment " . $download['id'] . " seems temporary unavailable");
                          LogDebug("Adding fragment " . $download['id'] . " to download queue");
                          $cc->addDownload($download['url'], $download['id']);
                        }
                      else
                        {
                          LogDebug("Fragment " . $download['id'] . " doesn't exist, Status: " . $download['status']);
                          $frag['response'] = false;
                          $this->rename     = true;

                          /* Resync with latest available fragment when we are left behind due to slow *
                           * connection and short live window on streaming server. make sure to reset  *
                           * the last written fragment.                                                */
                          if ($this->live and ($fragNum >= $this->fragCount) and ($i + 1 == count($downloads)) and !$cc->active)
                            {
                              LogDebug("Trying to resync with latest available fragment");
                              $status = $this->WriteFragment($frag, $opt);
                              if ($status === STOP_PROCESSING)
                                  break 2;
                              unset($frag['response']);
                              $this->UpdateBootstrapInfo($cc, $this->bootstrapUrl);
                              $fragNum        = $this->fragCount - 1;
                              $this->lastFrag = $fragNum;
                            }
                        }
                      if (isset($frag['response']))
                        {
                          $status = $this->WriteFragment($frag, $opt);
                          if ($status === STOP_PROCESSING)
                              break 2;
                        }
                    }
                  unset($downloads, $download);
                }
              if ($this->live and ($fragNum >= $this->fragCount) and !$cc->active)
                  $this->UpdateBootstrapInfo($cc, $this->bootstrapUrl);
            }

          if ($status === true)
              LogInfo("");
          LogDebug("\nAll fragments downloaded successfully\n");
          $cc->stopDownloads();
          $this->processed = true;
        }

      function VerifyFragment(&$frag)
        {
          $fragPos = 0;
          $fragLen = strlen($frag);

          /* Some moronic servers add wrong boxSize in header causing fragment verification *
           * to fail so we have to fix the boxSize before processing the fragment.          */
          while ($fragPos < $fragLen)
            {
              ReadBoxHeader($frag, $fragPos, $boxType, $boxSize);
              if ($boxType == "mdat")
                {
                  $len = strlen(substr($frag, $fragPos, $boxSize));
                  if ($boxSize and ($len == $boxSize))
                      return true;
                  else
                    {
                      $boxSize = $fragLen - $fragPos;
                      WriteBoxSize($frag, $fragPos, $boxType, $boxSize);
                      return true;
                    }
                }
              $fragPos += $boxSize;
            }
          return false;
        }

      function DecodeFragment($frag, $fragNum, $opt = array())
        {
          $ad       = null;
          $flvFile  = null;
          $flvWrite = true;
          extract($opt, EXTR_IF_EXISTS);
          $debug = $this->debug;
          if ($this->decoderTest)
              $debug = false;

          $flvData  = "";
          $flvTag   = "";
          $fragPos  = 0;
          $packetTS = 0;
          $fragLen  = strlen($frag);

          if (!$this->VerifyFragment($frag))
            {
              LogInfo("Skipping fragment number " . $fragNum);
              return false;
            }

          while ($fragPos < $fragLen)
            {
              ReadBoxHeader($frag, $fragPos, $boxType, $boxSize);
              if ($boxType == "mdat")
                {
                  $fragLen = $fragPos + $boxSize;
                  break;
                }
              $fragPos += $boxSize;
            }

          /**
           * Initialize Akamai decryptor
           * @var AkamaiDecryptor $ad
           */
          $ad->debug         = $this->debug;
          $ad->decryptorTest = $this->decoderTest;
          $ad->InitDecryptor();

          LogDebug(sprintf("\nFragment %d:\n" . $this->format . "%-16s", $fragNum, "Type", "CurrentTS", "PreviousTS", "Size", "Position"), $debug);
          while ($fragPos < $fragLen)
            {
              $packetType = ReadByte($frag, $fragPos);
              $packetSize = ReadInt24($frag, $fragPos + 1);
              $packetTS   = ReadInt24($frag, $fragPos + 4);
              $packetTS   = $packetTS | (ReadByte($frag, $fragPos + 7) << 24);
              if ($packetTS & 0x80000000)
                  $packetTS &= 0x7FFFFFFF;
              $totalTagLen = $this->tagHeaderLen + $packetSize + $this->prevTagSize;
              $tagHeader   = substr($frag, $fragPos, $this->tagHeaderLen);
              $tagData     = substr($frag, $fragPos + $this->tagHeaderLen, $packetSize);

              // Remove Akamai encryption
              if (($packetType == AKAMAI_ENC_AUDIO) or ($packetType == AKAMAI_ENC_VIDEO))
                {
                  $opt['auth']    = $this->media['queryString'];
                  $opt['baseUrl'] = $this->baseUrl;
                  $tagData        = $ad->Decrypt($tagData, 0, $opt);
                  $packetType     = ($packetType == AKAMAI_ENC_AUDIO ? AUDIO : VIDEO);
                  $packetSize     = strlen($tagData);
                  WriteByte($tagHeader, 0, $packetType);
                  WriteInt24($tagHeader, 1, $packetSize);
                  $this->sessionID = $ad->sessionID;
                }

              // Try to fix the odd timestamps and make them zero based
              $currentTS = $packetTS;
              $lastTS    = $this->prevVideoTS >= $this->prevAudioTS ? $this->prevVideoTS : $this->prevAudioTS;
              $fixedTS   = $lastTS + FRAMEFIX_STEP;
              if (($this->baseTS == INVALID_TIMESTAMP) and (($packetType == AUDIO) or ($packetType == VIDEO)))
                  $this->baseTS = $packetTS;
              if (($this->baseTS > 1000) and ($packetTS >= $this->baseTS))
                  $packetTS -= $this->baseTS;
              if ($lastTS != INVALID_TIMESTAMP)
                {
                  $timeShift = $packetTS - $lastTS;
                  if ($timeShift > $this->fixWindow)
                    {
                      LogDebug("Timestamp gap detected: PacketTS=" . $packetTS . " LastTS=" . $lastTS . " Timeshift=" . $timeShift, $debug);
                      if ($this->baseTS < $packetTS)
                          $this->baseTS += $timeShift - FRAMEFIX_STEP;
                      else
                          $this->baseTS = $timeShift - FRAMEFIX_STEP;
                      $packetTS = $fixedTS;
                    }
                  else
                    {
                      $lastTS = $packetType == VIDEO ? $this->prevVideoTS : $this->prevAudioTS;
                      if ($packetTS < ($lastTS - $this->fixWindow))
                        {
                          if (($this->negTS != INVALID_TIMESTAMP) and (($packetTS + $this->negTS) < ($lastTS - $this->fixWindow)))
                              $this->negTS = INVALID_TIMESTAMP;
                          if ($this->negTS == INVALID_TIMESTAMP)
                            {
                              $this->negTS = $fixedTS - $packetTS;
                              LogDebug("Negative timestamp detected: PacketTS=" . $packetTS . " LastTS=" . $lastTS . " NegativeTS=" . $this->negTS, $debug);
                              $packetTS = $fixedTS;
                            }
                          else
                            {
                              if (($packetTS + $this->negTS) <= ($lastTS + $this->fixWindow))
                                  $packetTS += $this->negTS;
                              else
                                {
                                  $this->negTS = $fixedTS - $packetTS;
                                  LogDebug("Negative timestamp override: PacketTS=" . $packetTS . " LastTS=" . $lastTS . " NegativeTS=" . $this->negTS, $debug);
                                  $packetTS = $fixedTS;
                                }
                            }
                        }
                    }
                }
              if ($packetTS != $currentTS)
                  WriteFlvTimestamp($tagHeader, 0, $packetTS);

              switch ($packetType)
              {
                  case AUDIO:
                      if ($packetTS > $this->prevAudioTS - $this->fixWindow)
                        {
                          $FrameInfo = ReadByte($tagData, 0);
                          $CodecID   = ($FrameInfo & 0xF0) >> 4;
                          if ($CodecID == CODEC_ID_AAC)
                            {
                              $AAC_PacketType = ReadByte($tagData, 1);
                              if ($AAC_PacketType == AAC_SEQUENCE_HEADER)
                                {
                                  if ($this->AAC_HeaderWritten)
                                    {
                                      LogDebug(sprintf("%s\n" . $this->format, "Skipping AAC sequence header", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                                      break;
                                    }
                                  else
                                    {
                                      LogDebug("Writing AAC sequence header", $debug);
                                      $this->AAC_HeaderWritten = true;
                                    }
                                }
                              else if (!$this->AAC_HeaderWritten)
                                {
                                  LogDebug(sprintf("%s\n" . $this->format, "Discarding audio packet received before AAC sequence header", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                                  break;
                                }
                            }
                          if ($packetSize > 0)
                            {
                              // Check for packets with non-monotonic audio timestamps and fix them
                              if (!(($CodecID == CODEC_ID_AAC) and (($AAC_PacketType == AAC_SEQUENCE_HEADER) or $this->prevAAC_Header)))
                                  if (($this->prevAudioTS != INVALID_TIMESTAMP) and ($packetTS <= $this->prevAudioTS))
                                    {
                                      LogDebug(sprintf("%s\n" . $this->format, "Fixing audio timestamp", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                                      $packetTS += (FRAMEFIX_STEP / 5) + ($this->prevAudioTS - $packetTS);
                                      WriteFlvTimestamp($tagHeader, 0, $packetTS);
                                    }
                              $flvTag    = $tagHeader . $tagData;
                              $flvTagLen = strlen($flvTag);
                              WriteInt32($flvTag, $flvTagLen, $flvTagLen);
                              $flvTagLen = strlen($flvTag);
                              if ($flvWrite and is_resource($flvFile))
                                {
                                  $this->pAudioTagPos = ftell($flvFile);
                                  $status             = fwrite($flvFile, $flvTag, $flvTagLen);
                                  if (!$status)
                                      LogError("Failed to write flv data to file");
                                  if ($debug)
                                      LogDebug(sprintf($this->format . "%-16s", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize, $this->pAudioTagPos));
                                }
                              else
                                {
                                  $flvData .= $flvTag;
                                  if ($debug)
                                      LogDebug(sprintf($this->format, "AUDIO", $packetTS, $this->prevAudioTS, $packetSize));
                                }
                              if (($CodecID == CODEC_ID_AAC) and ($AAC_PacketType == AAC_SEQUENCE_HEADER))
                                  $this->prevAAC_Header = true;
                              else
                                  $this->prevAAC_Header = false;
                              $this->prevAudioTS  = $packetTS;
                              $this->pAudioTagLen = $flvTagLen;
                            }
                          else
                              LogDebug(sprintf("%s\n" . $this->format, "Skipping small sized audio packet", "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                        }
                      else
                          LogDebug(sprintf("%s\n" . $this->format, "Skipping audio packet in fragment " . $fragNum, "AUDIO", $packetTS, $this->prevAudioTS, $packetSize), $debug);
                      if (!$this->audio)
                          $this->audio = true;
                      break;
                  case VIDEO:
                      if ($packetTS > $this->prevVideoTS - $this->fixWindow)
                        {
                          $FrameInfo = ReadByte($tagData, 0);
                          $FrameType = ($FrameInfo & 0xF0) >> 4;
                          $CodecID   = $FrameInfo & 0x0F;
                          if ($FrameType == FRAME_TYPE_INFO)
                            {
                              LogDebug(sprintf("%s\n" . $this->format, "Skipping video info frame", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                              break;
                            }
                          if ($CodecID == CODEC_ID_AVC)
                            {
                              $AVC_PacketType = ReadByte($tagData, 1);
                              if ($AVC_PacketType == AVC_SEQUENCE_HEADER)
                                {
                                  if ($this->AVC_HeaderWritten)
                                    {
                                      LogDebug(sprintf("%s\n" . $this->format, "Skipping AVC sequence header", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                                      break;
                                    }
                                  else
                                    {
                                      LogDebug("Writing AVC sequence header", $debug);
                                      $this->AVC_HeaderWritten = true;
                                    }
                                }
                              else if (!$this->AVC_HeaderWritten)
                                {
                                  LogDebug(sprintf("%s\n" . $this->format, "Discarding video packet received before AVC sequence header", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                                  break;
                                }
                            }
                          if ($packetSize > 0)
                            {
                              $pts = $packetTS;
                              if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType == AVC_NALU))
                                {
                                  $cts = ReadInt24($tagData, 2);
                                  $cts = ($cts + 0xff800000) ^ 0xff800000;
                                  $pts = $packetTS + $cts;
                                  if ($cts != 0)
                                      LogDebug("DTS: $packetTS CTS: $cts PTS: $pts", $debug);
                                }

                              // Check for packets with non-monotonic video timestamps and fix them
                              if (!(($CodecID == CODEC_ID_AVC) and (($AVC_PacketType == AVC_SEQUENCE_HEADER) or ($AVC_PacketType == AVC_SEQUENCE_END) or $this->prevAVC_Header)))
                                  if (($this->prevVideoTS != INVALID_TIMESTAMP) and ($packetTS <= $this->prevVideoTS))
                                    {
                                      LogDebug(sprintf("%s\n" . $this->format, "Fixing video timestamp", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                                      $packetTS += (FRAMEFIX_STEP / 5) + ($this->prevVideoTS - $packetTS);
                                      WriteFlvTimestamp($tagHeader, 0, $packetTS);
                                    }
                              $flvTag    = $tagHeader . $tagData;
                              $flvTagLen = strlen($flvTag);
                              WriteInt32($flvTag, $flvTagLen, $flvTagLen);
                              $flvTagLen = strlen($flvTag);
                              if ($flvWrite and is_resource($flvFile))
                                {
                                  $this->pVideoTagPos = ftell($flvFile);
                                  $status             = fwrite($flvFile, $flvTag, $flvTagLen);
                                  if (!$status)
                                      LogError("Failed to write flv data to file");
                                  if ($debug)
                                      LogDebug(sprintf($this->format . "%-16s", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize, $this->pVideoTagPos));
                                }
                              else
                                {
                                  $flvData .= $flvTag;
                                  if ($debug)
                                      LogDebug(sprintf($this->format, "VIDEO", $packetTS, $this->prevVideoTS, $packetSize));
                                }
                              if (($CodecID == CODEC_ID_AVC) and ($AVC_PacketType == AVC_SEQUENCE_HEADER))
                                  $this->prevAVC_Header = true;
                              else
                                  $this->prevAVC_Header = false;
                              $this->prevVideoTS  = $packetTS;
                              $this->pVideoTagLen = $flvTagLen;
                            }
                          else
                              LogDebug(sprintf("%s\n" . $this->format, "Skipping small sized video packet", "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                        }
                      else
                          LogDebug(sprintf("%s\n" . $this->format, "Skipping video packet in fragment " . $fragNum, "VIDEO", $packetTS, $this->prevVideoTS, $packetSize), $debug);
                      if (!$this->video)
                          $this->video = true;
                      break;
                  case SCRIPT_DATA:
                      break;
                  default:
                      if (($packetType == 40) or ($packetType == 41))
                          LogError("This stream is encrypted with FlashAccess DRM. Decryption of such streams isn't currently possible with this script.", 2);
                      else
                        {
                          LogInfo("Unknown packet type " . $packetType . " encountered! Unable to process fragment " . $fragNum);
                          break 2;
                        }
              }
              $fragPos += $totalTagLen;
            }
          $this->duration = round($packetTS / 1000, 0);
          if ($flvWrite and is_resource($flvFile))
            {
              $this->filesize = ftell($flvFile) / (1024 * 1024);
              return true;
            }
          else
              return $flvData;
        }

      function WriteFragment($download, &$opt)
        {
          $this->frags[$download['id']] = $download;
          if (!isset($opt['flvWrite']))
              $opt['flvWrite'] = true;
          if ($this->play)
              $opt['flvWrite'] = false;

          $available = count($this->frags);
          for ($i = 0; $i < $available; $i++)
            {
              if (isset($this->frags[$this->lastFrag + 1]))
                {
                  $frag = $this->frags[$this->lastFrag + 1];
                  if ($frag['response'] !== false)
                    {
                      LogDebug("Writing fragment " . $frag['id'] . " to flv file");
                      if (!isset($opt['flvFile']))
                        {
                          $this->decoderTest = true;
                          if ($this->play)
                              $outFile = STDOUT;
                          else if ($this->outFile)
                            {
                              if ($opt['filesize'])
                                  $outFile = JoinUrl($this->outDir, $this->outFile . '-' . $this->fileCount++ . ".flv");
                              else
                                  $outFile = JoinUrl($this->outDir, $this->outFile . ".flv");
                            }
                          else
                            {
                              if ($opt['filesize'])
                                  $outFile = JoinUrl($this->outDir, $this->baseFilename . '-' . $this->fileCount++ . ".flv");
                              else
                                  $outFile = JoinUrl($this->outDir, $this->baseFilename . ".flv");
                            }
                          $this->InitDecoder();
                          $this->DecodeFragment($frag['response'], $frag['id'], $opt);
                          $opt['flvFile'] = WriteFlvFile($outFile, $this->audio, $this->video);
                          if ($this->metadata)
                              WriteMetadata($this, $opt['flvFile']);

                          $this->decoderTest = false;
                          $this->InitDecoder();
                        }
                      if ($opt['flvWrite'])
                          $this->DecodeFragment($frag['response'], $frag['id'], $opt);
                      else
                        {
                          $flvData = $this->DecodeFragment($frag['response'], $frag['id'], $opt);
                          if (strlen($flvData) > 0)
                            {
                              $status = fwrite($opt['flvFile'], $flvData, strlen($flvData));
                              if (!$status)
                                  LogError("Failed to write flv data");
                            }
                        }
                      $this->lastFrag = $frag['id'];
                    }
                  else
                    {
                      $this->lastFrag += 1;
                      LogDebug("Skipping failed fragment " . $this->lastFrag);
                    }
                  unset($this->frags[$this->lastFrag]);
                }
              else
                  break;

              $recDuration = $opt['duration'] + $this->duration;
              if ($opt['tDuration'] and ($recDuration >= $opt['tDuration']))
                {
                  LogInfo("");
                  LogInfo($recDuration . " seconds of content has been recorded successfully.");
                  return STOP_PROCESSING;
                }
              if ($opt['filesize'] and ($this->filesize >= $opt['filesize']))
                {
                  $this->filesize = 0;
                  $opt['duration'] += $this->duration;
                  fclose($opt['flvFile']);
                  unset($opt['flvFile']);
                }
            }

          if (!count($this->frags))
              unset($this->frags);
          return true;
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

  function ReadDouble($str, $pos)
    {
      $double = unpack('d', strrev(substr($str, $pos, 8)));
      return $double[1];
    }

  function ReadString($str, &$pos)
    {
      $len = 0;
      while ($str[$pos + $len] != "\x00")
          $len++;
      $str = substr($str, $pos, $len);
      $pos += $len + 1;
      return $str;
    }

  function ReadBoxHeader($str, &$pos, &$boxType, &$boxSize)
    {
      if (!isset($pos))
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
      if ($boxSize <= 0)
          $boxSize = 0;
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

  function WriteBoxSize(&$str, $pos, $type, $size)
    {
      if (substr($str, $pos - 4, 4) == $type)
          WriteInt32($str, $pos - 8, $size);
      else
        {
          WriteInt32($str, $pos - 8, 0);
          WriteInt32($str, $pos - 4, $size);
        }
    }

  function WriteFlvTimestamp(&$frag, $fragPos, $packetTS)
    {
      WriteInt24($frag, $fragPos + 4, ($packetTS & 0x00FFFFFF));
      WriteByte($frag, $fragPos + 7, ($packetTS & 0xFF000000) >> 24);
    }

  function AbsoluteUrl($baseUrl, $url)
    {
      if (!isHttpUrl($url))
          $url = JoinUrl($baseUrl, $url);
      return NormalizePath($url);
    }

  function GetFragmentList($baseFilename, $fragStart, $fileExt)
    {
      $files   = array();
      $retries = 0;
      $count   = $fragStart;

      while (true)
        {
          if ($retries >= 50)
              break;
          $file = $baseFilename . ++$count;
          if (file_exists($file))
            {
              $files[$count] = $file;
              $retries       = 0;
            }
          else if (file_exists($file . $fileExt))
            {
              $files[$count] = $file . $fileExt;
              $retries       = 0;
            }
          else
              $retries++;
        }

      return $files;
    }

  function GetString($object)
    {
      return trim(strval($object));
    }

  function isHttpUrl($url)
    {
      return (strncasecmp($url, "http", 4) == 0) ? true : false;
    }

  function isRtmpUrl($url)
    {
      return (preg_match('/^rtm(p|pe|pt|pte|ps|pts|fp):\/\//i', $url)) ? true : false;
    }

  function JoinUrl($firstUrl, $secondUrl)
    {
      if ($firstUrl and $secondUrl)
        {
          if (substr($firstUrl, -1) == '/')
              $firstUrl = substr($firstUrl, 0, -1);
          if (substr($secondUrl, 0, 1) == '/')
              $secondUrl = substr($secondUrl, 1);
          return $firstUrl . '/' . $secondUrl;
        }
      else if ($firstUrl)
          return $firstUrl;
      else
          return $secondUrl;
    }

  function KeyName(array $a, $pos)
    {
      $temp = array_slice($a, $pos, 1, true);
      return key($temp);
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

  function NormalizePath($path)
    {
      $inSegs  = preg_split('/(?<!\/)\/(?!\/)/u', $path);
      $outSegs = array();

      foreach ($inSegs as $seg)
        {
          if ($seg == '' or $seg == '.')
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

  function RenameFragments($oldFiles, $baseFilename)
    {
      $newFiles = array();
      $count    = 0;

      foreach ($oldFiles as $oldFile)
        {
          $count++;
          $newFile = $baseFilename . $count;
          if (!rename($oldFile, $newFile))
              LogError("Failed to rename fragment from '" . $oldFile . "' to '" . $newFile . "'");
          $newFiles[$count] = $newFile;
        }

      return $newFiles;
    }

  function ShowHeader()
    {
      $header = "KSV Adobe HDS Downloader";
      $len    = strlen($header);
      $width  = floor((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  function WriteFlvFile($outFile, $audio = true, $video = true)
    {
      $flvHeader    = unhexlify("464c5601050000000900000000");
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

  function WriteMetadata($f4f, $flv)
    {
      if (isset($f4f->media) and $f4f->media['metadata'])
        {
          $metadataSize = strlen($f4f->media['metadata']);
          WriteByte($metadata, 0, SCRIPT_DATA);
          WriteInt24($metadata, 1, $metadataSize);
          WriteInt24($metadata, 4, 0);
          WriteInt32($metadata, 7, 0);
          $metadata = implode("", $metadata) . $f4f->media['metadata'];
          WriteByte($metadata, $f4f->tagHeaderLen + $metadataSize - 1, 0x09);
          WriteInt32($metadata, $f4f->tagHeaderLen + $metadataSize, $f4f->tagHeaderLen + $metadataSize);
          if (is_resource($flv))
            {
              fwrite($flv, $metadata, $f4f->tagHeaderLen + $metadataSize + $f4f->prevTagSize);
              return true;
            }
          else
              return $metadata;
        }
      return false;
    }

  function hexlify($str)
    {
      $str = unpack("H*", $str);
      return $str[1];
    }

  function in_array_field($needle, $needle_field, $haystack, $strict = false)
    {
      if ($strict)
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) and $item[$needle_field] === $needle)
                  return true;
        }
      else
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) and $item[$needle_field] == $needle)
                  return true;
        }
      return false;
    }

  function unhexlify($str)
    {
      return pack("H*", $str);
    }

  function value_in_array_field($needle, $needle_field, $value_field, $haystack, $strict = false)
    {
      if ($strict)
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) and $item[$needle_field] === $needle)
                  return $item[$value_field];
        }
      else
        {
          foreach ($haystack as $item)
              if (isset($item[$needle_field]) and $item[$needle_field] == $needle)
                  return $item[$value_field];
        }
      return false;
    }

  // Global code starts here
  $format       = " %-8s%-16s%-16s%-8s";
  $baseFilename = "";
  $debug        = false;
  $duration     = 0;
  $delete       = false;
  $fileCount    = 1;
  $fileExt      = ".f4f";
  $filesize     = 0;
  $fixWindow    = 1000;
  $fragCount    = 0;
  $fragStart    = 0;
  $manifest     = "";
  $maxSpeed     = 0;
  $metadata     = true;
  $outDir       = "";
  $outFile      = "";
  $play         = false;
  $quiet        = false;
  $referrer     = "";
  $rename       = false;
  $showHeader   = true;
  $start        = 0;
  $update       = false;

  // Set large enough memory limit
  ini_set("memory_limit", "1024M");

  // Initialize command line processing
  $options = array(
      0 => array(
          'help' => 'displays this help',
          'debug' => 'show debug output',
          'delete' => 'delete fragments after processing',
          'fproxy' => 'force proxy for downloading of fragments',
          'play' => 'dump stream to stdout for piping to media player',
          'rename' => 'rename fragments sequentially before processing',
          'update' => 'update the script to current git version'
      ),
      1 => array(
          'adkey' => 'akamai session decryption key',
          'auth' => 'authentication string for fragment requests',
          'duration' => 'stop recording after specified number of seconds',
          'filesize' => 'split output file in chunks of specified size (MB)',
          'fragments' => 'base filename for fragments',
          'fixwindow' => 'timestamp gap between frames to consider as timeshift',
          'manifest' => 'manifest file for downloading of fragments',
          'maxspeed' => 'maximum bandwidth consumption (KB) for fragment downloading',
          'outdir' => 'destination folder for output file',
          'outfile' => 'filename to use for output file',
          'parallel' => 'number of fragments to download simultaneously',
          'proxy' => 'proxy for downloading of manifest',
          'quality' => 'selected quality level (low|medium|high) or exact bitrate',
          'referrer' => 'Referer to use for emulation of browser requests',
          'start' => 'start from specified fragment',
          'useragent' => 'User-Agent to use for emulation of browser requests'
      )
  );
  $cli     = new CLI($options, true);

  // Check if STDOUT is available
  if ($cli->getParam('play'))
    {
      $play       = true;
      $quiet      = true;
      $showHeader = false;
    }

  // Check for required extensions
  $required_extensions = array(
      "bcmath",
      "curl",
      "mcrypt",
      "SimpleXML"
  );
  $missing_extensions  = array_diff($required_extensions, get_loaded_extensions());
  if ($missing_extensions)
    {
      $msg = "You have to install and enable the following extension(s) to continue: '" . implode("', '", $missing_extensions) . "'";
      LogError($msg);
    }

  // Display help
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      exit(0);
    }

  // Initialize classes
  $cc  = new cURL();
  $ad  = new AkamaiDecryptor();
  $f4f = new F4F();

  // Process command line options
  if (isset($cli->params['unknown']))
      $baseFilename = $cli->params['unknown'][0];
  if ($cli->getParam('debug'))
      $debug = true;
  if ($cli->getParam('delete'))
      $delete = true;
  if ($cli->getParam('fproxy'))
      $cc->fragProxy = true;
  if ($cli->getParam('rename'))
      $rename = $cli->getParam('rename');
  if ($cli->getParam('update'))
      $update = true;
  if ($cli->getParam('adkey'))
      $ad->sessionKey = unhexlify($cli->getParam('adkey'));
  if ($cli->getParam('auth'))
      $f4f->auth = '?' . $cli->getParam('auth');
  if ($cli->getParam('duration'))
      $duration = $cli->getParam('duration');
  if ($cli->getParam('filesize'))
      $filesize = $cli->getParam('filesize');
  if ($cli->getParam('fixwindow'))
      $f4f->fixWindow = $cli->getParam('fixwindow');
  if ($cli->getParam('fragments'))
      $baseFilename = $cli->getParam('fragments');
  if ($cli->getParam('manifest'))
      $manifest = $cli->getParam('manifest');
  if ($cli->getParam('maxspeed'))
      $maxSpeed = $cli->getParam('maxspeed');
  if ($cli->getParam('outdir'))
      $outDir = $cli->getParam('outdir');
  if ($cli->getParam('outfile'))
      $outFile = $cli->getParam('outfile');
  if ($cli->getParam('parallel'))
      $f4f->parallel = $cli->getParam('parallel');
  if ($cli->getParam('proxy'))
      $cc->proxy = $cli->getParam('proxy');
  if ($cli->getParam('quality'))
      $f4f->quality = $cli->getParam('quality');
  if ($cli->getParam('referrer'))
      $referrer = $cli->getParam('referrer');
  if ($cli->getParam('start'))
      $start = $cli->getParam('start');
  if ($cli->getParam('useragent'))
      $cc->user_agent = $cli->getParam('useragent');

  // Use custom referrer
  if ($referrer)
      $cc->headers[] = "Referer: " . $referrer;

  // Update the script
  if ($update)
    {
      LogInfo("Updating script....");
      $status = $cc->get("https://raw.github.com/K-S-V/Scripts/master/AdobeHDS.php");
      if ($status == 200)
        {
          if (md5($cc->response) == md5(file_get_contents($argv[0])))
              LogError("You are already using the latest version of this script.", 0);
          $status = file_put_contents($argv[0], $cc->response);
          if (!$status)
              LogError("Failed to write script file");
          LogError("Script has been updated successfully.", 0);
        }
      else
          LogError("Failed to update script");
    }

  // Set overall maximum bandwidth for fragment downloading
  if ($maxSpeed > 0)
    {
      $cc->maxSpeed = ($maxSpeed * 1024) / $f4f->parallel;
      LogDebug(sprintf("Setting maximum speed to %.2f KB per fragment (overall %d KB)", $cc->maxSpeed / 1024, $maxSpeed));
    }

  // Create output directory
  if ($outDir)
    {
      $outDir = rtrim(str_replace('\\', '/', $outDir));
      if (!file_exists($outDir))
        {
          LogDebug("Creating destination directory " . $outDir);
          if (!mkdir($outDir, 0777, true))
              LogError("Failed to create destination directory " . $outDir);
        }
    }

  // Remove existing file extension
  if ($outFile)
      $outFile = RemoveExtension($outFile);

  // Disable filesize when piping
  if ($play)
      $filesize = 0;

  // Disable metadata if it invalidates the stream duration
  if ($start or $duration or $filesize)
      $metadata = false;

  // Set f4f options
  $f4f->baseFilename = $baseFilename;
  $f4f->debug        = $debug;
  $f4f->format       = $format;
  $f4f->metadata     = $metadata;
  $f4f->outDir       = $outDir;
  $f4f->outFile      = $outFile;
  $f4f->play         = $play;
  $f4f->rename       = $rename;

  // Download fragments when manifest is available
  $opt = array(
      'ad' => $ad,
      'cc' => $cc,
      'duration' => 0,
      'filesize' => $filesize,
      'start' => $start,
      'tDuration' => $duration
  );
  if ($manifest)
    {
      $manifest = AbsoluteUrl("http://", $manifest);
      $f4f->DownloadFragments($manifest, $opt);
      $baseFilename = $f4f->baseFilename;
      $rename       = $f4f->rename;
    }

  // Determine output filename
  if (!$outFile)
    {
      $baseFilename = str_replace('\\', '/', $baseFilename);
      $lastChar     = substr($baseFilename, -1);
      if ($baseFilename and !(($lastChar == '/') or ($lastChar == ':')))
        {
          $lastSlash = strrpos($baseFilename, '/');
          if ($lastSlash)
              $outFile = substr($baseFilename, $lastSlash + 1);
          else
              $outFile = $baseFilename;
        }
      else
          $outFile = "Joined";
      $outFile = RemoveExtension($outFile);
    }

  // Check for available fragments and rename if required
  if ($f4f->fragStart)
      $fragStart = $f4f->fragStart;
  else if ($start)
      $fragStart = $start - 1;
  $files = GetFragmentList($baseFilename, $fragStart, $fileExt);
  if ($rename)
    {
      $files     = RenameFragments($files, $baseFilename);
      $fragStart = 0;
    }
  $fragCount = count($files);
  if (!($f4f->live or $f4f->play))
      LogInfo("Found " . $fragCount . " fragments");

  // Process available fragments
  if (!$f4f->processed)
    {
      if ($fragCount < 1)
          exit(1);
      $f4f->lastFrag = $fragStart;
      $f4f->outFile  = $outFile;
      $timeStart     = microtime(true);
      LogDebug("Joining Fragments:");
      $count = 0;
      foreach ($files as $id => $file)
        {
          $frag['id']       = $id;
          $frag['response'] = file_get_contents($file);
          LogInfo("Processed " . (++$count) . " fragments", true);
          if ($f4f->WriteFragment($frag, $opt) === STOP_PROCESSING)
              break;
        }
      unset($id, $file);
      if (isset($opt['flvFile']))
          fclose($opt['flvFile']);
      $timeEnd   = microtime(true);
      $timeTaken = sprintf("%.2f", $timeEnd - $timeStart);
      LogInfo("Joined " . $count . " fragments in " . $timeTaken . " seconds");
    }

  // Delete fragments after processing
  if ($delete)
    {
      foreach ($files as $file)
        {
          if (!unlink($file))
              LogInfo("Failed to delete file '" . $file . "'");
        }
    }

  LogInfo("Finished");
?>
