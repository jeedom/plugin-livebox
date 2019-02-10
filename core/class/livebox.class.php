<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class livebox extends eqLogic {
    /*     * *************************Attributs****************************** */
  public $_cookies;
  public $_contextID;
  public $_version = "2";
  public $_cmdHisto = 0;
  public $_cmdUnite = "";
  public $_cmdOrder = 1;
    /*     * ***********************Methode static*************************** */

  public static function pull() {
    foreach (self::byType('livebox') as $eqLogic) {
      $eqLogic->scan();
    }
  }

  function getCookiesInfo() {
    if ( ! isset($this->_cookies) )
    {
      $cookiefile =  jeedom::getTmpFolder('livebox') . "/livebox.cookie";
      // log::add('livebox','info',"get cookies $cookiefile");
      if ( ! defined("COOKIE_FILE") ) {
        define("COOKIE_FILE", $cookiefile);
      }
      $session = curl_init();

      curl_setopt($session, CURLOPT_HTTPHEADER, array(
         'Content-type: application/x-www-form-urlencoded',
         'User-Agent: Orange 8.0',
         'Host: '.$this->getConfiguration('ip'),
         'Accept: */*',
         'Content-Length: 0'
         )
      );
      $statuscmd = $this->getCmd(null, 'state');
      curl_setopt($session, CURLOPT_URL, 'http://'.$this->getConfiguration('ip').'/authenticate?username='.$this->getConfiguration('username').'&password='.$this->getConfiguration('password'));
      curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($session, CURLOPT_COOKIESESSION, true);
      curl_setopt($session, CURLOPT_COOKIEJAR, COOKIE_FILE);
      curl_setopt($session, CURLOPT_COOKIEFILE, COOKIE_FILE);
      curl_setopt($session, CURLOPT_POST, true);

      $json = curl_exec ($session);
      log::add('livebox','debug','json : '.$json);
      $httpCode = curl_getinfo($session, CURLINFO_HTTP_CODE);

      if ( $httpCode != 200 )
      {
        log::add('livebox','debug','version 4');
        $this->_version = "4";
        curl_close($session);
        $session = curl_init();

        $paramInternet = '{"service":"sah.Device.Information","method":"createContext","parameters":{"applicationName":"so_sdkut","username":"'.$this->getConfiguration('username').'","password":"'.$this->getConfiguration('password').'"}}';
        curl_setopt($session, CURLOPT_HTTPHEADER, array(
           'Content-type: application/x-sah-ws-4-call+json; charset=UTF-8',
           'User-Agent: Orange 8.0',
           'Host: '.$this->getConfiguration('ip'),
           'Accept: */*',
           'Authorization: X-Sah-Login',
           'Content-Length: '.strlen($paramInternet)
           )
        );
        curl_setopt($session, CURLOPT_POSTFIELDS, $paramInternet); 
        curl_setopt($session, CURLOPT_URL, 'http://'.$this->getConfiguration('ip').'/ws');
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_COOKIESESSION, true);
        curl_setopt($session, CURLOPT_COOKIEJAR, COOKIE_FILE);
        curl_setopt($session, CURLOPT_COOKIEFILE, COOKIE_FILE);
        curl_setopt($session, CURLOPT_POST, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
        $json = curl_exec ($session);
        if ( $json === false ) {
          if ( is_object($statuscmd) ) {
            if ($statuscmd->execCmd() != 0) {
              $statuscmd->setCollectDate('');
              $statuscmd->event(0);
            }
          }
          log::add('livebox','error',__('La livebox ne repond pas a la demande de cookie.',__FILE__)." ".$this->getName()." : ".curl_error ($session));
          throw new Exception(__('La livebox ne repond pas a la demande de cookie.', __FILE__));
          return false;
        }
      }
      else
      {
        log::add('livebox','debug','version 2');
        $this->_version = "2";
      }
      $info = curl_getinfo($session);
      curl_close($session);
      $obj = json_decode($json);
      if ( ! isset($obj->data->contextID) ) {
        log::add('livebox','debug','unable to get contextID');
        throw new Exception(__('Le compte est incorrect.', __FILE__));
        return false;
      }
      $this->_contextID = $obj->data->contextID;
      if ( ! file_exists ($cookiefile) )
      {
        log::add('livebox','error',__('Le compte est incorrect.',__FILE__));
        if ($statuscmd->execCmd() != 0) {
          $statuscmd->setCollectDate('');
          $statuscmd->event(0);
        }
        throw new Exception(__('Le compte est incorrect.', __FILE__));
        return false;
      }
      if (is_object($statuscmd) && $statuscmd->execCmd() != 1) {
        $statuscmd->setCollectDate('');
        $statuscmd->event(1);
      }
      $file = @fopen($cookiefile, 'r');
      if ( $file === false ) {
        log::add('livebox','debug','unable to read cookie file');
        return false;
      }
      $cookie= fread($file, 100000000);
      fclose($file);
      // unlink($cookiefile);
      
      // $cookie1 = explode ('	',$cookie);
      $cookie1 = explode ("\t",$cookie);
      $cookies = $cookie1[5].'='.$cookie1[6];
      $this->_cookies = trim($cookies);
      log::add('livebox','debug','get cookies done');
    }
    return true;
  }

  function getContext($paramInternet) {
    $httpInternet = array('http' =>
      array(
       'method' => 'POST',
       'header' =>  "Host: ".$this->getConfiguration('ip')."\r\n" .
              "Connection: keep-alive\r\n" .
              "Content-Length: ".(strlen($paramInternet))."\r\n" .
              "X-Context: ".$this->_contextID."\r\n" .
              "Authorization: X-Sah ".$this->_contextID."\r\n" .
              "Origin: http://".$this->getConfiguration('ip')."\r\n" .
              "User-Agent: Jeedom plugin\r\n" .
              "Content-type: application/x-sah-ws-4-call+json\r\n" .
              "Accept: */*\r\n" .
              "Accept-Encoding: gzip, deflate, br\r\n" .
              "Accept-Language: fr-FR,fr;q=0.8,en-US;q=0.6,en;q=0.4\r\n" .
              "Cookie: ".$this->_cookies."; ; sah/contextId=".$this->_contextID,
       'content' => $paramInternet
      )
    );
    return stream_context_create($httpInternet);
  }

  function logOut() {
    @file_get_contents ('http://'.$this->getConfiguration('ip').'/logout');
  }

  function getPage($page, $option = array()) {
    switch ($page) {
      case "internet":
        $listpage = array("sysbus/NMC:getWANStatus" => "");
        break;
      case "wifilist":
        $listpage = array("sysbus/NeMo/Intf/lan:getIntfs" => '"flag":"wlanradio","traverse":"down"');
        break;
      case "dsl":
        $listpage = array("sysbus/NeMo/Intf/data:getMIBs" => '"mibs":"dsl","flag":"","traverse":"down"');
        break;
      case "voip":
        $listpage = array("sysbus/VoiceService/VoiceApplication:listTrunks" => "");
        break;
      case "tv":
        $listpage = array("sysbus/NMC/OrangeTV:getIPTVStatus" => "");
        break;
      case "wifi":
        $listpage = array("sysbus/NeMo/Intf/lan:getMIBs" => '"mibs":"wlanvap","flag":"","traverse":"down"');
        break;
      case "reboot":
        $listpage = array("sysbus/NMC:reboot" => "");
        break;
      case "wpspushbutton":
        $listpage = array("sysbus/NeMo/Intf/lan:setWLANConfig" => '"mibs":{"wlanvap":{"wl0":{"WPS":{"ConfigMethodsEnabled":"PushButton,Label,Ethernet"}}},"wl1":{"WPS":{"ConfigMethodsEnabled":"PushButton,Label,Ethernet"}}}', 
                "sysbus/NeMo/Intf/wl0/WPS:pushButton" => '',
                "sysbus/NeMo/Intf/wl1/WPS:pushButton" => '');
        break;
      case "ring":
        $listpage = array("sysbus/VoiceService/VoiceApplication:ring" => "");
        break;
      case "changewifi":
        $listpage = array("sysbus/NeMo/Intf/lan:setWLANConfig" => '"mibs":{"penable":{"wifi'.$option[0].'_ath":{"PersistentEnable":'.$option[1].', "Enable":true}}}');
        break;
      case "devicelist":
        // $listpage = array("sysbus/Hosts:getDevices" => "");
        $listpage = array("sysbus/Devices:get" => "");
        break;
      case "listcalls":
        $listpage = array("sysbus/VoiceService.VoiceApplication:getCallList" => "");
        break;
    }
    $statuscmd = $this->getCmd(null, 'state');
    foreach ($listpage as $pageuri => $param)
    { $param = str_replace('/', '.', preg_replace('!sysbus/(.*):(.*)!i', '{"service":"$1", "method":"$2", "parameters": {'.$param.'}}', $pageuri));
      $pageuri = 'ws';
      log::add('livebox','debug',$page.' => get http://'.$this->getConfiguration('ip').'/ws');
      log::add('livebox','debug',$page.' => param '.$param);
      $content = @file_get_contents('http://'.$this->getConfiguration('ip').'/ws', false, $this->getContext($param));
      if ( is_object($statuscmd) )
      { if ( $content === false )
        { if ($statuscmd->execCmd() != 0)
          { $statuscmd->setCollectDate('');
            $statuscmd->event(0);
          }
          log::add('livebox','error',__('La livebox ne repond pas.',__FILE__)." ".$this->getName());
          return false;
        }
        log::add('livebox','debug','content '.$content);
        if ( $statuscmd->execCmd() != 1)
        { $statuscmd->setCollectDate('');
          $statuscmd->event(1);
        }
      }
      else
        break;
    }
    if ( $content === false )
      return false;
    else
    {
    /*
      $par = json_decode($param, true);
      $JsonFile = dirname(__FILE__).'/livebox-'.$par['service'].'-'.$par['method'].'.json';
      log::add('livebox','warning','Fichier '.$JsonFile);
      $fichierJson = fopen($JsonFile, "w");
      if($fichierJson !== FALSE)
      { fwrite($fichierJson, $content);
        fclose($fichierJson);
      }
     */

      $json = json_decode($content, true);
      if ( $json["status"] == "" )
      {
        log::add('livebox','warning','Demande non traitee par la livebox. Param: ' .print_r($param,true));
        return false;        
      }
      return $json;
    }
  }

  public function preUpdate()
  {
    if ( $this->getIsEnable() )
    {
      return $this->getCookiesInfo();
    }
  }

  public function preSave()
  {
    if ( $this->getIsEnable() )
    {
      return $this->getCookiesInfo();
    }
  }

/*  public function preInsert()
  {
    $this->setConfiguration('username', 'admin');
    $this->setConfiguration('password', 'admin');
    $this->setConfiguration('ip', 'livebox');
    $this->setLogicalId('livebox');
    $this->setEqType_name('livebox');
    $this->setIsEnable(1);
    $this->setIsVisible(0);
  }
*/
  public function getCmdOk($_logicID,$_name,$_type,$_display='',$_templateDash='')
  { $eqLogic_cmd = $this->getCmd(null, $_logicID);
    if ( ! is_object($eqLogic_cmd)) {
      $cmd = new liveboxCmd();
      $cmd->setName($_name);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setLogicalId($_logicID);
      $cmd->setUnite($this->_cmdUnite);
      $cmd->setOrder($this->_cmdOrder); $this->_cmdOrder++;
      $typ = explode(":",$_type);
      $nbtyp = count($typ);
      if ( $nbtyp == 1 || $nbtyp == 2 ) $cmd->setType($typ[0]);
      if ( $nbtyp == 2 ) $cmd->setSubType($typ[1]);
      if ( $this->_cmdHisto != -1 ) $cmd->setIsHistorized($this->_cmdHisto);
      $cmd->setEventOnly(1);
      if ( $_templateDash != '' ) $cmd->setTemplate("dashboard", $_templateDash);
      if ( $_display != '' ) $cmd->setDisplay("icon", $_display);
      $cmd->save();    
      $eqLogic_cmd = $cmd;
    }
    return($eqLogic_cmd);
  }

  public function postUpdate()
  { if ( $this->getIsEnable() )
    { $content = $this->getPage("internet");
      if ( $content !== false )
      { if($content["data"]["LinkType"] == "dsl" || $content["data"]["LinkType"] == "vdsl")
        { log::add('livebox','debug','Connexion mode dsl ou vdsl');
          $this->_cmdUnite = 'Kb/s'; $this->_cmdHisto = 0;
          $cmd = $this->getCmdOk('debitmontant','Débit montant','info:numeric','',"line");
          $cmd = $this->getCmdOk('debitdescendant','Débit descendant','info:numeric','',"line");

          $this->_cmdUnite = 'dB'; $this->_cmdHisto = 0;
          $cmd = $this->getCmdOk('margebruitmontant','Marge de bruit montant','info:numeric','',"line");
          $cmd = $this->getCmdOk('margebruitdescendant','Marge de bruit descendant','info:numeric','',"line");

          $this->_cmdUnite = 's'; $this->_cmdHisto = 1;
          $cmd = $this->getCmdOk('lastchange','Durée de la synchronisation DSL','info:numeric','',"line");

        }
        elseif ( $content->data->LinkType == "ethernet" ) {
          log::add('livebox','debug','Connexion mode ethernet');
          $cmd = $this->getCmd(null, 'debitmontant');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'debitdescendant');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'margebruitmontant');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'margebruitdescendant');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'lastchange');
          if ( is_object($cmd)) $cmd->remove();    

        }      
      }
      $cmd = $this->getCmd(null, 'reset');
      if ( is_object($cmd) ) $cmd->remove();    

      $content = $this->getPage("wifilist");
      if ( $content !== false ) {
        if ( count($content["status"]) == 1 ) {
          log::add('livebox','debug','Mode Wifi');
          $this->_cmdUnite = ''; $this->_cmdHisto = -1;
          $cmd = $this->getCmdOk('wifion','Activer wifi','action:other');
          $cmd = $this->getCmdOk('wifioff','Désactiver wifi','action:other');
          $cmd = $this->getCmd(null, 'wifi2.4on');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'wifi2.4off');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'wifi5on');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'wifi5off');
          if ( is_object($cmd)) $cmd->remove();    

          $this->_cmdUnite = ''; $this->_cmdHisto = 0;
          $cmd = $this->getCmdOk('wifistatus','Etat wifi','info:binary');
          $cmd = $this->getCmd(null, 'wifi5status');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'wifi2.4status');
          if ( is_object($cmd)) $cmd->remove();    
        } elseif ( count($content["status"]) == 2 ) {
          log::add('livebox','debug','Mode Wifi 2.4 et 5');
          $this->_cmdUnite = ''; $this->_cmdHisto = -1;
          $cmd = $this->getCmdOk('wifi2.4on','Activer wifi 2.4GHz','action:other');
          $cmd = $this->getCmdOk('wifi5on','Activer wifi 5GHz','action:other');
          $cmd = $this->getCmdOk('wifi2.4off','Désactiver wifi 2.4GHz','action:other');
          $cmd = $this->getCmdOk('wifi5off','Désactiver wifi 5GHz','action:other');
          $cmd = $this->getCmd(null, 'wifioff');
          if ( is_object($cmd)) $cmd->remove();    

          $cmd = $this->getCmd(null, 'wifion');
          if ( is_object($cmd)) $cmd->remove();    
 
          $this->_cmdUnite = ''; $this->_cmdHisto = 0;
          $cmd = $this->getCmdOk('wifi5status','Etat wifi 5GHz','info:binary');
          $cmd = $this->getCmdOk('wifi2.4status','Etat wifi 2.4GHz','info:binary');

          $cmd = $this->getCmd(null, 'wifistatus');
          if ( is_object($cmd)) $cmd->remove();    
        }
      }

      $cmd = $this->getCmd(null, 'voipstatus');
      if ( is_object($cmd)) $cmd->remove();    
      $cmd = $this->getCmd(null, 'numerotelephone');
      if ( is_object($cmd)) $cmd->remove();    

      $content = $this->getPage("voip");
      if ( $content !== false ) {
        log::add('livebox','debug','Mode VOIP');

        if ( isset($content["status"]) )
        {
          log::add('livebox','debug','Mode VOIP actif');
          foreach ( $content["status"] as $voip ) {
            if ( ! isset($voip["signalingProtocol"]) ) {
              $voip["signalingProtocol"] = strstr($voip["name"], "-", true);
            }
            if ( strtolower($voip["enable"]) == "enabled" ) {
              log::add('livebox','debug','Mode VOIP '.$voip["signalingProtocol"].' actif');
            if ( strtolower($voip["trunk_lines"]["0"]["enable"]) == "enabled" ) {
              $this->_cmdUnite = ''; $this->_cmdHisto = 0;
              $logicID = 'voipstatus'.$voip["signalingProtocol"];
              $logicName = 'Etat VoIP '.$voip["signalingProtocol"];
              $cmd = $this->getCmdOk($logicID,$logicName,'info:binary');
              $logicID = 'numerotelephone'.$voip["signalingProtocol"];
              $logicName = 'Numero de telephone '.$voip["signalingProtocol"];
              $cmd = $this->getCmdOk($logicID,$logicName,'info:binary');
            } else {
              $cmd = $this->getCmd(null, 'voipstatus'.$voip["signalingProtocol"]);
              if ( is_object($cmd)) $cmd->remove();    
              $cmd = $this->getCmd(null, 'numerotelephone'.$voip["signalingProtocol"]);
              if ( is_object($cmd)) $cmd->remove();    
            }
          } else {
              log::add('livebox','debug','Mode VOIP '.$voip["signalingProtocol"].' inactif');              
            }
          }
        }
        else
        {
          log::add('livebox','debug','Mode VOIP inactif');
        }
      }

      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $cmd = $this->getCmdOk('updatetime','Derniere actualisation','info:string');
  /* 
      $this->_cmdUnite = ''; $this->_cmdHisto = -1;
      $cmd = $this->getCmdOk('reset','Reset','action:other');
   */ 
      $this->_cmdUnite = ''; $this->_cmdHisto = -1;
      $cmd = $this->getCmdOk('reboot','Reboot','action:other');
      $cmd = $this->getCmdOk('ring','Sonner','action:other');
      $cmd = $this->getCmdOk('wpspushbutton','WPS Push Button','action:other');

      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $cmd = $this->getCmdOk('state','Etat','info:binary');
      $cmd = $this->getCmdOk('linkstate','Etat synchro','info:binary');
      $cmd = $this->getCmdOk('connectionstate','Etat connexion','info:binary');
      $cmd = $this->getCmdOk('tvstatus','Etat TV','info:binary');
      $cmd = $this->getCmdOk('ipwan','IPv4 WAN','info:string');
      $cmd = $this->getCmdOk('ipv6wan','IPv6 WAN','info:string');

      $this->refreshInfo();
      $this->logOut();
    }
  }

  public function scan()
  { if ( $this->getIsEnable() )
    { if ( $this->getCookiesInfo() )
      { $this->refreshInfo();
        $this->logOut();
      }
    }
  }

  function refreshInfo()
  { setlocale(LC_TIME,"fr_FR.utf8");
    $content = $this->getPage("internet");
    if ( $content !== false )
    {
      $value = $content["data"]["LinkState"];
      if($value == "up") $value = 1; else $value = 0;
      // log::add('livebox','warning',"Etat link : $value");
      $this->checkAndUpdateCmd('linkstate', $value);

      $value = $content["data"]["ConnectionState"];
      if($value == "Bound") $value = 1; else $value = 0;
      $this->checkAndUpdateCmd('connectionstate', $value);

      $value = $content["data"]["IPAddress"];
      $this->checkAndUpdateCmd('ipwan', $value);

      $value = $content["data"]["IPv6Address"];
      $this->checkAndUpdateCmd('ipv6wan', $value);

      if ( $content["data"]["LinkType"] == "dsl" || $content["data"]["LinkType"] == "vdsl")
      { $content = $this->getPage("dsl");
        if ( $content !== false )
        {
          $value = $content["status"]["dsl"]["dsl0"]["UpstreamCurrRate"];
          $this->checkAndUpdateCmd('debitmontant', $value);

          $value = $content["status"]["dsl"]["dsl0"]["DownstreamCurrRate"];
          $this->checkAndUpdateCmd('debitdescendant', $value);
          
          $value = $content["status"]["dsl"]["dsl0"]["UpstreamNoiseMargin"]/10;
          $this->checkAndUpdateCmd('margebruitmontant', $value);
          
          $value = $content["status"]["dsl"]["dsl0"]["DownstreamNoiseMargin"]/10;
          $this->checkAndUpdateCmd('margebruitdescendant', $value);
          
          $value = $content["status"]["dsl"]["dsl0"]["LastChange"];
          $this->checkAndUpdateCmd('lastchange', $value);
        }
      }

      $content = $this->getPage("voip");
      if ( $content !== false )
      { foreach ( $content["status"] as $voip )
        { if ( ! isset($voip["signalingProtocol"]) )
            $voip["signalingProtocol"] = strstr($voip["name"], "-", true);

          $value = $voip["trunk_lines"]["0"]["status"];
          if($value == "Up") $value = 1; else $value = 0;
          $this->checkAndUpdateCmd('voipstatus'.$voip["signalingProtocol"], $value);

          $value = $voip["trunk_lines"]["0"]["directoryNumber"];
          $this->checkAndUpdateCmd('numerotelephone'.$voip["signalingProtocol"], $value);
        }
      }
    }
/*  Etat TV ne fonctionne pas
 */
    $content = $this->getPage("tv");
    if ( $content !== false ) {
      $value = $content["data"]["IPTVStatus"];
      if($value == "Bound") $value = 1; else $value = 0;
      $this->checkAndUpdateCmd('tvstatus', $value);
    }

    $content = $this->getPage("wifilist");
    if ( $content !== false ) {
      if ( count($content["status"]) == 1 ) {
        $content = $this->getPage("wifi");
        if ( $content !== false ) {
          $value = $content["status"]["wlanvap"]["wl0"]["VAPStatus"];
          $this->checkAndUpdateCmd('wifistatus', $value);
        }
      } elseif ( count($content["status"]) == 2 ) {
        $content = $this->getPage("wifi");
        if ( $content !== false ) {
          $value = $content["status"]["wlanvap"]["wl0"]["VAPStatus"];
          if($value == "Up") $value = 1; else $value = 0;
          $this->checkAndUpdateCmd('wifi2.4status', $value);

          $value = $content["status"]["wlanvap"]["wl1"]["VAPStatus"];
          if($value == "Up") $value = 1; else $value = 0;
          $this->checkAndUpdateCmd('wifi5status', $value);
        }
      }
    }

    $content = $this->getPage("listcalls");
    if ( $content !== false )
    { $list_callsNbr = "";
      $list_callsOUT = "";
      $list_callsMissed = "";
      $list_callsIN = "";
      $calls_total_nbr = 0;
      $calls_out_nbr = 0;
      $calls_in_nbr = 0;
      $calls_missed_nbr = 0;
      $calls = array();
      if ( isset($content["status"]) )
      { foreach ( $content["status"] as $call )
        { $calls_total_nbr++;
          $Call_numero = $call["remoteNumber"];
          $Call_duree = $call["duration"];
          $ts = strtotime($call["startTime"]);
          // log::add('livebox','warning',$call["startTime"]." ==> ".date("Y-m-d H:i:s",$ts));
              // Appel entrant
          if ( $call["callDestination"] == "local" )
          { $in = 1;
              // Appel manqu�
            if($call["callType"] == "missed") { $calls_missed_nbr++; $missed = 1; }
            else if($call["callType"] == "succeeded") { $missed = 0; $calls_in_nbr++; }
            else $missed = -1;
          }
            // Appel sortant
          else if($call["callOrigin"] == "local") { $calls_out_nbr++; $in = 0; }
          $calls[] = array("timestamp" => $ts,"num" => $Call_numero, "duree" => $Call_duree,"in" => $in,"missed" => $missed);
        }
        if(count($calls) > 1) arsort($calls);
      }
      $logicID = 'calls_missed_nbr';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $display = "<i class=\"icon techno-phone69\" style=\"font-size : 24px\"></i>";
      $eqLogic_cmd = $this->getCmdOk($logicID,'Nbr appels manqués','info:numeric',$display,"line");
      $this->checkAndUpdateCmd($logicID, $calls_missed_nbr);
      
      $logicID = 'calls_in_nbr';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $display = "<i class=\"icon techno-phone3\" style=\"font-size : 24px\"></i>";
      $eqLogic_cmd = $this->getCmdOk($logicID,'Nbr appels entrants','info:numeric',$display,"line");
      $this->checkAndUpdateCmd($logicID, $calls_in_nbr);

      $logicID = 'calls_out_nbr';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $display = "<i class=\"icon techno-phone2\" style=\"font-size : 24px\"></i>";
      $eqLogic_cmd = $this->getCmdOk($logicID,'Nbr appels sortants','info:numeric',$display,"line");
      $this->checkAndUpdateCmd($logicID, $calls_out_nbr);

      $logicID = 'calls_total_nbr';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $display = "<i class=\"icon techno-phone25\" style=\"font-size : 24px\"></i>";
      $eqLogic_cmd = $this->getCmdOk($logicID,'Total nbr appels','info:numeric',$display,"line");
      $this->checkAndUpdateCmd($logicID, $calls_total_nbr);

        // Nombre appels dans une table
      $tabstyle = "<style> th, td { padding-left:3px;padding-right:3px; } </style><style> th { text-align:center; } </style><style> td { text-align:right; } </style>";
      $calls_nbrTab = "$tabstyle<table border=1>";
      $calls_nbrTab .= "<tr><th><i class=\"icon techno-phone25\" style=\"font-size : 24px\"></i>Total</th><th><i class=\"icon techno-phone69\" style=\"font-size : 24px\"></i>Manqués</th><th><i class=\"icon techno-phone2\" style=\"font-size : 24px\"></i>Sortants</th><th><i class=\"icon techno-phone3\" style=\"font-size : 24px\"></i>Entrants</th></tr>";
      $calls_nbrTab .= "<tr><td>$calls_total_nbr</td><td>$calls_missed_nbr</td><td>$calls_out_nbr</td><td>$calls_in_nbr</td></tr>";
      $calls_nbrTab .= "</table>";
      $logicID = 'calls_nbrTab';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $eqLogic_cmd = $this->getCmdOk($logicID,'Nbre appels table','info:string');
      $this->checkAndUpdateCmd($logicID, $calls_nbrTab);

           //  Appels sortants
      $calls_listOUT = "$tabstyle<table border=1>";
      $calls_listOUT .= "<tr><th>Numéro</th><th>Date</th><th>Durée</th></tr>";
      foreach($calls as $call)
      { if($call["in"] == 0)
          $calls_listOUT .= "<tr><td>".$this->fmt_numtel($call["num"])."</td><td>".$this->fmt_date($call["timestamp"])."</td><td>".$this->fmt_duree($call["duree"])."</td></tr>";
      }
      $calls_listOUT .= "</table>";
      $logicID = 'calls_listOUT';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $eqLogic_cmd = $this->getCmdOk($logicID,'Liste appels sortants','info:string');
      $this->checkAndUpdateCmd($logicID, $calls_listOUT);

        // Appels manqués
      $calls_listMissed =  "$tabstyle<table border=1>";
      $calls_listMissed .=  "<tr><th>Numéro</th><th>Date</th></tr>";
      foreach($calls as $call)
      { if($call["missed"] == 1)
          $calls_listMissed .=  "<tr><td>".$this->fmt_numtel($call["num"])."</td><td>".$this->fmt_date($call["timestamp"])."</td></tr>";
      }
      $calls_listMissed .=  "</table>";
      $logicID = 'calls_listMissed';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $eqLogic_cmd = $this->getCmdOk($logicID,'Liste appels manqués','info:string');
      $this->checkAndUpdateCmd($logicID, $calls_listMissed);

        // Appels recus
      $calls_listIN = "$tabstyle<table border=1>";
      $calls_listIN .= "<tr><th>Numéro</th><th>Date</th><th>Durée</th></tr>";
      foreach($calls as $call)
      { if($call["in"] == 1 && $call["missed"] == 0)
        { $calls_listIN .= "<tr><td>".$this->fmt_numtel($call["num"])."</td><td>".$this->fmt_date($call["timestamp"])."</td><td>".$this->fmt_duree($call["duree"])."</td></tr>";
        }
      }
      $calls_listIN .= "</table>";
      $logicID = 'calls_listIN';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $eqLogic_cmd = $this->getCmdOk($logicID,'Liste appels entrants','info:string');
      $this->checkAndUpdateCmd($logicID, $calls_listIN);
    }

    $content = $this->getPage("devicelist");
    if ( $content !== false )
    { $devicelist = array();
      $dlistTab = ""; $dlist="";
      if ( isset($content["status"]) )
      { foreach ( $content["status"] as $equipement )
        { if ( $equipement["Active"] && $equipement["IPAddressSource"] == "DHCP")
          { $devicelist[] = array("Name" => $equipement["Name"], "DeviceType" => $equipement["DeviceType"], "Active" => $equipement["Active"], "Divers" => $equipement["IPAddress"]);
          }
        }
        if ( count($devicelist) > 1) sort($devicelist);
        $dlistTab = "<table border=1><tr><th>Nom</th><th>Type</th><th>Actif</th><th>IPAddress</th></tr>";
        foreach ( $devicelist as $dl )
          $dlistTab .= "<tr><td>".$dl["Name"]."</td><td>".$dl["DeviceType"]."</td><td>".$dl["Active"]."</td><td>".$dl["Divers"]."</td></tr>";
        $dlistTab .= "</table>";
        $dlist = implode(', ',array_column($devicelist,"Name"));
      }

      $logicID = 'devicelist';
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $eqLogic_cmd = $this->getCmdOk($logicID,'Liste des équipements','info:string');
      $this->checkAndUpdateCmd($logicID, $dlist);
      $logicID = 'devicelistTab';
      $eqLogic_cmd = $this->getCmd(null, $logicID);
      $this->_cmdUnite = ''; $this->_cmdHisto = 0;
      $eqLogic_cmd = $this->getCmdOk($logicID,'Liste des équipements table','info:string');
      $this->checkAndUpdateCmd($logicID, $dlistTab);
    }
    $eqLogic_cmd = $this->getCmd(null, 'updatetime');
    $datemaj=strftime("%A %e %B %Y %T",time());
    $eqLogic_cmd->event($datemaj);
  }
  
  function fmt_date($timeStamp)
  { return(strftime("%a %d/%m %T",$timeStamp));
  }
  function fmt_duree($duree)
  { $h = floor(((float)$duree)/3600); $m = floor(((float)$duree)/60); $s = $duree%60;
    $fmt = '';
    if($h>0) $fmt .= $h.'h ';
    if($m>0) $fmt .= $m.'mn ';
    $fmt .= $s.'s';
    return($fmt);
  }
  function fmt_numtel($num)
  { if(is_numeric($num))
    { if(strlen($num) == 12 && substr($num,0,3) == '033') $num = '0' . substr($num,3);
      if(strlen($num) == 10)
      { $fmt = substr($num,0,2) .' '.substr($num,2,2) .' '.substr($num,4,2) .' '.substr($num,6,2) .' '.substr($num,8);
        return("<a target=_blank href=\"https://www.pagesjaunes.fr/annuaireinverse/recherche?quoiqui=".$num."&proximite=0\">".$fmt."</a>");
      }
      else
        return("<a target=_blank href=\"https://www.pagesjaunes.fr/annuaireinverse/recherche?quoiqui=".$num."&proximite=0\">".$num."</a>");
    }
    else return($num);
  }
}

