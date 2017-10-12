<?php
/**
 * Created by PhpStorm.
 * User: Matt
 * Date: 2/10/2017
 * Time: 6:17 PM
 */

namespace sys\arenapvp;


use pocketmine\block\Block;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\inventory\PlayerInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\SourceInterface;
use pocketmine\Player;
use pocketmine\plugin\PluginException;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use sys\arenapvp\kit\Kit;
use sys\arenapvp\match\Match;
use sys\arenapvp\menu\Menu;
use sys\arenapvp\party\Party;
use sys\arenapvp\queue\Queue;
use sys\arenapvp\utils\ArenaChest;


/*
 * TODO: Rewrite some of the hacks I implemented in a couple of months back.
 */

class ArenaPlayer extends Player {

	/**
	 * Thanks to Steadfast 2 for providing these constants
	 * https://github.com/Hydreon/Steadfast2
	 */
	const OS_ANDROID = 1;
	const OS_IOS = 2;
	const OS_OSX = 3;
	const OS_FIREOS = 4;
	const OS_GEARVR = 5;
	const OS_HOLOLENS = 6;
	const OS_WIN10 = 7;
	const OS_WIN32 = 8;
	const OS_DEDICATED = 9;
	const OS_ORBIS = 10;
	const OS_NX = 11;

	/** @var bool */
	private $duelRequestsEnabled = true;

	/** @var bool */
	private $partyInvitesEnabled = true;

	/** @var int */
	private $os = 0;

	/** @var ArenaPvP */
	private $main;

	/** @var Party|null */
	private $party = null;

	/** @var Queue|null */
	private $queue = null;

	/** @var int */
	private $searchDifference = 100;

	/** @var int */
	private $rankedMatchesLeft = 15;

	/** @var int */
	private $unrankedMatchesLeft = 40;

	/** @var bool */
	private $duelRequest = false;

	/** @var bool */
	private $inMatch = false;

	/** @var Match */
	private $match = null;

	/** @var Match */
	private $spectatingMatch = null;

	/** @var Menu */
	private $menu = null;

	/** @var Config */
	private $data = null;

	/** @var Elo[] */
	private $elo = [];

	public function __construct(SourceInterface $interface, $clientID, $ip, $port) {
		parent::__construct($interface, $clientID, $ip, $port);
		if (($plugin = $this->getServer()->getPluginManager()->getPlugin("ArenaPvP")) instanceof ArenaPvP and $plugin->isEnabled()) {
			$this->main = $plugin;
		} else {
			$this->kick(TextFormat::RED . "Error!");
			throw new PluginException("[ERROR] ArenaPvP is not loaded!");
		}
	}

	/**
	 * @param string $message
	 * @param string[] ...$args
	 */
	public function sendArgsMessage(string $message, string ...$args) {
		for ($i = 0; $i < count($args); $i++) {
			$message = str_replace("{" . $i . "}", $args[$i], $message);
		}
		$this->sendMessage($message);
	}

	/**
	 * @return ArenaPvP
	 */
	public function getMain(): ArenaPvP {
		return $this->main;
	}

	/**
	 * @return Config
	 */
	public function getData(): Config {
		return $this->data;
	}

	public function loadData() {
		$this->data = new Config($this->getMain()->getDataFolder() . DIRECTORY_SEPARATOR . ArenaPvP::FOLDER_NAME . DIRECTORY_SEPARATOR . $this->getLowerCaseName() . ".json", Config::JSON);
	}

	/**
	 * @return int
	 */
	public function getClientOs(): int {
		return $this->os;
	}

	/**
	 * @param int $os
	 */
	public function setClientOs(int $os) {
		$this->os = $os;
	}

	/**
	 * TODO: Implement command sending
	 */
	public function sendCommandData() {
		parent::sendCommandData();
	}

	/**
	 * @return bool
	 */
	public function fullyInMatch(): bool {
		return $this->inMatch() or $this->inMatchBool();
	}

	/**
	 * @return bool
	 */
	public function inMatch(): bool {
		return $this->match instanceof Match;
	}

	/**
	 * @return bool
	 */
	public function inMatchBool(): bool {
		return $this->inMatch;
	}

	/**
	 * @return Match|null
	 */
	public function getMatch(): ?Match {
		return $this->match;
	}

