<?php

namespace chatpermitter;

/* Base */
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\Player;

/* Event */
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\server\DataPacketReceiveEvent;

/* scheduler */
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\CallbackTask;

/* utils */
use pocketmine\utils\Config;

/* packet */
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class Main extends PluginBase implements Listener {

	public function onEnable() {

		Server::Instance()->getLogget()->info("[ChatPermitter] > §a読み込み中...");
		Server::Instance()->getPluginManager()->registerEvents($this, $this);

		if(!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder(), 0744, true);

		$def_chaturl = "https://mirm.info/viewkey.php";
		$def_deleteurl = "https://mirm.info/chat/removekey.php";
		$def_deleteday = 3;
		$configenable = false;

		global $config_pl, $chaturl, $delete_url, $delete_day, $chatplayers;

		$config_pl = new Config($this->getDataFolder() . "config.yml", Config::YAML,
			array(
				"player名" => "日付",
				"@config_enable" => false,
				"@chaturl" => $def_chaturl,
				"@deleteurl" => $def_deleteurl,
				"@deleteday" => $def_deleteday
			));

		if($config_pl->exists("@config_enable")){
			if($config_pl->get("@config_enable" === false)){
				$configenable = false;
			}else{
				$configenable = true;
			}
		}else{
			$configenable = false;
		}

		$chaturl = $configenable ? $config_pl->get("@chaturl") : $def_chaturl;
		$delete_url = $configenable ? $config_pl->get("@deleteurl") : $def_deleteurl;
		$delete_day = $configenable ? $config_pl->get("@deleteday") : $def_deleteday;
		$chatplayers = array();

	}

	public function onJoin(PlayerJoinEvent $event) {

		global $config_pl, $chatplayers, $delete_day;

		$name = $event->getPlayer()->getName();

		if($config_pl->exists($name)){
			if(time() - $config_pl->get($name) <= $delete_day * 3600 * 24){
				$chatplayers[$name] = true;
			}else{
				$chatplayers[$name] = false;
			}
		}else{
			$chatplayers[$name] = false;
		}
	}

	public function onChat(PlayerChatEvent $event) {

		global $chatplayers, $chaturl, $delete_url;

		$player = $event->getPlayer();
		$name = $player->getName();

		if(!$chatplayers[$name]){
			$event->setCancelled(true);
			Server::Instance()->getLogger()->info($event->getMessage());
			$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "mirm"], [$player]), 1); 
		}
	}

	public function onCmd(PlayerCommandPreprocessEvent $event) {

		global $chaturl, $chatplayers;

		$player = $event->getPlayer();
		$name = $player->getName();
		$message = $event->getMessage();
		$command = substr($message, 1);
		$args = explode(" ", $command);

		switch($args[0]){

			case "me":

				if(!$chatplayers[$name]){
					$event->setCancelled(true);
					$player->sendMessage("[ChatPermitter] > §cこのコマンドを使うには、". $chaturl ."にアクセスして、そこで得たキーをダイアログに入力してください。");
					$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "mirm"], [$player]), 1);
				}
		}
	}

	public function mirm(Player $player) {

		global $chaturl, $delete_url;

		$data = [
					"type" => "custom_form",
					"title" => "§lMiRmチャット認証",
					"content" => [
						[
							"type" => "label",
							"text" => "§cチャットをするためには認証キーが必要です。\n§c認証キーは{$chaturl}で取得できます。"
						],
						[
							"type" => "input",
							"text" => "認証キー",
							"placeholder" => "key"
						]
					]
				];

		$pk = new ModalFormRequestPacket();

		$pk->formId = 1;
		$pk->formData = json_encode($data);

		$player->dataPacket($player);

	} 

	public function onReceivePacket(DataPacketReceiveEvent $event) {

		global $chaturl, $delete_url;

		$player = $event->getPlayer();
		$name = $player->getName();
		$packet = $event->getPacket();

		if($packet instanceof ModalFormResponsePacket){
			$formid = $packet->formId;
			$formdata = $packet->formData;

			switch($id){

				case 1:

					$Fdata = json_encode($data, true);

					if($Fdata === null){

						break;

					}elseif($Fdata[1] === ""){

						$player->sendMessage("[ChatPermitter] > §c認証キーを入力してください！");
						$this->mirm($player);

						break;

					}

					$key = preg_replace('/[^a-z]/', '', $Fdata[1]);

					$this->getServer()->getScheduler()->scheduleAsyncTask($job4 = new thread_getdata($key, $name, $delete_url));

					break;
			}
		}
	}
}

class thread_getdata extends AsyncTask {

	public function __construct($key, $player, $delete_url) {

		$this->code = $key;
		$this->player = $player;
		$this->delete_url = $delete_url;

	}

	public function onRun() {

		$re = array();
		$re["playername"] = $this->player;

		$base_url = $this->delete_url;

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, $base_url .'?key='. $this->code);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);

		$response = curl_exec($curl);

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if(is_numeric($response)){
			if($response = 0){
				$re["result"] = false;
			}else{
				$re["result"] = true;
			}
		}else{
			$re["result"] = true;

			echo "Error Occured : ". PHP_EOL;
			echo $response . PHP_EOL;
			echo $code . PHP_EOL;
		}

		curl_close($curl);

		$this->setResult($re);
	}

	public function onCompletion(Server $server) {

		global $chatplayers, $config_pl;

		$re = $this->getResult();
		$pl = $re["playername"];

		if($server->getPlayer($pl) !== null){
			$player = $server->getPlayer($pl);
			$name = $player->getName();
			if($re["result"]){
				$chatplayers[$name] = true;
				if($config_pl->exists($name)){
					$config_pl->remove($name);
				}

				$config_pl->set($name, time());
				$config_pl->save();

				$player->sendMessage("[ChatPermitter] > §aコードが認証されました！");
			}else{
				$player->sendMessage("[ChatPermitter] > §cコードが間違っているはずです！");
			}
		}
	}
}

class ResultSet {

	public $player, $result;

}