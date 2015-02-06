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
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\Long;
use pocketmine\nbt\tag\Short;
use pocketmine\nbt\tag\String;
use pocketmine\level\format\mcregion\Chunk;
use pocketmine\level\format\FullChunk;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\LevelEventPacket;
use pocketmine\block\Block;
use pocketmine\network\protocol\AddEntityPacket;


class MyZombie extends PluginBase implements Listener{
	private $nbt;
	private $eid,$config,$CC,$EntityType;
	private $zombie;
	
	public function onEnable(){ 
	$this->EntityType = 32;
	$this->eid = 100000;
		$this->getLogger()->info("MyZombie Is Loading!");
		$this->nbt = new Compound(\false, [
				new Enum("pos", [
					new Double(0,0),
					new Double(1,0),
					new Double(2,0)
					]),
				new Enum("Motion", [
					new Double(0, 0.0),
					new Double(1, 0.0),
					new Double(2, 0.0)
					]),
				new Enum("Rotation",[
					new Float(0,0),
					new Float(1,0)
					]),
				]);	
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieScan" 
		] ), 3.5 );
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

	public function ZombieScan() {
		foreach ($this->getServer()->getOnlinePlayers () as $p) {
		if( $p instanceof Zombie){
		}else{
		if( $p instanceof Villager){
		}else{
			foreach ($p->getLevel()->getEntities() as $zo ){
		if( $zo instanceof Zombie){	
		
		
		
		if(!isset($this->zombie[$zo->getId()])){		
				$this->zombie[$zo->getId()] = array(
				'left' => 0,
				'front' => 0,
				'hurt' => 10,
				'time'=>6,
                );
			}
			$pos = new Vector3 ( $zo->getX(), $zo->getY(), $zo->getZ());
			$zom = &$this->zombie[$zo->getId()];
			
			if( $p->distance($pos) <= 6){  //玩家仇恨模式
				$zom['left'] = 0;
				$zom['front'] = 0;
				$zom['time'] = 0;
				$x1 =$zo->getX () - $p->getX();
				$zx =floor($zo->getX());
				$zY =floor($zo->getY());
				$zZ = floor($zo->getZ());
				$xxx = 0.17;
				$zzz = 0.17;
				//$jumpy = $zo->getY() - 1;
				
				if($x1 > -0.5 and $x1 < 0.5) { //直行
					$zx = $zo->getX();
					$xxx = 0;
					$jumpyX = $zo->getY();
				}
				elseif($x1 < 0){
					$jy = $this->ifjump($p->getLevel(), new Vector3 ($zo->getX()+0.17, $zo->getY()-1,$zo->getZ()));
					if($jy !== false) {
						$zx = $zo->getX() +0.17;
						$xxx =0.17;
					}
					else {
						$zx = $zo->getX();
						$xxx = 0;
					}
					$jumpyX = $jy;
				}else{
					$jy = $this->ifjump($p->getLevel(), new Vector3 ($zo->getX()-0.17, $zo->getY()-1,$zo->getZ()));
					if($jy !== false) {
						$zx = $zo->getX() -0.17;
						$xxx = -0.17;
					}
					else {
						$zx = $zo->getX();
						$xxx = 0;
					}
					$jumpyX = $jy;
				}
				
				$z1 =$zo->getZ () - $p->getZ() ;
				if($z1 > -0.5 and $z1 < 0.5) { //直行
					$zZ = $zo->getZ();
					$zzz = 0;
					$jumpyZ = $zo->getY();
				}					
				elseif($z1 <0){
				 $jy = $this->ifjump($p->getLevel(), new Vector3 ($zo->getX(), $zo->getY()-1,$zo->getZ()+0.17));
					if($jy !== false) {
						$zZ = $zo->getZ() +0.17;
						$zzz =0.17;
					}
					else {
						$zZ = $zo->getZ();
						$zzz = 0;
					}
					$jumpyZ = $jy;
				}else{
					$jy = $this->ifjump($p->getLevel(), new Vector3 ($zo->getX(), $zo->getY()-1,$zo->getZ()-0.17));
					if($jy !== false) {
						$zZ = $zo->getZ() -0.17;
						$zzz =-0.17;
					}
					else {
						$zZ = $zo->getZ();
						$zzz = 0;
					}
					$jumpyZ = $jy;
				}
				//$pos3 = new Vector3 ($zx, $p->getY(),$zZ);
				//boybook 的判断Y轴方法
				if($jumpyX === false or $jumpyZ === false) {
					$zy = $zo->getY() - 1;
				}
				else {
					if ($jumpyX !== false) {
						$zy = $jumpyX;
					}
					else {
						$zy = $jumpyZ;
					}
				//$block = $p->getLevel()->getBlock ( $pos3 );
				//var_dump($block->getID ());
				//if ($block->getID () == 0) {
					$pos2 = new Vector3 ($zx, $zy, $zZ);
				//}else{
				//	$pos2 = new Vector3 ($zx, $zo->getY() - 1,$zZ);
				//}
	    $pos4= new Vector3 ($xxx, 0.5 ,$zzz);
		  	//$zo->setMotion($pos4);
				//$zo->teleport($pos2);
				$zo->setPosition($pos2);
				//$zo->move(0.1,0.1,0.1);
				//$zo->hasLineOfSight ($p);
				if(0 < $p->distance($pos) and $p->distance($pos) <= 1.5){
					//$p->attack (1, $zo);
					if($zom['hurt'] >= 0){
						$zom['hurt'] = $zom['hurt'] -1 ;
					}else{
						//$p->setHealth($p->getHealth() - 2 );
						$p->attack(2);
						$zom['hurt'] = 6 ;
					}
				}
		 }
		
		}else{  //自由行走模式
		
		
		if($zom['time'] >= 0){
		$zom['time'] = $zom['time'] -1 ;

		//旧的算法
		
		if($zom['left'] <= -5){
		$zx =$zo->getX();
		$zom['front'] = rand(-5,15);
		}
		if($zom['left'] >-5 and $zom['left'] < 5){
		$zx =$zo->getX()+ 0.15;
		}
		if($zom['left'] >= 5){
		$zx =$zo->getX()- 0.15;
		}
		
		if($zom['front'] <= -5){
		$zz =$zo->getZ();
		}
		if($zom['front'] >-5 and $zom['front'] < 5){
		$zz =$zo->getZ()+ 0.15;
		}
		if($zom['front'] >= 5){
		$zz =$zo->getZ()- 0.15;
		}
		
		for($i1 = 0; $i1 <= 100; $i1 ++){
					$pos = new Vector3 ( $zx , 100 - $i1 , $zz);
					$block = $p->getLevel()->getBlock ( $pos );
					if ($block->getID () == Item::AIR) {
					}else{
					//var_dump($block->getID ());
					$yyy = 100 - $i1;
					if($zo->getY() - $yyy <= 2 and $yyy - $zo->getY() <= 2 ){
					$pos2 = new Vector3 ($zx ,100 - $i1 , $zz );
					}else{
					$zom['time'] = -1;
					break;
					}
		    //$zo->setMotion($pos2);
			//$zo->teleport($pos2);
		 	//$zY =$zo->getY();   
			$zo->setPosition($pos2);
		$zo->move(0.1,0.1,0.1);
		break;
		//$zo->move(0.1,0.1,0.1);
					}
				}
		}else{
		$zom['left'] = rand(-15,15);
		$zom['front'] = rand(-15,15);
		$zom['time'] = 7;
			}
		}
		}
		}
		}
		}
		}
		}
		
		
	public function ifjump($level, $v3) {
		$x = floor($v3->getX());
		$y = floor($v3->getY());
		$z = floor($v3->getZ());
		
		if($level->getBlock(new Vector3($x,$y,$z))->getID() == 0) {
			if($level->getBlock(new Vector3($x,$y-1,$z))->getID() != 0) {
				return $y;  //向前走
			}
			else {
				if($level->getBlock(new Vector3($x,$y-2,$z))->getID() != 0) {
				 return $y-1;  //向下跳
				}
				else { //前方悬崖
					return false;
				}
			}
		}
		else {  //考虑向上
		 if($level->getBlock(new Vector3($x,$y+1,$z))->getID() != 0) {  //前方是面墙
		 		return false;
		 	}
		 	else {
		 	 return $y+1;  //向上跳
		 	}
		}
	}
	
	/*public function spawnTo(Player $player, $pos){
		$pk = new AddEntityPacket();
		$pk->type = $this->EntityType;
		$pk->eid = $this->eid++;
		$pk->x = $pos->getX();;
		$pk->y = $pos->getY();
		$pk->z = $pos->getZ();
		$pk->did = 0;
		$player->dataPacket($pk);
	}
	*/
	
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
		}
		}
	
	
	
	public function ZombieGenerate() {
	foreach ( $this->getServer ()->getOnlinePlayers () as $p ) {
			for($i1 = - 1; $i1 <= 10; $i1 ++)
				for($b1 = - 1; $b1 <= 10; $b1 ++) {
					$pos = new Vector3 ( $p->x + $i1, $p->y, $p->z + $b1 );
					$block = $this->getServer ()->getDefaultLevel ()->getBlock ( $pos );
					if ($block->getID () == Item::GLOWSTONE  or $block->getID () == Item::TORCH or $block->getID () == Item::FIRE ) {
					var_dump($block->getID ());
					var_dump($p->distance($pos));
				}else{
				$pos = $p->getPosition();
				//$this->spawnTo($p, $pos);
				
				
				/*
				//$zom=new Eneity ($xxx, 1 ,$zzz);
				$pos = $p->getPosition();
				$chunk = $p->getLevel()->getChunk($pos->x -20, $pos->z -20, true);
				//var_dump($chunk);
						$Zombie = new Zombie($chunk,$this->nbt);
					
					$x = rand(0,32) + $pos->x -10;
					$y = rand(5,15) + $pos->y;
					$z = rand(0,32) + $pos->z -10;
					$pos = new Vect or3($x,$y,$z);
					$Zombie->setPosition($pos);
					$Zombie->spawnToAll();
				//var_dump($p->getLevel()->getEntities());
				//$p->getLevel()->addEntity("Zombie");
				*/
				}
		}
		}
		}
		
		
		
		
	
	public function onDisable(){
		$this->getLogger()->info("MyZombie Unload Success!");
	}
	
}

