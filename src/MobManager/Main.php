<?php

namespace MobManager;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\level\format\FullChunk;

class Main extends PluginBase{

    /** @var int */
    private $spawnTimer = 0;

    /** @var int */
    private $cleanupTimer = 0;

public function onEnable(){

    @mkdir($this->getDataFolder());

    $this->saveResource("config.yml", false);

    $this->reloadConfig();

    $this->getLogger()->info("MobManager aktif!");

    $this->getServer()->getScheduler()->scheduleRepeatingTask(
        new MobTask($this),
        20
    );
}

    /**
     * Dipanggil setiap 1 detik.
     */
    public function tick(){

        $this->spawnTimer += 20;
        $this->cleanupTimer += 20;

        $spawnInterval = (int) $this->getConfig()->get("spawn")["interval"];
        $cleanupInterval = (int) $this->getConfig()->get("cleanup")["interval"] * 20;

        if(
            $this->getConfig()->getNested("spawn.enabled", true) &&
            $this->spawnTimer >= $spawnInterval
        ){
            $this->spawnTimer = 0;
            $this->spawnMobs();
        }

        if(
            $this->getConfig()->getNested("cleanup.enabled", true) &&
            $this->cleanupTimer >= $cleanupInterval
        ){
            $this->cleanupTimer = 0;
            $this->cleanupMobs();
        }
    }

    /**
     * Spawn animal dan monster.
     */
    public function spawnMobs(){

        foreach($this->getServer()->getLevels() as $level){

            if(!$level instanceof Level){
                continue;
            }

            /*
             * ==========================
             * ANIMAL
             * ==========================
             */

            if($this->getConfig()->getNested("animals.enabled", true)){

                $animalMax = (int) $this->getConfig()->getNested(
                    "animals.max",
                    32
                );

                $animalAmount = (int) $this->getConfig()->getNested(
                    "animals.amount",
                    2
                );

                $animalCount = $this->countAnimals($level);

                if($animalCount < $animalMax){

                    $amount = min(
                        $animalAmount,
                        $animalMax - $animalCount
                    );

                    for($i = 0; $i < $amount; $i++){
                        $this->spawnAnimal($level);
                    }
                }
            }

            /*
             * ==========================
             * MONSTER
             * ==========================
             */

            if($this->getConfig()->getNested("monsters.enabled", true)){

                $monsterMax = (int) $this->getConfig()->getNested(
                    "monsters.max",
                    50
                );

                $monsterAmount = (int) $this->getConfig()->getNested(
                    "monsters.amount",
                    3
                );

                $monsterCount = $this->countMonsters($level);

                if($monsterCount < $monsterMax){

                    $amount = min(
                        $monsterAmount,
                        $monsterMax - $monsterCount
                    );

                    for($i = 0; $i < $amount; $i++){
                        $this->spawnMonster($level);
                    }
                }
            }
        }
    }

    /**
     * Spawn animal.
     */
private function spawnAnimal(Level $level){

    $players = $level->getPlayers();

    if(count($players) === 0){
        return;
    }

    $player = $players[array_rand($players)];

    $pos = $this->getSpawnPosition(
        $level,
        $player,
        false
    );

    if($pos === null){
        return;
    }

    $animals = array(
        "Cow",
        "Pig",
        "Sheep",
        "Chicken"
    );

    $name = $animals[array_rand($animals)];

    $nbt = new CompoundTag("", array(
        "Pos" => new ListTag("Pos", array(
            new DoubleTag("", $pos->x),
            new DoubleTag("", $pos->y),
            new DoubleTag("", $pos->z)
        )),
        "Motion" => new ListTag("Motion", array(
            new DoubleTag("", 0),
            new DoubleTag("", 0),
            new DoubleTag("", 0)
        )),
        "Rotation" => new ListTag("Rotation", array(
            new FloatTag("", mt_rand(0, 360)),
            new FloatTag("", 0)
        ))
    ));

$chunkX = $pos->getFloorX() >> 4;
$chunkZ = $pos->getFloorZ() >> 4;

$chunk = $level->getChunk($chunkX, $chunkZ, true);

$entity = Entity::createEntity(
    $name,
    $chunk,
    $nbt
);

    if($entity !== null){
        $entity->spawnToAll();
    }
}

