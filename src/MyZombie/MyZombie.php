<?php

namespace MyZombie;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\entity\Zombie;
use pocketmine\level\format\mcregion\Chunk;
use pocketmine\level\format\FullChunk;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use pocketmine\block\Block;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\SetEntityMotionPacket;
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\String;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

class MyZombie extends PluginBase implements Listener{
	private $zombie = array();
 	public $width = 0.4;  //僵尸宽度
	private $lz;
	private $nbt;
	public $hatred_r = 10;  //仇恨半径
	public $birth = 30;  //僵尸出生间隔秒数
	public $birth_r = 30;  //僵尸出生半径
	
	public function onEnable(){ 
		$this->getLogger()->info("MyZombie Is Loading!");
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieRandomWalkCalc" 
		] ), 10 );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieRandomWalk" 
		] ), 1 );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieYaw"
		] ), 1 );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieGenerate" 
		] ), 20 * $this->birth);
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"ZombieFire" 
		] ), 40);
		
						
		$this->getLogger()->info("MyZombie Loaded !!!!");
	} 

	public function getNBT($v3) {
		$nbt = new Compound("", [
			"Pos" => new Enum("Pos", [
				new Double("", 0),
				new Double("", 0),
				new Double("", 0)
			]),
			"Motion" => new Enum("Motion", [
				new Double("", 0),
				new Double("", 0),
				new Double("", 0)
			]),
			"Rotation" => new Enum("Rotation", [
				new Float("", 0),
				new Float("", 0)
			]),
		]);
		return $nbt;
	}
	
	public function getLight($level,$pos) {//获取亮度
		$chunk = $level->getChunk($pos->x >> 4, $pos->z >> 4, false);
		$l = 0;
		if($chunk instanceof FullChunk){
			$l = $chunk->getBlockSkyLight($pos->x & 0x0f, $pos->y & 0x7f, $pos->z & 0x0f);
			//TODO: decrease light level by time of day
			if($l < 15){
				//$l = \max($chunk->getBlockLight($pos->x & 0x0f, $pos->y & 0x7f, $pos->z & 0x0f));
				$l = $chunk->getBlockLight($pos->x & 0x0f, $pos->y & 0x7f, $pos->z & 0x0f);
			}
		}

		return $l;
	}
	
	public function ZombieYaw() {//转身
		foreach ($this->getServer()->getLevels() as $level) {
		foreach ($level->getEntities() as $zo){
		if($zo instanceof Zombie){
		if(count($zo->getViewers() != 0)) {
			if(isset($this->zombie[$zo->getId()])){	
				$zom = &$this->zombie[$zo->getId()];
				$yaw0 = $zo->yaw;  //实际yaw
				$yaw = $zom['yaw']; //目标yaw
				if (abs($yaw0 + $yaw) <= 180) {  //-180到+180正方向
					if ($yaw0 <= $yaw) {  //实际在目标左边
						if ($yaw - $yaw0 <= 10) {
							$yaw0 = $yaw;
						}
						else {
							$yaw0 += 10;
						}
					}
					else {  ////实际在目标右边
						if ($yaw0 - $yaw <= 10) {
							$yaw0 = $yaw;
						}
						else {
							$yaw0 -= 10;
						}
					}
				}
				else {  ////+180到-180方向
					if ($yaw0 >= $yaw) {  //实际在目标左边
						if ($yaw0 - $yaw <= 5) {
							$yaw0 = $yaw;
						}
						else {
							$yaw0 -= 5;
							if ($yaw0 <= -180) $yaw0 = $yaw0 + 360;
						}
					}
					else {  ////实际在目标右边
						if ($yaw - $yaw0 <= 5) {
							$yaw0 = $yaw;
						}
						else {
							$yaw0 += 5;
							if ($yaw0 >= 180) $yaw0 = $yaw0 - 360;
						}
					}
				}
				$zo->setRotation($yaw0,0);
			}
		}
		}
		}
		}
	}
			
	public function ZombieRandomWalkCalc() {//计算行进路线
		foreach ($this->getServer()->getLevels() as $level) {
		foreach ($level->getEntities() as $zo){
		if($zo instanceof Zombie){
		if(count($zo->getViewers() != 0)) {
			if(!isset($this->zombie[$zo->getId()])){		
				$this->zombie[$zo->getId()] = array(
				'ID' => $zo->getId(),
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
				'yaw' => $zo->yaw,
				'level' => $zo->getLevel()->getName(),
				'xxx' => 0,
				'zzz' => 0,
                );
			$zom = &$this->zombie[$zo->getId()];
			$zom['x'] = $zo->getX();
			$zom['y'] = $zo->getY();
			$zom['z'] = $zo->getZ();
			$this->lz = $zo->getId() + 1;
			$zo->setMaxHealth(15);		
			}
			$zom = &$this->zombie[$zo->getId()];
			
			if ($zom['IsChasing'] == "0") {  //自由行走模式
			
				//限制转动幅度
				$newmx = mt_rand(-5,5)/10;
				while (abs($newmx - $zom['motionx']) >= 0.3) {
			 		$newmx = mt_rand(-5,5)/10;
			 	} 
			 	$zom['motionx'] = $newmx;
			 	
			 	$newmz = mt_rand(-5,5)/10;
				while (abs($newmz - $zom['motionz']) >= 0.3) {
			 		$newmz = mt_rand(-5,5)/10;
			 	} 
			 	$zom['motionz'] = $newmz;
			 	
				//$zom['motionx'] = mt_rand(-10,10)/10;
				//$zom['motionz'] = mt_rand(-10,10)/10;
				$zom['yup'] = 0;
				$zom['up'] = 0;
				
				//boybook的y轴判断法
				$width = $this->width;
				$pos = new Vector3 ($zom['x'] + $zom['motionx'], floor($zo->getY()) + 1,$zom['z'] + $zom['motionz']);  //目标坐标
				$zy = $this->ifjump($zo->getLevel(),$pos);
				if ($zy === false) {  //前方不可前进
					$pos2 = new Vector3 ($zom['x'], $zom['y'] ,$zom['z']);  //目标坐标
					if ($this->ifjump($zo->getLevel(),$pos2) === false) { //原坐标依然是悬空
						$pos2 = new Vector3 ($zom['x'], $zom['y']-2,$zom['z']);  //下降
						$zom['up'] = 1;
						$zom['yup'] = 0;
					}
					else {
						$zom['motionx'] = - $zom['motionx'];
						$zom['motionz'] = - $zom['motionz'];
						//转向180度，向身后走
						$zom['up'] = 0;
					}
				}
				else {
					$pos2 = new Vector3 ($zom['x'] + $zom['motionx'], $zy - 1 ,$zom['z'] + $zom['motionz']);  //目标坐标
					if ($pos2->y - $zom['y'] < 0) {
						$zom['up'] = 1;
					}
					else {
						$zom['up'] = 0;
					}
				}
		
				//转向计算
				$yaw = $this->getyaw($zom['motionx'], $zom['motionz']);
		
				//$zo->setRotation($yaw,0);
				$zom['yaw'] = $yaw;
				
				//更新僵尸坐标
				$zom['x'] = $pos2->getX();
				$zom['z'] = $pos2->getZ();
				$zom['y'] = $pos2->getY();
				$zom['motiony'] = $pos2->getY() - $zo->getY();
				//echo($zo->getY()."\n");
				//var_dump($pos2);
				//var_dump($zom['motiony']);
				$zo->setPosition($pos2);
				//echo "SetPosition \n";
			}
		}
		}
		}
		}
	}
	
	public function getyaw($mx, $mz) {  //根据motion计算转向角度
		//转向计算
		if ($mz == 0) {  //斜率不存在
			if ($mx < 0) {
				$yaw = -90;
			}
			else {
				$yaw = 90;
			}
		}
		else {  //存在斜率
			if ($mx >= 0 and $mz > 0) {  //第一象限
				$atan = atan($mx/$mz);
				$yaw = rad2deg($atan);
			}
			elseif ($mx >= 0 and $mz < 0) {  //第二象限
				$atan = atan($mx/abs($mz));
				$yaw = 180 - rad2deg($atan);
			}
			elseif ($mx < 0 and $mz < 0) {  //第三象限
				$atan = atan($mx/$mz);
				$yaw = -(180 - rad2deg($atan));
			}
			elseif ($mx < 0 and $mz > 0) {  //第四象限
				$atan = atan(abs($mx)/$mz);
				$yaw = -(rad2deg($atan));
			}
		}
		
		$yaw = - $yaw;
		return $yaw;
	}
	
	public function ifjump($level, $v3) {  //boybook Y轴算法核心函数 
		$x = floor($v3->getX());
		$y = floor($v3->getY());
		$z = floor($v3->getZ());
		
		//echo ($y." ");
		if ($this->whatBock($level,new Vector3($x,$y,$z)) == "air") {
			//echo "前方空气 ";
			if ($this->whatBock($level,new Vector3($x,$y-1,$z)) == "block") {  //方块
				//echo "考虑向前 ";
				if ($this->whatBock($level,new Vector3($x,$y+1,$z)) == "block" or $this->whatBock($level,new Vector3($x,$y+1,$z)) == "half") {  //上方一格被堵住了
					//echo "上方卡住 \n";
					return false;  //上方卡住
				}
				else {
					//echo "GO向前走 \n";
					return $y;  //向前走
				}
			}
			elseif ($this->whatBock($level,new Vector3($x,$y-1,$z)) == "water") {  //水
				//echo "下水游泳 \n";
				return $y-1;  //降低一格向前走（下水游泳）
			}
			elseif ($this->whatBock($level,new Vector3($x,$y-1,$z)) == "half") {  //半砖
				//echo "下到半砖 \n";
				return $y-0.5;  //向下跳0.5格
			}
			elseif ($this->whatBock($level,new Vector3($x,$y-1,$z)) == "lava") {  //岩浆
				//echo "前方岩浆 \n";
				return false;  //前方岩浆
			}
			elseif ($this->whatBock($level,new Vector3($x,$y-1,$z)) == "air") {  //空气
				//echo "考虑向下跳 ";
				if ($this->whatBock($level,new Vector3($x,$y-2,$z)) == "block") {
					//echo "GO向下跳 \n";
					return $y-1;  //向下跳
				}
				else { //前方悬崖
					//echo "前方悬崖 \n";
					return false;
				}
			}
		}
		elseif ($this->whatBock($level,new Vector3($x,$y,$z)) == "water") {  //水
			//echo "正在水中";
			if ($this->whatBock($level,new Vector3($x,$y+1,$z)) == "water") {  //上面还是水
				//echo "向上游 \n";
				return $y+1;  //向上游，防溺水
			}
			elseif ($this->whatBock($level,new Vector3($x,$y+1,$z)) == "block" or $this->whatBock($level,new Vector3($x,$y+1,$z)) == "half") {  //上方一格被堵住了
				if ($this->whatBock($level,new Vector3($x,$y-1,$z)) == "block" or $this->whatBock($level,new Vector3($x,$y-1,$z)) == "half") {  //下方一格被也堵住了
					//echo "上下都被卡住 \n";
					return false;  //上下都被卡住
				}
				else {
					//echo "向下游 \n";
					return $y-1;  //向下游，防卡住
				}
			}
			else {
				//echo "游泳ing... \n";
				return $y;  //向前游
			}
		}
		elseif ($this->whatBock($level,new Vector3($x,$y,$z)) == "half") {  //半砖
			//echo "前方半砖 \n";
			if ($this->whatBock($level,new Vector3($x,$y+1,$z)) == "block" or $this->whatBock($level,new Vector3($x,$y+1,$z)) == "half") {  //上方一格被堵住了
				//return false;  //上方卡住
			}
			else {
				return $y+0.5;
			}
			
		}
		elseif ($this->whatBock($level,new Vector3($x,$y,$z)) == "lava") {  //岩浆
			//echo "前方岩浆 \n";
			return false;
		}
		else {  //考虑向上
			//echo "考虑向上 ";
			if ($this->whatBock($level,new Vector3($x,$y+1,$z)) != "air") {  //前方是面墙
				//echo "前方是墙 \n";
		 		return false;
		 	}
		 	else {
				if ($this->whatBock($level,new Vector3($x,$y+2,$z)) == "block" or $this->whatBock($level,new Vector3($x,$y+2,$z)) == "half") {  //上方两格被堵住了
					//echo "2格处被堵 \n";
					return false;
				}
				else {
					//echo "GO向上跳 \n";
					return $y+1;  //向上跳
				}
		 	}
		}
	}
	
	public function whatBock($level, $v3) {  //boybook的y轴判断法 核心 什么方块？
		$block = $level->getBlock($v3);
		$id = $block->getID();
		switch ($id) {
			case 0:
			case 6:
			case 27:
			case 30:
			case 31:
			case 37:
			case 38:
			case 39:
			case 40:
			case 50:
			case 51:
			case 65:
			case 66:
			case 78:
			case 106:
			case 111:
			case 141:
			case 142:
			case 171:
			case 175:
			case 244:
			case 323:
				//透明方块
				return "air";
				break;
			case 8:
			case 9:
				//水
				return "water";
				break;
			case 10:
			case 11:
				//岩浆
				return "lava";
				break;
			case 44:
			case 158:
				//半砖
				return "half";
				break;
			default:
				return "block";
				break;
		}
	}
					
	public function ZombieRandomWalk() {//僵尸运动 Zzm X,Z算法核心函数
	$filter_res = array_filter($this->zombie);
	if(!empty($filter_res)){
	foreach ($this->zombie as $zoms) {
	$level=$this->getServer()->getLevelByName($zoms['level']);
	 $zo = $level->getEntity($zoms['ID']);
	 if($zo != false){
			$zom = &$this->zombie[$zo->getId()];
			$zom['yup'] = $zom['yup'] -1;
			$h_r = $this->hatred_r;  //仇恨半径
			$pos = new Vector3($zo->getX(), $zo->getY(), $zo->getZ());
			$hatred = false;
			foreach($zo->getViewers() as $p) {  //获取附近玩家
				if($p->distance($pos) <= $h_r){  //玩家在仇恨半径内
					if ($hatred === false) {
						$hatred = $p;
					}
					else {
						if ($p->distance($pos) <= $hatred->distance($pos)) {  //比上一个更近
							$hatred = $p;
						}
					}
				}
			}
			//echo ($zom['IsChasing']."\n");
			if ($hatred == false) {
				$zom['IsChasing'] = 0;
			}
			else {
				$zom['IsChasing'] = $hatred->getName();
			}
			//echo ($zom['IsChasing']."\n");
			if($zom['IsChasing'] != "0"){
				//echo ("是属于仇恨模式\n");
				$p = $this->getServer()->getPlayer($zom['IsChasing']);
				if (($p instanceof Player) === false){
					$zom['IsChasing'] = 0;  //取消仇恨模式
				}
				else {
				$xxx =0.07;
				$zzz =0.07;
				$posz1 = new Vector3 ($zo->getX() + $xxx, $zo->getY(), $zo->getZ());
					if($p->distance($pos) > $p->distance($posz1)){
					$xxx =0.07;
					}
					if($p->distance($pos) == $p->distance($posz1)){
					$xxx =0;
					}
					if($p->distance($pos) < $p->distance($posz1)){
					$xxx =-0.07;
					}
				$posz2 = new Vector3 ($zo->getX()+ $xxx, $zo->getY(), $zo->getZ() + $zzz);
					if($p->distance($pos) < $p->distance($posz2)){
					$zzz =-0.07;
					}
					if($p->distance($pos) == $p->distance($posz2)){
					$zzz =0;
					}
					if($p->distance($pos) > $p->distance($posz2)){
					$zzz =0.07;
					}
					//我爱新算法。。
				/*
					//还不如用旧算法了。。
					$zx =floor($zo->getX());
					$zZ = floor($zo->getZ());
					$xxx = 0.07;
					$zzz = 0.07;
				
					$x1 =$zo->getX () - $p->getX();
				
					//$jumpy = $zo->getY() - 1;
				
					if($x1 >= -0.5 and $x1 <= 0.5) { //直行
						$zx = $zo->getX();
						$xxx = 0;
					}
					elseif($x1 < 0){
						$zx = $zo->getX() +0.07;
						$xxx =0.07;
					}else{
						$zx = $zo->getX() -0.07;
						$xxx = -0.07;
					}
					
					$z1 =$zo->getZ () - $p->getZ() ;
					if($z1 >= -0.5 and $z1 <= 0.5) { //直行
						$zZ = $zo->getZ();
						$zzz = 0;
					}					
					elseif($z1 <0){
						$zZ = $zo->getZ() +0.07;
						$zzz =0.07;
					}else{
						$zZ = $zo->getZ() -0.07;
						$zzz =-0.07;
					}
					
					if ($xxx == 0 and $zzz == 0) {
						$xxx = 0.1;
					}
					*/
					
					$zom['xxx'] = $xxx;
					$zom['zzz'] = $zzz;
					
					//计算y轴
					$width = $this->width;
					$pos0 = new Vector3 ($zo->getX(), $zo->getY() + 1,$zo->getZ());  //原坐标
					$pos = new Vector3 ($zo->getX()+ $xxx, $zo->getY() + 1,$zo->getZ() + $zzz);  //目标坐标
					$zy = $this->ifjump($zo->getLevel(),$pos);
					if ($zy === false) {  //前方不可前进
						$xxx = - $xxx * 10;
						$zzz = - $zzz * 10;
						if ($this->ifjump($zo->getLevel(),$pos0) === false) { //原坐标依然是悬空
							$pos2 = new Vector3 ($zo->getX(), $zo->getY() - 2,$zo->getZ());  //下降
							$zom['up'] = 1;
							$zom['yup'] = 0;
						}
						else {
							$pos2 = new Vector3 ($zo->getX() + $xxx, $zo->getY() - 1,$zo->getZ() + $zzz);  //目标坐标
							//转向180度，向身后走
							$zom['up'] = 0;
						}
					}
					else {
						$pos2 = new Vector3 ($zo->getX()+ $xxx, $zy - 1 , $zo->getZ() + $zzz);  //目标坐标
						$zom['up'] = 0;
					}
	
					$zo->setPosition($pos2);
					$yaw = $this->getyaw($xxx , $zzz);
					$zom['x'] = $zo->getX();
					$zom['y'] = $zo->getY();
					$zom['z'] = $zo->getZ();
					//$zo->setRotation($yaw,0);
					$zom['yaw'] = $yaw;
					if(0 <= $p->distance($pos) and $p->distance($pos) <= 1.5){
						if($zom['hurt'] >= 0){
							$zom['hurt'] = $zom['hurt'] -1 ;
						}else{
							$p->knockBack($zo, 0, $xxx * 3, $zzz * 3, 0.4);
							if ($p->isSurvival()) {
								$p->attack(2);
							}
							$zom['hurt'] = 10 ;
						}
					}
				}
		
			}
			else{
			if($zom['IsChasing'] == "0"){
			if($zom['up'] == 1){
				if($zom['yup'] <= 10){
					$pk3 = new SetEntityMotionPacket;
					$pk3->entities = [
					[$zo->getID(), $zom['motionx']/10,  $zom['motiony']/10 , $zom['motionz']/10]
					];
						foreach($zo->getViewers() as $pl){
						$pl->dataPacket($pk3);
						}
				}else{
				$pk3 = new SetEntityMotionPacket;
				$pk3->entities = [
				[$zo->getID(), $zom['motionx']/10,  -$zom['motiony']/10 , $zom['motionz']/10]
				];
					foreach($zo->getViewers() as $pl){
					$pl->dataPacket($pk3);
					}
				}
			}else{
				
				$pk3 = new SetEntityMotionPacket;
				$pk3->entities = [
				[$zo->getID(), $zom['motionx']/10,  -$zom['motiony']/10 , $zom['motionz']/10]
				];
					foreach($zo->getViewers() as $pl){
					$pl->dataPacket($pk3);
					
					}
			}
			}
			}
			}
			}
		}
	}
	
	public function ZombieDeath(EntityDeathEvent $event){//死亡移除数组 
	//var_dump($event->getEntity()->getId());
	@$founded = array_search($event->getEntity()->getId(),$this->zombie);  //得到玩家名在玩家列表中的键值
			//var_dump($founded);
            if ($founded !== false) {
	        	array_splice($this->zombie, $founded, 1);   //移除此键值的数据
				var_dump($this->zombie);
            }
	}
	
	public function ZombieFire() {//白天僵尸燃烧
	$filter_res = array_filter($this->zombie);
	if(!empty($filter_res)){
	foreach ($this->zombie as $zoms) {
	$level=$this->getServer()->getLevelByName($zoms['level']);
	 $zo = $level->getEntity($zoms['ID']);
	 if($zo != false){
	//var_dump($level->getTime());
				if(0 < $level->getTime() and $level->getTime() < 14000){
						$v3 = new Vector3($zo->getX(), $zo->getY(), $zo->getZ());
						$ok = true;
						for ($y0 = $zo->getY() + 2; $y0 <= $zo->getY()+10; $y0++) {
							$v3->y = $y0;
							if ($level->getBlock($v3)->getID() != 0) {
								$ok = false;
								break;
							}
					}
					if ($ok) $zo->setOnFire(2);
				}
				if($level->getTime() > 24000){
				$level->setTime(0);
				}
				}
			}
		}
	}
	
	public function ZombieGenerate() {//僵尸生成
		foreach ($this->getServer()->getOnlinePlayers() as $p) {
			//$this->getLogger()->info("开始生成僵尸");
			$level = $p->getLevel();
			if ($level->getTime() >= 14000) {  //是夜晚
			$v3 = new Vector3($p->getX() + mt_rand(-$this->birth_r,$this->birth_r), $p->getY(), $p->getZ() + mt_rand(-$this->birth_r,$this->birth_r));
			for ($y0 = $p->getY()-10; $y0 <= $p->getY()+10; $y0++) {
				$v3->y = $y0;
				if ($level->getBlock($v3)->getID() != 0) {
					$v3_1 = $v3;
					$v3_1->y = $y0 + 1;
					$v3_2 = $v3;
					$v3_2->y = $y0 + 2;
					if ($level->getBlock($v3_1)->getID() == 0 and $level->getBlock($v3_2)->getID() == 0) {  //找到地面
						if ($this->getLight($level,$v3) == 0) {
							$chunk = $level->getChunk($v3->x >> 4, $v3->z >> 4, false);
							$nbt = $this->getNBT($v3);
							$zo = new Zombie($chunk,$nbt);
							$zo->setPosition($v3);
							$zo->spawnToAll();
							//$zo = Entity::createEntity("Zombie", $level->getChunk($v3->x >> 4, $v3->z >> 4, false), $nbt, $level);
							//$zo->spawnToAll();
							//$this->getLogger()->info("生成了一只僵尸");
							break;
						}
					}
				}
			}
			}
		}
	}
	
	public function ZombieDamage(EntityDamageEvent $event){//僵尸击退修复
	if($event instanceof EntityDamageByEntityEvent){
	$p = $event->getDamager();
	$zo = $event->getEntity();
	if(isset($this->zombie[$zo->getId()])){	
	$zom = &$this->zombie[$zo->getId()];
	$zo->knockBack($p, 0, - $zom['xxx'] * 10, - $zom['zzz'] * 10, 0.4);
	//var_dump("玩家".$p->getName()."攻击了ID为".$zo->getId()."的僵尸");
	$zom['x'] = $zom['x'] - $zom['xxx'] * 10;
	$zom['y'] = $zo->getY();
	$zom['z'] = $zom['z'] - $zom['zzz'] * 10;
	$pos2 = new Vector3 ($zom['x'],$zom['y'],$zom['z']);  //目标坐标
	$zo->setPosition($pos2);
	}
	}
	}
	
	public function onDisable(){
		$this->getLogger()->info("MyZombie Unload Success!");
	}
	
}
