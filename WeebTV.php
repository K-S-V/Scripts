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
      $BatchFile = fopen("WeebTV.bat", 'w');
      fwrite($BatchFile, "@Echo off\r\n");
      fwrite($BatchFile, "Title $filename\r\n");
      fwrite($BatchFile, "$command\r\n");
      fwrite($BatchFile, "Del \"WeebTV.bat\"\r\n");
      fclose($BatchFile);
      $WshShell = new COM("WScript.Shell");
      $oExec    = $WshShell->Run("WeebTV.bat", 1, false);
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
              printf("%-25.25s = %s\n", preg_replace('/=/', '-', $name), $url);
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

  function ReadSettings()
    {
      global $quality, $username, $password;
      if (file_exists("WeebTV.xml"))
        {
          $xml      = simplexml_load_file("WeebTV.xml");
          $quality  = $xml->quality;
          $username = $xml->username;
          $password = $xml->password;
        }
      else
        {
          $quality  = "HI";
          $username = "";
          $password = "";
        }
    }

  function LogIn()
    {
      global $cc, $logged_in, $username, $password;
      if (($username != "") && ($password != ""))
        {
          $cc->post("http://weeb.tv/account/login", "username=$username&userpassword=$password");
          $logged_in = true;
        }
      else
          $logged_in = false;
    }

  function LogOut()
    {
      global $cc, $logged_in;
      if ($logged_in)
        {
          $cc->get("http://weeb.tv/account/logout");
          $logged_in = false;
        }
    }

  function ShowChannel($url, $filename)
    {
      global $cc, $format, $password, $PremiumUser, $quality, $username, $vlc, $windows, $cli;
      qecho("Retrieving html . . .\n");
      $cc->headers = $cc->headers();
      $html        = $cc->get($url);
      preg_match('/flashvars.*?cid[^\d]+?(\d+)/is', $html, $cid);
      if (!isset($cid[1]))
          Close("No channel id found");

      // Retrieve rtmp stream info
      $cc->headers[] = "Referer: http://weeb.tv/static/player.swf";
      $response      = $cc->post("http://weeb.tv/api/setPlayer", "cid=$cid[1]&watchTime=0&firstConnect=1&ip=NaN");
      $result        = explode("\r\n\r\n", $response, 2);
      $flashVars     = explode("&", trim($result[1]));
      foreach ($flashVars as $flashVar)
        {
          $temp          = explode("=", $flashVar);
          $name          = strtolower($temp[0]);
          $Params[$name] = $temp[1];
        }
      $rtmp         = urldecode($Params["10"]);
      $playpath     = urldecode($Params["11"]);
      $MultiBitrate = urldecode($Params["20"]);
      $PremiumUser  = urldecode($Params["5"]);
      if ($MultiBitrate)
          $playpath .= $quality;

      $BlockType = urldecode($Params["13"]);
      if ($BlockType != 0)
        {
          switch ($BlockType)
          {
              case 1:
                  $BlockTime        = urldecode($Params["14"]);
                  $ReconnectionTime = urldecode($Params["16"]);
                  Close("You have crossed free viewing limit. you have been blocked for $BlockTime minutes. try again in $ReconnectionTime minutes.");
                  break;
              case 11:
                  Close("No free slots available");
                  break;
              default:
                  break;
          }
        }

      if (!isset($Params["73"]))
        {
          // Retrieve authentication token
          $response  = $cc->post("http://weeb.tv/setplayer", "cid=$cid[2]&watchTime=0&firstConnect=0&ip=NaN");
          $result    = explode("\r\n\r\n", $response, 2);
          $flashVars = explode("&", trim($result[1]));
          foreach ($flashVars as $flashVar)
            {
              $temp          = explode("=", $flashVar);
              $name          = strtolower($temp[0]);
              $Params[$name] = $temp[1];
            }
        }

      if (isset($Params["73"]))
          $token = $Params["73"];
      else
          Close("Server seems busy. please try after some time.");
      qprintf($format, "RTMP Url", $rtmp);
      qprintf($format, "Playpath", $playpath);
      qprintf($format, "Token", $token);
      qprintf($format, "Premium", $PremiumUser ? "Yes" : "No");
      if (($username != "") && ($password != ""))
          $token = "$token;$username;$password";

      $filename = SafeFileName($filename);
      if (file_exists($filename . ".flv"))
          unlink($filename . ".flv");
      $basecmd = 'rtmpdump -r "' . $rtmp . "/" . $playpath . '" -W "http://static2.weeb.tv/player.swf" --weeb "' . $token . "\" --live";
      $command = $basecmd . " | \"$vlc\" --meta-title \"$filename\" -";

      if ($cli->getParam('print'))
        {
          echo $basecmd;
          exit(0);
        }

      qprintf($format, "Command", $command);
      if ($rtmp && $token)
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
  $header        = "KSV WeebTV Downloader";
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
  $cli = new CLI();
  $cc  = new cURL();

  ShowHeader($header);
  if ($cli->getParam('help'))
    {
      $cli->displayHelp();
      Close("");
    }
  if ($cli->getParam('proxy'))
      $cc->proxy = $cli->getParam('proxy');
  ReadSettings();
  LogIn();

  if ($cli->getParam('url'))
    {
      $url = $cli->getParam('url');

      // You can use only the channel name
      if (!preg_match('/^http/', $url))
        {
          $url = preg_replace('/\.\S+$/', '', $url); // also with extension (like .mpg etc...)
          $url = "http://weeb.tv/online/$url";
        }

      $filename = strrchr($url, '/');
      ShowChannel($url, $filename);
    }
  else
    {
      $html = $cc->get("http://weeb.tv/channels/live");
      preg_match('/<ul class="channels">(.*?)<\/ul>/is', $html, $html);
      $html = $html[1];
      preg_match_all('/<fieldset[^>]+>(.*?)<\/fieldset>/is', $html, $fieldSets);
      foreach ($fieldSets[1] as $fieldSet)
        {
          preg_match('/12px.*?<a href="([^"]+)"[^>]+>(.*?)<\/a>/i', $fieldSet, $channelVars);
          $ChannelList[$channelVars[2]] = $channelVars[1];
        }
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