    /**
     * Spawn monster.
     */
private function spawnMonster(Level $level){

    $players = $level->getPlayers();

    if(count($players) === 0){
        return;
    }

    $player = $players[array_rand($players)];

    $pos = $this->getSpawnPosition(
        $level,
        $player,
        true
    );

    if($pos === null){
        return;
    }

    $monsters = array(
        "Zombie",
        "Skeleton",
        "Creeper",
        "Spider"
    );

    $name = $monsters[array_rand($monsters)];

    $nbt = new CompoundTag("", array(
        "Pos" => new ListTag("Pos", array(
            new DoubleTag("", $pos->x),
            new DoubleTag("", $pos->y),
            new DoubleTag("", $pos->z)
        )),
        "Motion" => new ListTag("Motion", array(
            new DoubleTag("", 0),
            new DoubleTag("", 0),
            new DoubleTag("", 0)
        )),
        "Rotation" => new ListTag("Rotation", array(
            new FloatTag("", mt_rand(0, 360)),
            new FloatTag("", 0)
        ))
    ));

$chunkX = $pos->getFloorX() >> 4;
$chunkZ = $pos->getFloorZ() >> 4;

$chunk = $level->getChunk($chunkX, $chunkZ, true);

$entity = Entity::createEntity(
    $name,
    $chunk,
    $nbt
);

    if($entity !== null){
        $entity->spawnToAll();
    }
}

    /**
     * Cari posisi spawn.
     */
    private function getSpawnPosition(
        Level $level,
        Player $player,
        $monster = false
    ){

        $radius = (int) $this->getConfig()->getNested(
            "spawn.radius",
            30
        );

        $x = (int) $player->x + mt_rand(-$radius, $radius);
        $z = (int) $player->z + mt_rand(-$radius, $radius);

        /*
         * Cari Y dari atas world.
         */
        $y = $level->getHighestBlockAt($x, $z);

        if($y <= 0){
            return null;
        }

        $block = $level->getBlockIdAt($x, $y, $z);

        /*
         * Pastikan ada tempat untuk entity.
         */
        if($level->getBlockIdAt($x, $y + 1, $z) !== 0){
            return null;
        }

        if($level->getBlockIdAt($x, $y + 2, $z) !== 0){
            return null;
        }

        /*
         * Animal harus punya blok permukaan.
         */
        if(!$monster){

            /*
             * Grass / dirt / sand / snow.
             */
            $allowed = array(
                2,  // Grass
                3,  // Dirt
                12, // Sand
                13, // Gravel
                80  // Snow
            );

            if(!in_array($block, $allowed)){
                return null;
            }
        }

        /*
         * Monster hanya spawn dalam kondisi gelap.
         */
if($monster){

    $time = $level->getTime() % 24000;

    if($time < 12000){
        return null;
    }
}

        return new \pocketmine\math\Vector3(
            $x + 0.5,
            $y + 1,
            $z + 0.5
        );
    }

    /**
     * Hitung animal.
     */
    private function countAnimals(Level $level){

        $count = 0;

        foreach($level->getEntities() as $entity){

            if(
                $entity instanceof \milk\pureentities\entity\animal\Animal
            ){
                $count++;
            }
        }

        return $count;
    }

    /**
     * Hitung monster.
     */
    private function countMonsters(Level $level){

        $count = 0;

        foreach($level->getEntities() as $entity){

            if(
                $entity instanceof \milk\pureentities\entity\monster\Monster
            ){
                $count++;
            }
        }

        return $count;
    }


/**
 * Task sederhana untuk Genisys.
 */
class MobTask extends \pocketmine\scheduler\PluginTask{

    public function __construct(Main $plugin){
        parent::__construct($plugin);
    }

    public function onRun($currentTick){
        $this->getOwner()->tick();
    }
}
