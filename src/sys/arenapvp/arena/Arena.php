<?php
/**
 * Created by PhpStorm.
 * User: Matt
 * Date: 2/10/2017
 * Time: 6:29 PM
 */

namespace sys\arenapvp\arena;

use pocketmine\entity\Item;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\utils\MainLogger;

class Arena {

	/** @var int */
	private $type;

	/** @var Level */
	private $level;

	/** @var int */
	private $id;

	/** @var int */
	private $maxBuildHeight;

	/** @var Position[] */
	private $edges = [];

	/** @var ArenaChunk[] */
	private $chunks = [];

	/** @var Position[] */
	private $positions = [];

	/** @var bool */
	private $inUse = false;

	/**
	 * Arena constructor.
	 * @param int $id
	 * @param array $positions
	 * @param array $edges
	 * @param Level $level
	 * @param int $type
	 * @param int $maxBuildHeight
	 */
	public function __construct(int $id, array $positions, array $edges, Level $level, int $type, int $maxBuildHeight) {
		$this->type = $type;
		$this->id = $id;
		$this->level = $level;
		$this->edges = $edges;
		$this->maxBuildHeight = $maxBuildHeight;
		$this->positions = $positions;
		$this->saveChunks();
	}

	public function toYAML() {
		return ["pos1" => [$this->getPosition(0)->getFloorX(), $this->getPosition(0)->getFloorY(), $this->getPosition(0)->getFloorZ()], "pos2" => [$this->getPosition(1)->getFloorX(), $this->getPosition(1)->getFloorY(), $this->getPosition(1)->getFloorZ()], "edge1" => [$this->getEdge(0)->getFloorX(), $this->getEdge(0)->getFloorY(), $this->getEdge(0)->getFloorZ()], "edge2" => [$this->getEdge(1)->getFloorX(), $this->getEdge(1)->getFloorY(), $this->getEdge(1)->getFloorZ()], "levelName" => $this->getLevel()->getFolderName(), "type" => $this->getType(), "maxBuildHeight" => $this->getMaxBuildHeight()];
	}

	/**
	 * @param int $id
	 */
	public function setId(int $id) {
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function getType(): int {
		return $this->type;
	}

	/**
	 * @return Level
	 */
	public function getLevel(): Level {
		return $this->level;
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @param int $index
	 * @return Position|null
	 */
	public function getEdge(int $index): ?Position {
		return $this->edges[$index] ?? null;
	}

	/**
	 * @return Position[]
	 */
	public function getEdges(): array {
		return $this->edges;
	}

	/**
	 * @return Position|null
	 */
	public function getRandomPosition(): ?Position {
		return $this->getPosition(array_rand($this->positions, 1));
	}

	/**
	 * @param int $index
	 * @return Position|null
	 */
	public function getPosition(int $index): ?Position {
		return $this->positions[$index] ?? null;
	}

	/**
	 * @return Position[]
	 */
	public function getPositions(): array {
		return $this->positions;
	}

	/**
	 * @return bool
	 */
	public function inUse(): bool {
		return $this->inUse;
	}

	public function setInUse(bool $value = true) {
		$this->inUse = $value;

		if ($value) {
			$this->prepareChunks();
		}
	}

	/**
	 * @return int
	 */
	public function getMaxBuildHeight(): int {
		return $this->maxBuildHeight;
	}

	/**
	 * @return ArenaChunk[]
	 */
	public function getChunks(): array {
		return $this->chunks;
	}

	public function resetChunks() {
		$this->chunks = [];
	}

	/**
	 * @param Chunk $chunk
	 * @return bool
	 */
	public function chunkExists(Chunk $chunk): bool {
		return isset($this->chunks[Level::chunkHash($chunk->getX(), $chunk->getZ())]);
	}

	/**
	 * @param Chunk $chunk
	 */
	public function addChunk(Chunk $chunk) {
		$this->chunks[$hash = Level::chunkHash($chunk->getX(),  $chunk->getZ())] = new ArenaChunk($this, $chunk);
		$this->getLevel()->registerChunkLoader($this->chunks[$hash], $chunk->getX(), $chunk->getZ());
	}

	public function saveChunks() {
		$this->resetChunks();
		$pos1 = $this->getEdge(0);
		$pos2 = $this->getEdge(1);
		$posMin = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		$posMax = new Vector3(max($pos1->x, $pos2->x), max($pos1->y, $pos2->y), max($pos1->z, $pos2->z));
		for ($x = $posMin->getFloorX(); $x <= $posMax->getFloorX(); $x++) {
			for ($z = $posMin->getFloorZ(); $z <= $posMax->getFloorZ(); $z++) {
				$chunk = $this->getLevel()->getChunk($x >> 4, $z >> 4);
				if ($chunk !== null and !$this->chunkExists($chunk)) {
					$this->addChunk($chunk);
				}

			}
		}
		MainLogger::getLogger()->debug("Arena #" . ($this->getId() + 1) . " > " . count($this->getChunks()) . " chunks saved!");
	}

	public function prepareChunks() {
		$level = $this->getLevel();
		$chunkCount = 0;
		foreach ($this->chunks as $chunk) {
			$level->registerChunkLoader($chunk, $chunk->getChunkX(), $chunk->getChunkZ());
			$chunkCount++;
		}
		MainLogger::getLogger()->debug("Arena #" . ($this->getId() + 1) . " > " . $chunkCount . " chunks prepared!");
	}

	public function resetArena() {
		$this->setInUse(false);
		$level = $this->getLevel();
		$chunkCount = 0;
		foreach ($this->getChunks() as $chunk) {
			$level->unregisterChunkLoader($chunk, $chunk->getChunkX(), $chunk->getChunkZ());
			$level->setChunk($chunk->getChunkX(), $chunk->getChunkZ(), $chunk->getChunk(), true);
			$level->clearChunkCache($chunk->getChunkX(), $chunk->getChunkZ());
			$chunkCount++;
		}
		MainLogger::getLogger()->debug("Arena #" . ($this->getId() + 1) . " > " . $chunkCount . " chunks reset!");
	}

}