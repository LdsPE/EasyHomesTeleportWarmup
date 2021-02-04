<?php
declare(strict_types = 1);

namespace JackMD\EasyHomesTeleportWarmup;

use JackMD\EasyHomes\event\events\PlayerTeleportHomeEvent;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class EasyHomesTeleportWarmup extends PluginBase implements Listener{

	/**
	 * @var array
	 *
	 * key => player lower case name
	 *
	 * 0 => $block
	 */
	private $move = [];
	/**
	 * @var array
	 *
	 * key => player lower case name
	 *
	 * 0 => $player
	 * 1 => $homeLocation
	 * 2 => $warmupTime
	 */
	private $warmup = [];

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new WarmupTask($this), 20);
	}

	public function onHomeTeleport(PlayerTeleportHomeEvent $event){
		if($event->isExecutedByAdmin()){
			return;
		}

		$player = $event->getPlayer();

		if($player->hasPermission("eh_warmup.bypass")){
			return;
		}

		$event->setCancelled();

		$config = $this->getConfig();
		$warmupTime = $config->get("warmup-time", 5);
		$homeLocation = $event->getHomeLocation();

		$this->move[$player->getLowerCaseName()] = $player->getLevel()->getBlock($player->subtract(0, 1, 0));
		$this->warmup[$player->getLowerCaseName()] = [
			$player,
			$homeLocation,
			time() + $warmupTime
		];

		$player->sendMessage($config->getNested("stand-still", "Please stand still while you are being teleported."));

		if((bool) $config->getNested("add.effect", true)){
			$player->addEffect(new EffectInstance(Effect::getEffect(Effect::NAUSEA), $warmupTime * 40, 10, false));
		}
	}

	public function onQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();

		if($player->hasEffect(Effect::NAUSEA)){
			$player->removeEffect(Effect::NAUSEA);
		}

		unset($this->warmup[$player->getLowerCaseName()]);
	}

	public function onMove(PlayerMoveEvent $event){
		$player = $event->getPlayer();
		$to = $event->getTo();

		if(!isset($this->warmup[$player->getLowerCaseName()])){
			return;
		}

		$move = $this->move[$player->getLowerCaseName()];
		$block = $player->getLevel()->getBlock($to->subtract(0, 1, 0));
		if($move != $block){
			unset($this->move[$player->getLowerCaseName()]);
			unset($this->warmup[$player->getLowerCaseName()]);

			if($player->hasEffect(Effect::NAUSEA)){
				$player->removeEffect(Effect::NAUSEA);
			}

			$player->sendMessage($this->getConfig()->getNested("you-moved", "Teleporting cancelled since you moved."));
		}
	}

	public function getWarmups(): array{
		return $this->warmup;
	}

	public function hasWarmup(Player $player): bool{
		return isset($this->warmup[$player->getLowerCaseName()]);
	}

	public function removeWarmup(Player $player): void{
		unset($this->move[$player->getLowerCaseName()]);
		unset($this->warmup[$player->getLowerCaseName()]);
	}
}
