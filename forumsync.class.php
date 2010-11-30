<?php

class ForumSync
{
	static protected $oInstance = null;
	protected $sync_token = 0;
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
			"debug" => false,
		);
		$this->req = new Req();
		$this->req->set(array("host"=>$this->opts["host"],"cookies"=>$_COOKIE,"debug"=>$this->opts["debug"]));
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
			"username"=>$vrs["username"],
			"password"=>$vrs["password"],
			"login"=>1
		);

		if ($vrs["rememberme"]) $vars["autologin"] = "on";

		$this->req	->set(array("protocol"=>"POST","url"=>"/forum/ucp.php?mode=login"))
				->req($vars)
				->get("cookies",$cookies)
				->save($str);

		$this->setcookies($cookies);				
		
		if (preg_match("/<meta[^<>]*http-equiv\s*=\s*[\"']?refresh[^<>]*>/i",$str,$m))
			if (preg_match("/sid=([^<>\"'\s\?&]*)/",$m[0],$mm))
			{
				$this->forum_sid = $mm[1];
				$this->setcookies(array("forum_sid"=>$mm[1]));
			}

		if (preg_match("/Вы успешно вошли/",$str))
			return 1;
		elseif (preg_match("/Вы ввели неверный пароль/",$str))
			throw new Exception(__METHOD__ . ": incorrect password");
		else
			throw new Exception(__METHOD__ . ": unknown error");
	}

	protected function signout($vrs)
	{
		if ($this->forum_sid)
		{
			$this->req->set(array("protocol"=>"GET","url"=>"/forum/ucp.php?mode=logout&sid=" . $this->forum_sid . "&sync_token=" . $this->sync_token))->req();
			$this->forum_sid = 0;
			$this->setcookies(array("forum_sid"=>0));
			return 1;
		}
		else throw new Exception(__METHOD__ . ": forum_sid not found");
	}

	protected function change_password($vrs)
	{
		if ($this->forum_sid)
		{
			$vars = array(
				"sync_token" => $this->sync_token,
				"new_password" => $vrs["new_password"],
				"password_confirm" => $vrs["new_password"],
				"cur_password" => $vrs["old_password"],
				"submit"=>1,
			);

			$this->req	->set(array("protocol"=>"POST","url"=>"/forum/ucp.php?i=profile&mode=reg_details"))
					->req($vars)
					->save($str);

			if (preg_match("/Ваш профиль обновлен/",$str))
				return 1;
			else throw new Exception(__METHOD__ . ": unknown error");
		}
		else throw new Exception(__METHOD__ . ": forum_sid not found");
	}

	protected function change_profile($vrs)
	{
		if ($this->forum_sid)
		{
			$vars = array(
				"sync_token" => $this->sync_token,
				"submit" => 1,
				"occupation" => $vrs["about"],
				"interests" => $vrs["tags_str"],
				"location" => $vrs["geo_str"],
				"bday_day" => $vrs["birth_day"],
				"bday_month" => $vrs["birth_month"],
				"bday_year" => $vrs["birth_year"],
			);
			foreach($vars as $i => $v)
				if (!$v) unset($vars[$i]);

			$this->req	->set(array("protocol"=>"POST","url"=>"/forum/ucp.php?i=profile&mode=profile_info"))
					->req($vars)
					->save($str);

			if (preg_match("/Ваш профиль обновлен/",$str))
				return 1;
			else throw new Exception(__METHOD__ . ": unknown error");
		}
		else throw new Exception(__METHOD__ . ": forum_sid not found");
	}

	protected function register($vrs)
	{
		$vars = array(
			"sync_token" => $this->sync_token,
			"submit" => 1,
			"username"=>$vrs[username],
			"email"=>$vrs[email],
			"email_confirm"=>$vrs[email],
			"new_password"=>$vrs[password],
			"password_confirm"=>$vrs[password],
			"lang"=>"ru",
			"agreed"=>"true",
			"change_lang"=>0,
		);

		$this->req	->set(array("protocol"=>"POST","url"=>"/forum/ucp.php?mode=register"))
				->req($vars)
				->save($str);

		if (preg_match("/<!-- PHPBB_SYNC\[user_id\s*=\s*(\d+)\] -->/",$str,$m))
			return $m[1];
		else throw new Exception(__METHOD__ . ": unknown error");
	}

	protected function signup($vrs)
	{
		$this->opts["setcookie"] = false;

		$user_id = $this->register($vrs);
		if (!$user_id) throw new Exception(__METHOD__ . ": unknown error");
		$this->signin($vrs);
		$this->change_profile($vrs);
		return $user_id;
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

