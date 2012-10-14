<?php
  class CLI
    {
      protected static $ACCEPTED = array(
          0 => array(
              'help'  => 'displays this help',
              'list'  => 'display formatted channels list and exit',
              'print' => 'only print the base rtmpdump command, don\'t start anything',
              'quiet' => 'disables unnecessary output'
          ),
          1 => array(
              'proxy' => 'use proxy to retrieve channel information',
              'url'   => 'use specified url without displaying channels list'
          )
      );
      var $params = array();

      function __construct()
        {
          global $argc, $argv;

          // Parse params
          if ($argc > 1)
            {
              $paramSwitch = false;
              for ($i = 1; $i < $argc; $i++)
                {
                  $arg      = $argv[$i];
                  $isSwitch = preg_match('/^--/', $arg);

                  if ($isSwitch)
                      $arg = preg_replace('/^--/', '', $arg);

                  if ($paramSwitch && $isSwitch)
                    {
                      echo "[param] expected after '$paramSwitch' switch (" . self::$ACCEPTED[1][$paramSwitch] . ")\n";
                      exit(1);
                    }
                  else if (!$paramSwitch && !$isSwitch)
                    {
                      echo "'$arg' is an invalid switch, use --help to display valid switches\n";
                      exit(1);
                    }
                  else if (!$paramSwitch && $isSwitch)
                    {
                      if (isset($this->params[$arg]))
                        {
                          echo "'$arg' switch cannot occur more than once\n";
                          exit(1);
                        }

                      $this->params[$arg] = true;
                      if (isset(self::$ACCEPTED[1][$arg]))
                          $paramSwitch = $arg;
                      else if (!isset(self::$ACCEPTED[0][$arg]))
                        {
                          echo "there's no '$arg' switch, use --help to display all switches\n";
                          exit(1);
                        }
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
                {
                  echo "[param] expected after '$k' switch (" . self::$ACCEPTED[1][$k] . ")\n";
                  exit(1);
                }
        }

      function getParam($name)
        {
          if (isset($this->params[$name]))
              return $this->params[$name];
          else
              return "";
        }

      function displayHelp()
        {
          echo "You can use script with following switches: \n\n";
          foreach (self::$ACCEPTED[0] as $key => $value)
              printf(" --%-14s%s\n", $key, $value);
          foreach (self::$ACCEPTED[1] as $key => $value)
              printf(" --%-5s%-9s%s\n", $key, " [param]", $value);
        }
    }

  define('CRYPT_XXTEA_DELTA', 0x9E3779B9);
  class Crypt_XXTEA
    {
      var $_key;

      function setKey($key)
        {
          if (is_string($key))
              $k = $this->_str2long($key, false);
          elseif (is_array($key))
              $k = $key;
          else
              qecho("The secret key must be a string or long integer array\n");

          if (count($k) > 4)
              qecho("The secret key cannot be more than 16 characters or 4 long values\n");
          elseif (count($k) == 0)
              qecho("The secret key cannot be empty\n");
          elseif (count($k) < 4)
              for ($i = count($k); $i < 4; $i++)
                  $k[$i] = 0;

          $this->_key = $k;
          return true;
        }

      function encrypt($plaintext)
        {
          if ($this->_key == null)
              qecho("Secret key is undefined\n");

          if (is_string($plaintext))
              return $this->_encryptString($plaintext);
          elseif (is_array($plaintext))
              return $this->_encryptArray($plaintext);
          else
              qecho("The plain text must be a string or long integer array\n");
        }

      function decrypt($ciphertext)
        {
          if ($this->_key == null)
              qecho("Secret key is undefined\n");

          if (is_string($ciphertext))
              return $this->_decryptString($ciphertext);
          elseif (is_array($ciphertext))
              return $this->_decryptArray($ciphertext);
          else
              qecho("The cipher text must be a string or long integer array\n");
        }

      function _encryptString($str)
        {
          if ($str == '')
              return '';
          $v = $this->_str2long($str, false);
          $v = $this->_encryptArray($v);
          return $this->_long2str($v, false);
        }

      function _encryptArray($v)
        {
          $n   = count($v) - 1;
          $z   = $v[$n];
          $y   = $v[0];
          $q   = floor(6 + 52 / ($n + 1));
          $sum = 0;
          while (0 < $q--)
            {
              $sum = $this->_int32($sum + CRYPT_XXTEA_DELTA);
              $e   = $sum >> 2 & 3;
              for ($p = 0; $p < $n; $p++)
                {
                  $y  = $v[$p + 1];
                  $mx = $this->_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ $this->_int32(($sum ^ $y) + ($this->_key[$p & 3 ^ $e] ^ $z));
                  $z  = $v[$p] = $this->_int32($v[$p] + $mx);
                }
              $y  = $v[0];
              $mx = $this->_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ $this->_int32(($sum ^ $y) + ($this->_key[$p & 3 ^ $e] ^ $z));
              $z  = $v[$n] = $this->_int32($v[$n] + $mx);
            }
          return $v;
        }

      function _decryptString($str)
        {
          if ($str == '')
              return '';
          $v = $this->_str2long($str, false);
          $v = $this->_decryptArray($v);
          return $this->_long2str($v, false);
        }

      function _decryptArray($v)
        {
          $n   = count($v) - 1;
          $z   = $v[$n];
          $y   = $v[0];
          $q   = floor(6 + 52 / ($n + 1));
          $sum = $this->_int32($q * CRYPT_XXTEA_DELTA);
          while ($sum != 0)
            {
              $e = $sum >> 2 & 3;
              for ($p = $n; $p > 0; $p--)
                {
                  $z  = $v[$p - 1];
                  $mx = $this->_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ $this->_int32(($sum ^ $y) + ($this->_key[$p & 3 ^ $e] ^ $z));
                  $y  = $v[$p] = $this->_int32($v[$p] - $mx);
                }
              $z   = $v[$n];
              $mx  = $this->_int32((($z >> 5 & 0x07FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ $this->_int32(($sum ^ $y) + ($this->_key[$p & 3 ^ $e] ^ $z));
              $y   = $v[0] = $this->_int32($v[0] - $mx);
              $sum = $this->_int32($sum - CRYPT_XXTEA_DELTA);
            }
          return $v;
        }

      function _long2str($v, $w)
        {
          $len = count($v);
          $s   = '';
          for ($i = 0; $i < $len; $i++)
              $s .= pack('V', $v[$i]);
          if ($w)
              return substr($s, 0, $v[$len - 1]);
          else
              return $s;
        }

      function _str2long($s, $w)
        {
          $v = array_values(unpack('V*', $s . str_repeat("\0", (4 - strlen($s) % 4) & 3)));
          if ($w)
              $v[] = strlen($s);
          return $v;
        }

      function _int32($n)
        {
          while ($n >= 2147483648)
              $n -= 4294967296;
          while ($n <= -2147483649)
              $n += 4294967296;
          return (int) $n;
        }
    }

  class cURL
    {
      var $headers, $user_agent, $compression, $cookie_file;
      var $cert_check, $proxy;
      static $ref = 0;

      function cURL($cookies = true, $cookie = 'Cookies.txt', $compression = 'gzip', $proxy = '')
        {
          $this->headers     = $this->headers();
          $this->user_agent  = 'Mozilla/5.0 (Windows NT 5.1; rv:15.0) Gecko/20100101 Firefox/15.0';
          $this->compression = $compression;
          $this->cookies     = $cookies;
          if ($this->cookies == true)
              $this->cookie($cookie);
          $this->cert_check = true;
          $this->proxy      = $proxy;
          self::$ref++;
        }

      function __destruct()
        {
          if ((self::$ref <= 1) and file_exists($this->cookie_file))
              unlink($this->cookie_file);
          self::$ref--;
        }

      function headers()
        {
          $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
          $headers[] = 'Connection: Keep-Alive';
          $headers[] = 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8';
          return $headers;
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
            {
              curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
              curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
            }
          curl_setopt($process, CURLOPT_ENCODING, $this->compression);
          curl_setopt($process, CURLOPT_TIMEOUT, 30);
          if ($this->proxy)
              $this->setProxy($process, $this->proxy);
          curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
          if (!$this->cert_check)
              curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 0);
          $return = curl_exec($process);
          curl_close($process);
          return $return;
        }

      function post($url, $data)
        {
          $process = curl_init($url);
          curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
          curl_setopt($process, CURLOPT_HEADER, 1);
          curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
          if ($this->cookies == true)
            {
              curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
              curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
            }
          curl_setopt($process, CURLOPT_ENCODING, $this->compression);
          curl_setopt($process, CURLOPT_TIMEOUT, 30);
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

      function error($error)
        {
          echo "cURL Error : $error";
          die;
        }
    }

  function runAsyncBatch($command, $filename)
    {
      $BatchFile = fopen("PlayTV.bat", 'w');
      fwrite($BatchFile, "@Echo off\r\n");
      fwrite($BatchFile, "Title $filename\r\n");
      fwrite($BatchFile, "$command\r\n");
      fwrite($BatchFile, "Del \"PlayTV.bat\"\r\n");
      fclose($BatchFile);
      $WshShell = new COM("WScript.Shell");
      $oExec    = $WshShell->Run("PlayTV.bat", 1, false);
      unset($WshShell, $oExec);
    }

  function SafeFileName($filename)
    {
      $len = strlen($filename);
      for ($i = 0; $i < $len; $i++)
        {
          $char = ord($filename[$i]);
          if (($char < 32) || ($char >= 127))
              $filename = substr_replace($filename, ' ', $i, 1);
        }
      $filename = preg_replace('/[\/\\\?\*\:\|\<\>]/i', ' ', $filename);
      $filename = preg_replace('/\s\s+/i', ' ', $filename);
      $filename = trim($filename);
      return $filename;
    }

  function ShowHeader($header)
    {
      global $cli;
      $len    = strlen($header);
      $width  = (int) ((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      if (!$cli->getParam('quiet'))
          printf($format, $header);
    }

  function KeyName(array $a, $pos)
    {
      $temp = array_slice($a, $pos, 1, true);
      return key($temp);
    }

  function ci_uksort($a, $b)
    {
      $a = strtolower($a);
      $b = strtolower($b);
      return strnatcmp($a, $b);
    }

  function Display($items, $format, $columns)
    {
      global $cli;

      // Display formatted channels list for external script
      if ($cli->getParam('list'))
        {
          foreach ($items as $name => $url)
            {
              printf("%-25.20s", preg_replace('/=/', '-', $name));
              printf(" = %s\n", $url);
            }
          exit(0);
        }

      $numcols  = $columns;
      $numitems = count($items);
      $numrows  = ceil($numitems / $numcols);

      for ($row = 1; $row <= $numrows; $row++)
        {
          $cell = 0;
          for ($col = 1; $col <= $numcols; $col++)
            {
              if ($col === 1)
                {
                  $cell += $row;
                  printf($format, $cell, KeyName($items, $cell - 1));
                }
              else
                {
                  $cell += $numrows;
                  if (isset($items[KeyName($items, $cell - 1)]))
                      printf($format, $cell, KeyName($items, $cell - 1));
                }
            }
          echo "\n\n";
        }
    }

  function Close($message)
    {
      global $cli, $windows;
      if ($message)
          qecho($message . "\n");
      if ($windows)
          exec("chcp 1252");
      if (!count($cli->params))
          sleep(2);
      die();
    }

  function ShowChannel($url, $filename)
    {
      global $cc, $cli, $format, $vlc, $windows, $xxtea;
      qecho("Retrieving html . . .\n");
      $cc->headers = $cc->headers();

      // Retrieve channel id and primary key
      $timestamp = time();
      $player_id = $url;
      $init      = $cc->get("http://tvplayer.playtv.fr/js/$player_id.js?_=$timestamp");
      preg_match("/b:[^{]*?({[^}]+})/i", $init, $init);
      $init = json_decode(trim($init[1]));
      if (!$init)
          Close("Unable to retrieve initialization parameters");
      $a = pack("H*", $init->{'a'});
      $b = pack("H*", $init->{'b'});

      $xxtea->setKey("object");
      $params = json_decode(trim($xxtea->decrypt($b)));
      if (!$params)
          Close("Unable to decode initialization parameters");
      $key = $xxtea->decrypt(pack("H*", $params->{'k'}));
      $xxtea->setKey($key);
      $params     = json_decode(trim($xxtea->decrypt($a)));
      $channel_id = $params->{'i'};
      $api_url    = $params->{'u'};

      // Generate parameter request
      $request       = json_encode(array(
          'i' => $channel_id,
          't' => $timestamp,
          'h' => 'playtv.fr',
          'a' => 5
      ));
      $request       = unpack("H*", $xxtea->encrypt($request));
      $request       = $request[1];
      $cc->headers[] = "Referer: http://static.playtv.fr/swf/tvplayer.swf?r=18";
      $cc->headers[] = "x-flash-version: 11,4,402,265";
      $response      = $cc->get($api_url . $request);

      // Decode server response
      $response = pack("H*", $response);
      $params   = json_decode(trim($xxtea->decrypt($response)));
      if (!$params)
          Close("Unable to decode server response");
      if (isset($params->{'s'}[1]))
          $streams = $params->{'s'}[0]->{'bitrate'} > $params->{'s'}[1]->{'bitrate'} ? $params->{'s'}[0] : $params->{'s'}[1];
      else
          $streams = $params->{'s'}[0];
      $scheme   = $streams->{'scheme'};
      $host     = $streams->{'host'};
      $port     = $streams->{'port'};
      $app      = $streams->{'application'};
      $playpath = $streams->{'stream'};
      $token    = $streams->{'token'};
      $title    = $streams->{'title'};

      // Generate authentication token for rtmp server
      $t = $params->{'j'}->{'t'};
      $k = $params->{'j'}->{'k'};
      $xxtea->setKey("object");
      $key = $xxtea->decrypt(pack("H*", $k));
      $xxtea->setKey($key);
      $auth = unpack("H*", $xxtea->encrypt($t));
      $auth = $auth[1];

      if ($scheme == "http")
          qprintf($format, "HTTP Url", "$scheme://$host" . (isset($port) ? ":$port" : "") . "/$playpath");
      else
          qprintf($format, "RTMP Url", "$scheme://$host" . (isset($port) ? ":$port" : "") . "/$app");
      qprintf($format, "Playpath", $playpath);
      qprintf($format, "Auth", $auth);

      $filename = SafeFileName($filename);
      if (file_exists($filename . ".flv"))
          unlink($filename . ".flv");
      if ($scheme == "http")
        {
          $basecmd = "$scheme://$host" . (isset($port) ? ":$port" : "") . "/$playpath";
          $command = "\"$vlc\" --meta-title \"$title\" \"$basecmd\"";
        }
      else
        {
          $basecmd = "rtmpdump -r \"$scheme://$host" . (isset($port) ? ":$port" : "") . "/$app\" -a \"$app\" -s \"http://static.playtv.fr/swf/tvplayer.swf\" -p \"http://playtv.fr/television\" -C S:$auth " . (isset($token) ? "-T \"$token\" " : "") . "--live -y \"$playpath\"";
          $command = $basecmd . " | \"$vlc\" --meta-title \"$title\" -";
        }

      if ($cli->getParam('print'))
        {
          echo $basecmd;
          exit(0);
        }

      qprintf($format, "Command", $command);
      if ($host && $playpath && $auth)
          if ($windows)
              runAsyncBatch($command, $filename);
          else
              exec($command);
    }

  function qecho($str)
    {
      global $cli;
      if (!$cli->getParam('quiet'))
          echo $str;
    }

  function qprintf($format, $param, $arg)
    {
      global $cli;
      if (!$cli->getParam('quiet'))
          printf($format, $param, $arg);
    }

  // Global code starts here
  $header        = "KSV PlayTV Downloader";
  $format        = "%-8s: %s\n";
  $ChannelFormat = "%2d) %-22.21s";

  strncasecmp(php_uname('s'), "Win", 3) == 0 ? $windows = true : $windows = false;
  if ($windows)
    {
      exec("chcp 65001");
      if (file_exists("C:\\Program Files (x86)\\VideoLAN\\VLC\\vlc.exe"))
          $vlc = "C:\\Program Files (x86)\\VideoLAN\\VLC\\vlc.exe";
      else
          $vlc = "C:\\Program Files\\VideoLAN\\VLC\\vlc.exe";
    }
  else
      $vlc = "vlc";
  $cli   = new CLI();
  $cc    = new cURL();
  $xxtea = new Crypt_XXTEA();

  ShowHeader($header);
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      Close("");
    }
  if ($cli->getParam('proxy'))
      $cc->proxy = $cli->getParam('proxy');

  if ($cli->getParam('url'))
    {
      $url      = $cli->getParam('url');
      $filename = $url;
      ShowChannel($url, $filename);
    }
  else
    {
      $html = $cc->get("http://playtv.fr/television/");
      preg_match_all('/<a.*?data-channel="([^"]+).*?data-playerid="([^"]+)[^>]+>/i', $html, $links);
      for ($i = 0; $i < count($links[1]); $i++)
          $ChannelList[$links[1][$i]] = $links[2][$i];
      uksort($ChannelList, 'ci_uksort');

      $FirstRun    = true;
      $KeepRunning = true;
      while ($KeepRunning)
        {
          if ($FirstRun)
              $FirstRun = false;
          else
              ShowHeader($header);
          Display($ChannelList, $ChannelFormat, 3);
          echo "Enter Channel Number : ";
          $channel = trim(fgets(STDIN));
          if (is_numeric($channel) && ($channel >= 1) && ($channel <= count($ChannelList)))
            {
              $url      = $ChannelList[KeyName($ChannelList, $channel - 1)];
              $filename = KeyName($ChannelList, $channel - 1);
              ShowChannel($url, $filename);
            }
          else
              $KeepRunning = false;
        }
    }

  Close("Finished");
?>
