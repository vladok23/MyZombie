<?php

namespace MyZombie;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\Position;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\tile\Tile;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\entity\Zombie;
use pocketmine\level\format\mcregion\Chunk;
use pocketmine\level\format\FullChunk;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\LevelEventPacket;
use pocketmine\block\Block;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\SetEntityMotionPacket;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\protocol\AddMobPacket;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\RemovePlayerPacket;


class MyZombie extends PluginBase implements Listener{
	private $zombie;
	
	public function onEnable(){ 
		$this->getLogger()->info("MyZombie Is Loading!");
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieRandomWalkCalc" 
		] ), 20 );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieRandomWalk" 
		] ), 1 );
		/*$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieGenerate" 
		] ), 100 );
		*/
		/*$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieFire" 
		] ), 20 );
		*/
		$this->getLogger()->info("Loaded!!!!!");
	}

	public function ZombieRandomWalkCalc() {
		foreach ($this->getServer()->getOnlinePlayers () as $p) {
		if( $p instanceof Zombie){
		}else{
		if( $p instanceof Villager){
		}else{
			foreach ($p->getLevel()->getEntities() as $zo ){
		if( $zo instanceof Zombie){	
		if(!isset($this->zombie[$zo->getId()])){		
				$this->zombie[$zo->getId()] = array(
				'IsChasing' => 0,
				'motionx' => 0,
				'motiony' => 0,
				'motionz' => 0,
				'hurt' => 10,
				'time'=>10,
				'x' => 0,
				'y' => 0,
				'z' => 0,
				'yup' => 20,
				'up' => 0,
                );
			$zom = &$this->zombie[$zo->getId()];	
			$zom['x'] = $zo->getX();
			$zom['y'] = $zo->getY();
			$zom['z'] = $zo->getZ();			
			}
			$zom = &$this->zombie[$zo->getId()];
			if($zom['IsChasing'] == 0){
				$zom['motionx'] = mt_rand(-1,1);
				$zom['motionz'] = mt_rand(-1,1);
				$zom['yup'] = 20;
			
			for($i1 = 0; $i1 <= 100; $i1 ++){
					$pos = new Vector3 ( $zom['x'] , 100 - $i1 , $zom['z']);
					$block = $p->getLevel()->getBlock ( $pos );
					if ($block->getID () == Item::AIR) {
					}else{
					$yyy = 100 - $i1;
					if(abs($zom['y'] - $yyy) <= 2  ){
					$pos2 = new Vector3 ($zom['x'] , 100 - $i1 , $zom['z']);
					$zom['motiony'] = abs($zom['y'] - $yyy);
					}else{
					break;
					}
		$zo->setPosition($pos2);
		break;
		}
		}
		}
		}
		}
		}
	}
	}
	}
	
	public function ZombieRandomWalk() {
	foreach ($this->getServer ()->getDefaultLevel ()->getEntities() as $zo ){
		if( $zo instanceof Zombie){
		if(isset($this->zombie[$zo->getId()])){		
			$zom = &$this->zombie[$zo->getId()];
			$zom['yup'] = $zom['yup'] -1;
			if($zom['IsChasing'] == 0){
			if($zom['up'] == 1){
				if($zom['yup'] <= 10){
					$pk3 = new SetEntityMotionPacket;
					$pk3->entities = [
					[$zo->getID(), $zom['motionx']/20,  $zom['motiony']/20 , $zom['motionz']/20]
					];
						foreach(Server::getInstance()->getOnlinePlayers() as $pl){
						$pl->dataPacket($pk3);
						}
				}else{
				$pk3 = new SetEntityMotionPacket;
				$pk3->entities = [
				[$zo->getID(), $zom['motionx']/20,  -$zom['motiony']/20 , $zom['motionz']/20]
				];
					foreach(Server::getInstance()->getOnlinePlayers() as $pl){
					$pl->dataPacket($pk3);
					}
				}
			}else{
				
				$pk3 = new SetEntityMotionPacket;
				$pk3->entities = [
				[$zo->getID(), $zom['motionx']/20,  -$zom['motiony']/20 , $zom['motionz']/20]
				];
					foreach(Server::getInstance()->getOnlinePlayers() as $pl){
					$pl->dataPacket($pk3);
					}
			}
			}
			}
			}
			}
			}
			
	
	
	
	
	
	
	
	public function ZombieFire() {
	foreach ($this->getServer()->getOnlinePlayers () as $p) {
		if( $p instanceof Zombie){
		}else{
		if( $p instanceof Villager){
		}else{
			foreach ($p->getLevel()->getEntities() as $zo ){
			if( $zo instanceof Zombie){	
			//var_dump($p->getLevel()->getTime());
			if( 0 < $p->getLevel()->getTime() and $p->getLevel()->getTime() < 14000 ){
			$zo->setOnFire(1);
			}
			}
			}
			}
		}
		}}
	
	public function ZombieGenerate() {
		}
		
		
	
	public function onDisable(){
		$this->getLogger()->info("MyZombie Unload Success!");
	}
	
}