	public function setInMatch($value = true) {
		$this->inMatch = $value;
	}

	/**
	 * @param Match $match
	 */
	public function setMatch(Match $match) {
		$this->match = $match;
	}

	public function removeMatch() {
		$this->match = null;
	}


	/**
	 * @return bool
	 */
	public function inQueue(): bool {
		return $this->queue instanceof Queue;
	}

	/**
	 * @return Queue|null
	 */
	public function getQueue(): ?Queue {
		return $this->queue;
	}

	/**
	 * @param Queue|null $queue
	 */
	public function setQueue(?Queue $queue) {
		$this->queue = $queue;
	}


	public function removeFromQueue() {
		if($this->inQueue()) {
			$this->getQueue()->removePlayer($this);
			$this->queue = null;
		}
	}

	/**
	 * @param Menu $menu
	 */
	public function addMenu(Menu $menu) {
		$this->menu = $menu;
	}

	/**
	 * @param string $name
	 */
	public function sendMenu(string $name) {
		if ($this->inMenu() and $this->getLevel() !== null) {
			$tile = Tile::createTile("ArenaChest", $this->getLevel(), new CompoundTag("", [new StringTag("CustomName", $name), new StringTag("id", Tile::CHEST), new IntTag("x", floor($this->getX())), new IntTag("y", floor($this->getY()) + 4), new IntTag("z", floor($this->getZ())),]));
			$block = (Block::get(Block::CHEST))->setComponents($tile->getX(), $tile->getY(), $tile->getZ());
			$block->setLevel($tile->getLevel());
			$block->getLevel()->sendBlocks([$this], [$block]);
			if ($tile instanceof ArenaChest) {
				$tile->getInventory()->setContents($this->getMenu()->getItems());
				$this->addWindow($tile->getInventory());
			} else {
				$this->removeMenu();
			}
		}
	}

	/**
	 * @return bool
	 */
	public function inMenu(): bool {
		return $this->menu !== null;
	}

	/**
	 * @return Menu|null
	 */
	public function getMenu(): ?Menu {
		return $this->menu ?? null;
	}

	public function removeMenu() {
		$this->menu = null;
	}

	public function hasDuelRequestsEnabled() {
		return $this->duelRequestsEnabled;
	}

	public function setDuelRequestsEnabled($value = false) {
		$this->duelRequestsEnabled = $value;
	}

	public function hasPartyInvitesEnabled() {
		return $this->partyInvitesEnabled;
	}

	public function setPartyInvitesEnabled($value = false) {
		$this->partyInvitesEnabled = $value;
	}

	public function hasDuelRequest() {
		return $this->duelRequest;
	}

	public function setHasDuelRequest($value = true) {
		$this->duelRequest = $value;
	}

	/**
	 * @param Party|null $party
	 */
	public function setParty($party) {
		$this->party = $party;
	}

	public function getParty() {
		return $this->party;
	}

	public function inParty(): bool {
		return $this->party !== null;
	}

	/**
	 * @param string $name
	 * @param ArenaPlayer[] $players
	 */
	public function setCustomNameTag(string $name, $players) {
		foreach ($players as $player) {
			$this->sendData($player, [self::DATA_NAMETAG => [self::DATA_TYPE_STRING, $name]]);
		}
	}

	/**
	 * @return Match|null
	 */
	public function getMatchSpectating(): ?Match {
		return $this->spectatingMatch;
	}

	public function removeAllEffects() {
		foreach ($this->effects as $effect) {
			if ($effect->getId() == Effect::NIGHT_VISION) {
				continue;
			}
			$this->removeEffect($effect->getId());
		}
	}