class liveboxCmd extends cmd 
{
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*     * **********************Getteur Setteur*************************** */
    public function execute($_options = null) {
    $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
            throw new Exception(__('Equipement desactivé impossible d\'éxecuter la commande : ' . $this->getHumanName(), __FILE__));
        }
    log::add('livebox','debug','get '.$this->getLogicalId());
    $option = array();
    switch ($this->getLogicalId()) {
      case "reset":
        $page = null;
        break;
      case "reboot":
        $page = "reboot";
        break;
      case "ring":
        $page = "ring";
        break;
      case "wpspushbutton":
        $page = "wpspushbutton";
        break;
      case "wifi2.4on":
      case "wifion":
        $option = array("0", "true");
        $page = "changewifi";
        break;
      case "wifi2.4off":
      case "wifioff":
        $option = array("0", "false");
        $page = "changewifi";
        break;
      case "wifi5on":
        $option = array("1", "true");
        $page = "changewifi";
        break;
      case "wifi5off":
        $option = array("1", "false");
        $page = "changewifi";
        break;
    }
    if ( $page != null ) {
      $eqLogic->getCookiesInfo();
      $content = $eqLogic->getPage($page, $option);
      if ( $this->getLogicalId() != "reboot" ) {
        $eqLogic->refreshInfo();
        $eqLogic->logOut();
      }
      if ( $this->getLogicalId() != "ring" ) {
        $eqLogic->refreshInfo();
        $eqLogic->logOut();
      }
    } else {
            throw new Exception(__('Commande non implémentée actuellement', __FILE__));
    }
        return true;
    }

}
?>
