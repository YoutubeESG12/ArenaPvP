<?php

/**
 * Created by PhpStorm.
 * User: Jack
 * Date: 4/10/2017
 * Time: 9:59 AM
 */

namespace sys\arenapvp\arena;

use pocketmine\item\Item;
use pocketmine\level\ChunkLoader;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\utils\MainLogger;

/**
 * Class for storing chunk data for an arena
 */
class ArenaChunk implements ChunkLoader {

	/** @var Arena */
	private $arena;

	/** @var int */
	private $loaderId = 0;

	/** @var string */
	private $chunkData; // serialized chunk

	/** @var int */
	private $chunkX;

	/** @var int */
	private $chunkZ;

	/** @var int */
	private $x;

	/** @var int */
	private $z;

	public function __construct(Arena $arena, Chunk $chunk) {
		$this->arena = $arena;
		$this->loaderId = Level::generateChunkLoaderId($this);
		$this->prepareChunk($chunk);
	}

	public function getChunk() : Chunk {
		return Chunk::fastDeserialize($this->chunkData);
	}

	public function getChunkData() : string {
		return $this->chunkData;
	}

	/**
	 * Get the chunks right-bit-shifted x coordinate
	 *
	 * @return int
	 */
	public function getChunkX() : int {
		return $this->chunkX;
	}

	/**
	 * Get the chunks right-bit-shifted z coordinate
	 *
	 * @return int
	 */
	public function getChunkZ() : int {
		return $this->chunkZ;
	}

	/**
	 * Get the chunks center x coordinate
	 *
	 * @return int
	 */
	public function getX() : int {
		return $this->x;
	}

	/**
	 * Get the chunks center z coordinate
	 *
	 * @return int
	 */
	public function getZ() : int {
		return $this->z;
	}

	public function isLoaderActive() : bool {
		return $this->arena->inUse();
	}

	public function getLevel() {
		return $this->arena->getLevel();
	}

	public function getLoaderId() : int {
		return $this->loaderId;
	}

	public function getPosition() {
		return new Position($this->x, $this->arena->getMaxBuildHeight(), $this->z, $this->getLevel());
	}

	public function onChunkChanged(Chunk $chunk) {
	}

	public function onChunkLoaded(Chunk $chunk) {
	}

	public function onChunkUnloaded(Chunk $chunk) {
		if($this->isLoaderActive()) {
			MainLogger::getLogger()->debug("Arena #" . ($this->arena->getId() + 1) . " > Chunk unloaded while arena in use! (X: " . $this->chunkX . " Z: " . $this->chunkZ . ")");
		}
	}

	public function onChunkPopulated(Chunk $chunk) {
	}

	public function onBlockChanged(Vector3 $block) {
	}

	/**
	 * Prepare the chunk for serialization and update the chunk data
	 *
	 * @param Chunk $chunk
	 */
	protected function prepareChunk(Chunk $chunk) {
		foreach ($chunk->getEntities() as $entity) {
			if ($entity instanceof Item) {
				$entity->close();
			}
		}

		$this->chunkX = $chunk->getX();
		$this->chunkZ = $chunk->getZ();
		$this->x = ($chunk->getX() << 4) + 8;
		$this->z = ($chunk->getZ() << 4) + 8;
		$this->chunkData = $chunk->fastSerialize();
	}

}