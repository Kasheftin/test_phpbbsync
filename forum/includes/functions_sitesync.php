<?php


require_once(dirname(__FILE__) . "/../../classes/req/req.class.php");
require_once(dirname(__FILE__) . "/../../classes/req/reqexception.class.php");


class SiteSync
{
	static protected $oInstance = null;
	protected $sync_token = 0;
	protected $site_sid = 0;
	protected $forum_sid = 0;
	protected $opts = array();
	protected $req = null;

	static public function getInstance()
	{
		if (isset(self::$oInstance) && (self::$oInstance instanceof self)) {
			return self::$oInstance;
		}
		else {
			self::$oInstance = new self();
			return self::$oInstance;
		}
	}
	public function __clone() { }

	protected function __construct() 
	{ 
		$this->opts = array(
			"host" => "www.obzor.lt",
			"setcookie" => true,
			"setcookie_host" => ".obzor.lt",
			"setcookie_int" => time() + 86400*365,
			"debug" => false
		);

		$this->req = new Req();
		$this->req->set(array("host"=>$this->opts["host"],"cookies"=>$_COOKIE,"debug"=>$this->opts["debug"]));
		if (isset($_COOKIE["SID"]))
			$this->site_sid = $_COOKIE["SID"];
		if (isset($_COOKIE["forum_sid"]))
			$this->forum_sid = $_COOKIE["forum_sid"];
		$this->sync_token = md5("kasheftin" . date("Ymd"));
	}

	static public function checkToken()
	{
		$o = self::getInstance();
		if (isset($_REQUEST["sync_token"]) && $_REQUEST["sync_token"] == $o->sync_token)
			return 1;
		return 0;
	}
		    
	static public function sync($action,$vrs=array())
	{
		$o = self::getInstance();

		if (isset($_REQUEST["sync_token"]) && $_REQUEST["sync_token"] == $o->sync_token)
			return 1;

		if (method_exists($o,$action))
			return $o->$action($vrs);
	}

	protected function signin($vrs)
	{
		$vars = array(
			"sync_token"=>$this->sync_token,
			"action"=>"signin",
			"formData[auth_username]"=>$vrs["username"],
			"formData[auth_password]"=>$vrs["password"],
		);

		if ($vrs["autologin"]) $vars["formData[rememberme]"] = "1";

		$this->req	->set(array("protocol"=>"POST","url"=>"/profile/signin/"))
				->req($vars)
				->get("cookies",$cookies)
				->save($str);

		$this->setcookies($cookies);				

		if (isset($cookies["SID"]))
		{
			$this->site_sid = $cookies["SID"];

			if (isset($vrs["redirect"]))
			{
				if (preg_match("/sid=([^<>\"'\s\?&]*)/",$vrs["redirect"],$mm))
				{
					$this->forum_sid = $mm[1];
					$this->setcookies(array("forum_sid"=>$mm[1]));
				}
			}

			return 1;
		}
		else
			throw new Exception(__METHOD__ . ": unknown error");
	}

	protected function signout($vrs)
	{
		if ($this->site_sid)
		{
			$this->req->set(array("protocol"=>"GET","url"=>"/profile/exit/?sync_token=" . $this->sync_token))->req();
			$this->site_sid = 0;
			$this->setcookies(array("SID"=>0));
			return 1;
		}
		else throw new Exception(__METHOD__ . ": site_sid not found");
	}

	protected function change_profile($vrs)
	{
		if ($this->site_sid)
		{
			$vars = array(
				"sync_token" => $this->sync_token,
				"mode" => "main_form",
				"formData[about]" => $vrs["occupation"],
				"formData[tags_str]" => $vrs["interests"],
				"formData[birth_day]" => $vrs["bday_day"],
				"formData[birth_month]" => $vrs["bday_month"],
				"formData[birth_year]" => $vrs["bday_year"],
			);
			foreach($vars as $i => $v)
				if (!$v) unset($vars[$i]);

			$this->req	->set(array("protocol"=>"POST","url"=>"/actions/profile_save.php"))
					->req($vars)
					->save($str);

			if (preg_match("/success/",$str))
				return 1;
			else throw new Exception(__METHOD__ . ": unknown error: $str");
		}
		else throw new Exception(__METHOD__ . ": site_sid not found");
	}

	protected function change_password($vrs)
	{
		if ($this->site_sid)
		{
			$vars = array(
				"sync_token" => $this->sync_token,
				"mode" => "password_form",
				"formData[new_password]" => $vrs["new_password"],
				"formData[new_password2]" => $vrs["new_password"],
				"formData[old_password]" => $vrs["cur_password"],
				"formData[email]" => $vrs["email"],
				"formData[email_confirm]" => $vrs["email_confirm"],
			);

			$this->req	->set(array("protocol"=>"POST","url"=>"/actions/profile_save.php"))
					->req($vars)
					->save($str);

			if (preg_match("/success/",$str))
				return 1;
			else throw new Exception(__METHOD__ . ": unknown error: $str");
		}
		else throw new Exception(__METHOD__ . ": forum_sid not found");
	}

	protected function setcookies($cookies)
	{
		if ($cookies)
			foreach($cookies as $i => $v)
			{
				if ($this->opts["setcookie"]) setcookie($i,$v,(int)$this->opts["setcookie_int"],"/",$this->opts["setcookie_host"]);
				$_COOKIE[$i] = $v;
			}
	}
}