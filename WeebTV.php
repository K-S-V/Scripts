<?php
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
              LogInfo(sprintf(" --%-13s %s", $key, $value));
          foreach (self::$ACCEPTED[1] as $key => $value)
              LogInfo(sprintf(" --%-5s%-8s %s", $key, " [param]", $value));
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
      var $cert_check, $proxy;
      static $ref = 0;

      function cURL($cookies = true, $cookie = 'Cookies.txt', $compression = 'gzip', $proxy = '')
        {
          $this->headers     = $this->headers();
          $this->user_agent  = 'Mozilla/5.0 (Windows NT 5.1; rv:41.0) Gecko/20100101 Firefox/41.0';
          $this->compression = $compression;
          $this->cookies     = $cookies;
          if ($this->cookies == true)
              $this->cookie($cookie);
          $this->cert_check = false;
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
          $return = curl_exec($process);
          curl_close($process);
          return $return;
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

      function error($error)
        {
          LogError("cURL Error : $error");
        }
    }

  function Close($message)
    {
      global $cli;
      if ($message)
          LogInfo($message);
      if (!count($cli->params))
          sleep(2);
      exit(0);
    }

  function Display($items, $format, $columns)
    {
      global $cli;

      // Display formatted channels list for external script
      if ($cli->getParam('list'))
        {
          foreach ($items as $name => $url)
              printf("%-25.25s = %s" . PHP_EOL, preg_replace('/=/', '-', $name), $url);
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
          printf(PHP_EOL . PHP_EOL);
        }
    }

  function GetApiResponse($cc, $url, $data)
    {
      $response = $cc->post($url, $data);
      $result   = explode("\r\n\r\n", $response, 2);
      $vars     = explode("&", trim($result[1]));
      foreach ($vars as $var)
        {
          $temp          = explode("=", $var);
          $name          = strtolower($temp[0]);
          $Params[$name] = urldecode($temp[1]);
        }
      return $Params;
    }

  function GetHtmlResponse($cc, $url)
    {
      $retries = 0;
      while ($retries < 5)
        {
          $html = $cc->get($url);
          if (preg_match("/NAME=\"robots\"/i", $html))
            {
              $cc->get("http://weeb.tv/_Incapsula_Resource?SWHANEDL=807917559759548716,1918358409640862410,9717984079971852691,283468");
              $retries++;
              usleep(1000000);
            }
          else
              break;
        }
      return $html;
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

  function LogOut()
    {
      global $cc, $logged_in;
      if ($logged_in)
        {
          GetHtmlResponse($cc, "http://weeb.tv/account/logout");
          $logged_in = false;
        }
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

  function RunAsyncBatch($command, $filename)
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

  function ShowChannel($url, $filename)
    {
      global $cc, $cli, $format, $password, $PremiumUser, $quality, $username, $vlc, $windows;
      LogInfo("Retrieving info....");
      $cc->headers = $cc->headers();
      $cid         = substr($url, strrpos($url, '/') + 1);
      if (!$cid)
          Close("No channel id found");

      // Retrieve rtmp stream info
      $cc->headers[] = "Referer: http://static.weeb.tv/player.swf";
      $Params        = GetApiResponse($cc, "http://weeb.tv/api/setPlayer", "cid=" . $cid . "&watchTime=0&firstConnect=1&ip=NaN");
      if (isset($Params[0]) and $Params[0] <= 0)
          Close("Server refused to send required parameters.");
      $rtmp         = $Params["10"];
      $playpath     = $Params["11"];
      $MultiBitrate = $Params["20"];
      $PremiumUser  = $Params["5"];
      if ($MultiBitrate)
          $playpath .= $quality;

      $BlockType = $Params["13"];
      if ($BlockType != 0)
        {
          switch ($BlockType)
          {
              case 1:
                  $BlockTime        = $Params["14"];
                  $ReconnectionTime = $Params["16"];
                  Close("You have crossed free viewing limit. you have been blocked for $BlockTime minutes. try again in $ReconnectionTime minutes.");
                  break;
              case 11:
                  Close("No free slots available");
                  break;
              default:
                  break;
          }
        }

      // Retrieve authentication token
      if (!isset($Params["73"]))
          $Params = GetApiResponse($cc, "http://weeb.tv/api/setPlayer", "cid=" . $cid . "&watchTime=0&firstConnect=0&ip=NaN");

      if (isset($Params["73"]))
          $token = $Params["73"];
      else
          Close("Server seems busy, please try after some time.");
      LogInfo(sprintf($format, "RTMP Url", $rtmp));
      LogInfo(sprintf($format, "Playpath", $playpath));
      LogInfo(sprintf($format, "Token", $token));
      LogInfo(sprintf($format, "Premium", $PremiumUser ? "Yes" : "No"));
      if (($username != "") && ($password != ""))
          $token = "$token;$username;$password";

      $filename = SafeFileName($filename);
      if (file_exists($filename . ".flv"))
          unlink($filename . ".flv");
      $basecmd = 'rtmpdump -r "' . $rtmp . "/" . $playpath . '" -W "http://static.weeb.tv/player.swf" --weeb "' . $token . "\" --live";
      $command = $basecmd . " | \"$vlc\" --meta-title \"$filename\" -";

      if ($cli->getParam('print'))
        {
          printf($basecmd);
          exit(0);
        }

      LogInfo(sprintf($format, "Command", $command));
      if ($rtmp && $token)
          if ($windows)
              RunAsyncBatch($command, $filename);
          else
              exec($command);
    }

  function ShowHeader()
    {
      $header = "KSV WeebTV Downloader";
      $len    = strlen($header);
      $width  = floor((80 - $len) / 2) + $len;
      $format = "\n%" . $width . "s\n\n";
      printf($format, $header);
    }

  // Global code starts here
  $format        = "%-8s: %s";
  $ChannelFormat = "%2d) %-22.21s";
  $quiet         = false;

  $options = array(
      0 => array(
          'help' => 'displays this help',
          'list' => 'display formatted channels list and exit',
          'print' => 'only print the base rtmpdump command, don\'t start anything',
          'quiet' => 'disables unnecessary output'
      ),
      1 => array(
          'proxy' => 'use proxy to retrieve channel information',
          'url' => 'use specified url without displaying channels list'
      )
  );
  $cli     = new CLI($options);
  if ($cli->getParam('quiet'))
      $quiet = true;
  if (!$quiet)
      ShowHeader();

  $windows = (strncasecmp(php_uname('s'), "Win", 3) == 0 ? true : false);
  if ($windows)
    {
      if (file_exists("C:\\Program Files (x86)\\VideoLAN\\VLC\\vlc.exe"))
          $vlc = "C:\\Program Files (x86)\\VideoLAN\\VLC\\vlc.exe";
      else
          $vlc = "C:\\Program Files\\VideoLAN\\VLC\\vlc.exe";
    }
  else
      $vlc = "vlc";
  $cc = new cURL();

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
      $html = GetHtmlResponse($cc, "http://weeb.tv/channels/live");
      preg_match('/<ul class="channels">(.*?)<\/ul>/is', $html, $html);
      $html = $html[1];
      preg_match_all('/<fieldset[^>]+>(.*?)<\/fieldset>/is', $html, $fieldSets);
      foreach ($fieldSets[1] as $fieldSet)
        {
          preg_match('/12px.*?<a href="([^"]+)"[^>]+>(.*?)<\/a>/i', $fieldSet, $channelVars);
          $ChannelList[$channelVars[2]] = $channelVars[1];
        }
      uksort($ChannelList, 'strnatcasecmp');

      $FirstRun    = true;
      $KeepRunning = true;
      while ($KeepRunning)
        {
          if ($FirstRun)
              $FirstRun = false;
          else
              ShowHeader();
          Display($ChannelList, $ChannelFormat, 3);
          printf("Enter Channel Number : ");
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