	public function setSpectating($addEffects = false, Match $match = null) {
		if ($addEffects) {
			$this->addEffect(Effect::getEffect(Effect::BLINDNESS)->setDuration(20 * 2)->setAmplifier(3));
			$this->addEffect(Effect::getEffect(Effect::SLOWNESS)->setDuration(20 * 2)->setAmplifier(3));
		}
		$this->reset();
		$this->setNameTagVisible(false);
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->hidePlayer($this);
		}
		$this->setFlying(true);
		$this->setAllowFlight(true);
		if($match instanceof Match and !$match->getBossBar()->hasPlayer($this)) {
			$match->getBossBar()->addBossBar($this);
			$this->spectatingMatch = $match;
		}
	}

	/**
	 * @param Kit $kit
	 * @return int
	 *
	 * Returns the player's KFactor for a specific kit.
	 *
	 */
	public function getKFactor(Kit $kit): int {
		$elo = $this->getElo($kit)->getElo();
		if ($elo < 1600) {
			$kFactor = 32;
		} else if ($elo >= 1600 and $elo < 2000) {
			$kFactor = 24;
		} else if ($elo >= 2000 and $elo <= 2400) {
			$kFactor = 16;
		} else {
			$kFactor = 8;
		}
		return $kFactor;
	}

	public function loadElo() {
		if(!$this->getData()->exists("elo")) {
			$this->getData()->set("elo", []);
		}
		$elo = $this->getData()->get("elo");
		foreach ($this->getMain()->getKitManager()->getKits() as $kit) {
			if (isset($elo[$kit->getName()])) {
				$this->addElo($kit, (int)$elo[$kit->getName()]);
			} else {
				$this->addElo($kit);
				$elo[$kit->getName()] = $this->getElo($kit)->getElo();
			}
		}
		$this->getData()->set("elo", $elo);
		$this->getData()->save();
	}

	/**
	 * @return Elo[]
	 */
	public function getAllElo(): array {
		return $this->elo;
	}

	public function boostElo(Kit $kit, int $elo) {
		$eloKit = $this->elo[$kit->getName()];
		$eloKit->setElo($eloKit->getElo() + $elo);
	}

	public function subtractElo(Kit $kit, int $elo) {
		$eloKit = $this->elo[$kit->getName()];
		$eloKit->setElo($eloKit->getElo() - $elo);
	}

	public function addElo(Kit $kit, int $elo = 1500) {
		$this->elo[$kit->getName()] = new Elo($kit, $elo);
	}

	public function setElo(Kit $kit, int $elo) {
		$this->getElo($kit)->setElo($elo);
	}

	public function saveElo(Kit $kit) {
		$eloArray = $this->getData()->get("elo");
		$eloArray[$kit->getName()] = $this->getElo($kit)->getElo();
		$this->getData()->set("elo", $eloArray);
		$this->getData()->save();
	}

	/**
	 * @param Kit $kit
	 * @return Elo
	 */
	public function getElo(Kit $kit): Elo {
		return $this->elo[$kit->getName()];
	}

	/**
	 * @return bool
	 */
	public function isSpectating(): bool {
		return $this->spectatingMatch instanceof Match;
	}

	public function dropAllItems() {
		$items = array_merge($this->getInventory()->getContents(), $this->getInventory()->getArmorContents());
		foreach ($items as $item) {
			$this->getLevel()->dropItem($this, $item);
		}
	}

	public function removeFromSpectating() {
		$this->setNameTagVisible();
		foreach ($this->getServer()->getOnlinePlayers() as $player) {
			$player->showPlayer($this);
		}
		if ($this->isSpectating()) {
			$this->spectatingMatch->getBossBar()->removeBossBar($this);
			$this->spectatingMatch = null;
		}
		$this->setGamemode(self::SURVIVAL);
		$this->setFlying(false);
		$this->setAllowFlight(false);
	}

	public function reset(int $gamemode = ArenaPlayer::ADVENTURE) {
		$this->setGamemode($gamemode);
		if ($this->getInventory() instanceof PlayerInventory) {
			$this->getInventory()->clearAll();
		}
		$this->removeAllEffects();
		$this->extinguish();
		$this->setFood($this->getMaxFood());
		$this->setSaturation($this->getAttributeMap()->getAttribute(Attribute::SATURATION)->getMaxValue()); //no constant? oh well
		$this->setHealth($this->getMaxHealth());
	}

	public function logOut() {
		if ($this->inQueue()) {
			$this->removeFromQueue();
		}
		if ($this->getMain()->getPartyManager()->hasInviteObject($this)) {
			$this->getMain()->getPartyManager()->removeHostObject($this);
		}
		if ($this->inParty()) {
			if ($this->getName() === $this->getParty()->getLeader()->getName()) {
				$this->getMain()->getPartyManager()->removeParty($this->getParty());
				$this->getParty()->disbandParty();
			} else {
				$party = $this->getParty();
				$party->removePlayer($this);
			}
		}
	}

}