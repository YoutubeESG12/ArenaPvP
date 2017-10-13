<?php
/**
 * Created by PhpStorm.
 * User: Matt
 * Date: 2/10/2017
 * Time: 7:11 PM
 */

namespace sys\arenapvp\match;


use pocketmine\block\Block;
use pocketmine\block\Planks;
use pocketmine\entity\Arrow;
use pocketmine\entity\Effect;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityEffectRemoveEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\level\sound\AnvilBreakSound;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\Sound;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use sys\arenapvp\arena\Arena;
use sys\arenapvp\ArenaPlayer;
use sys\arenapvp\ArenaPvP;
use sys\arenapvp\kit\Kit;
use sys\arenapvp\utils\BossBar;

class Match {

	/** @var ArenaPvP */
	protected $plugin;

	/** @var int */
	protected $countdown = 10;

	/** @var int */
	protected $time = 60 * 15;

	/** @var int */
	protected $finishTime = 3;

	/** @var Arena */
	protected $arena;

	/** @var BossBar */
	protected $bossBar;

	/** @var string */
	private $id = "";

	/** @var Kit */
	protected $kit;

	/** @var ArenaPlayer[] */
	private $matchPlayers = [];

	/** @var ArenaPlayer[] */
	protected $players = [];

	/** @var ArenaPlayer[] */
	protected $spectators = [];

	/** @var bool */
	protected $ended = false;

	/** @var bool */
	private $ranked = false;

	/** @var bool */
	protected $started = false;

	/** @var Vector3[] */
	private $blocksPlaced = [];

	/** @var ArenaPlayer */
	private $winner = null;

	/** @var Position[] */
	protected $positions = [];
	/**
	 * Match constructor.
	 * @param ArenaPvP $plugin
	 * @param Kit $kit
	 * @param ArenaPlayer[] $players
	 * @param Arena $arena
	 * @param bool $ranked
	 */
	public function __construct(ArenaPvP $plugin, Kit $kit, array $players, Arena $arena, bool $ranked = false) {
		$this->plugin = $plugin;
		$this->arena = $arena;
		$this->id = uniqid("", true);
		$this->bossBar = new BossBar($plugin);
		$this->kit = $kit;
		$this->players = $players;
		$this->matchPlayers = $players;
		$this->ranked = $ranked;
		$this->init();
	}

	public function teleportPlayers() {
		foreach ($this->getPlayers() as $player) {
			$position = Position::fromObject($this->getMatchPosition($player)->add(0, 2), $this->getMatchPosition($player)->getLevel());
			$player->teleport($position);
		}
	}

	public function setMatchPosition(ArenaPlayer $player, int $index) {
		$this->positions[$player->getName()] = $this->getArena()->getPosition($index);
	}

	/**
	 * @param ArenaPlayer $player
	 * @return Position|null
	 */
	public function getMatchPosition(ArenaPlayer $player): ?Position {
		return $this->positions[$player->getName()] ?? null;
	}

	public function sendNameTags(ArenaPlayer $player) {
		$player->setCustomNameTag(TextFormat::GREEN . $player->getName(), [$player]);
		$player->setCustomNameTag(TextFormat::RED . $player->getName(), $this->getAllOtherPlayers($player));
	}

	/**
	 * @param ArenaPlayer $player
	 * @return ArenaPlayer[]
	 */
	public function getAllOtherPlayers(ArenaPlayer $player) {
		$otherPlayers = $this->getPlayers();
		unset($otherPlayers[$player->getName()]);
		return $otherPlayers;
	}

	public function shufflePlayers() {
		$keys = array_keys($this->players);
		shuffle($keys);
		$new = [];
		foreach ($keys as $key) {
			$new[$key] = $this->players[$key];
		}
		$this->players = $new;
	}

	protected function init() {
		if (!$this->players or count($this->players) <= 1) {
			$this->reset();
			return;
		}
		$this->shufflePlayers();
		foreach ($this->getPlayers() as $player) {
			$player->reset(ArenaPlayer::SURVIVAL);
			$player->setMatch($this);
			$this->getBossBar()->addBossBar($player);
			$this->getBossBar()->setBossBarProgress(20);
		}
		$chunkSize = ceil(count($this->getPlayers()) / 2);
		$split = array_chunk($this->getPlayers(), $chunkSize);
		for ($i = 0; $i <= 1; $i++) {
			foreach ($split[$i] as $player) {
				$this->setMatchPosition($player, $i);
				$this->getBossBar()->setBossBarTitle(TextFormat::GRAY . "Starting in " . TextFormat::GOLD . gmdate("i:s", $this->countdown) . TextFormat::GRAY . "...");
			}
		}
		$this->teleportPlayers();
	}


	/**
	 * @return Arena
	 */
	public function getArena(): Arena {
		return $this->arena;
	}

	/**
	 * @return BossBar
	 */
	public function getBossBar(): BossBar {
		return $this->bossBar;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return Kit
	 */
	public function getKit(): Kit {
		return $this->kit;
	}

	/**
	 * @param ArenaPlayer $player
	 * @return bool
	 */
	public function isPlayer(ArenaPlayer $player): bool {
		return isset($this->players[$player->getName()]);
	}

	public function addPlayer(ArenaPlayer $player) {
		if(!isset($this->players[$player->getName()])) {
			$this->players[$player->getName()] = $player;
		}
	}

	public function removePlayer(ArenaPlayer $player) {
		if(isset($this->players[$player->getName()])) {
			unset($this->players[$player->getName()]);
		}
	}

	public function isSpectator(ArenaPlayer $player) {
		return isset($this->spectators[$player->getName()]);
	}

	public function addSpectator(ArenaPlayer $player, $addEffects = false) {
		if(!isset($this->spectators[$player->getName()])) {
			$this->spectators[$player->getName()] = $player;
		}
		$player->setSpectating($addEffects, $this);
	}

	public function removeSpectator(ArenaPlayer $player) {
		if(isset($this->spectators[$player->getName()])) {
			unset($this->spectators[$player->getName()]);
		}
	}

	/**
	 * @return ArenaPlayer[]
	 */
	public function getAll(): array {
		return array_merge($this->players, $this->spectators);
	}

	/**
	 * @return ArenaPlayer[]
	 */
	public function getMatchPlayers(): array {
		return $this->matchPlayers;
	}

	/**
	 * @return ArenaPlayer[]
	 */
	public function getPlayers(): array {
		return $this->players;
	}

	/**
	 * @return ArenaPlayer[]
	 */
	public function getSpectators(): array {
		return $this->spectators;
	}

	/**
	 * @return ArenaPvP
	 */
	public function getPlugin(): ArenaPvP {
		return $this->plugin;
	}

	/**
	 * @return null|bool
	 */
	public function hasStarted(): ?bool {
		return $this->started;
	}

	public function setStarted($value = true) {
		$this->started = $value;
	}

	/**
	 * @return bool
	 */
	public function hasEnded() {
		return $this->ended;
	}

	public function setEnded($value = true) {
		$this->ended = $value;
	}

	public function tick() {
		if ($this->hasEnded()) {
			$this->finishTime--;
			if ($this->finishTime <= 0) {
				$this->reset();
			}
			return;
		}

		if (!$this->hasStarted() and !$this->hasEnded()) {
			$this->countdown--;
			$this->getBossBar()->setBossBarTitle(TextFormat::GRAY . "Starting in " . TextFormat::GOLD . gmdate("i:s", $this->countdown - 1) . TextFormat::GRAY . "...");
			foreach ($this->getPlayers() as $player) {
				$position = $this->getMatchPosition($player);
				if ($player->distance($position) >= 6) {
					$player->teleport($position->add(0, 2));
				}
			}
			if ($this->countdown != 0) {
				if ($this->countdown <= 3) {
					foreach ($this->getAll() as $player) {
						$this->broadcastSound(new AnvilFallSound($player, $this->countdown));
					}
					$this->broadcastMessage(TextFormat::GREEN . "Match starting in {$this->countdown}...");
					$this->broadcastTitle(TextFormat::GRAY . "Match starting in:", TextFormat::GREEN . "{$this->countdown}...", 1, 20, 1);
				}
			} else {
				foreach ($this->getPlayers() as $player) {
					if ($player->isOnline()) {
						$this->sendNameTags($player);
						$player->reset(ArenaPlayer::SURVIVAL);
						$this->broadcastSound(new AnvilBreakSound($player));
						$player->removeTitles();
						$player->teleport($this->getMatchPosition($player)->add(0, 2));
						$this->getKit()->giveKit($player);
					}

				}
				$this->sendFightingMessage();
				$this->setStarted();
			}
		} else {
			$this->time--;
			$this->handleMessages();
			foreach ($this->getAll() as $player) $this->getBossBar()->moveEntity($player);
			$this->getBossBar()->setBossBarTitle(TextFormat::GRAY . "Match Time: " . TextFormat::GOLD . gmdate("i:s", $this->time));
			if ($this->time == 0) {
				$this->broadcastMessage(TextFormat::RED . "Time ran out, so it's a draw!");
				$this->kill();
			}
		}

		if (count($this->getPlayers()) <= 1 and !$this->hasEnded()) {
			$this->setEnded();
		}
	}

	/**
	 * @return null|bool
	 */
	public function isRanked(): ?bool {
		return $this->ranked;
	}

	public function handleMessages() {
		foreach ($this->getAll() as $player) {
			$message = TextFormat::GRAY . "Ladder: " . TextFormat::GOLD . $this->getKit()->getName();
			if (count($this->getMatchPlayers()) <= 2 and $this->isPlayer($player)) $message .= TextFormat::GRAY . " | Opponent: " . TextFormat::GOLD . ($this->getOtherPlayer($player))->getName();
			$player->sendPopup($message);
		}
	}

	public function sendFightingMessage() {
		foreach ($this->getPlayers() as $player) {
			$againstMessage = TextFormat::GOLD . "Now in match against: ";
			foreach ($this->getAllOtherPlayers($player) as $otherPlayer) {
				$addElo = ($this->isRanked() ? "[" . $otherPlayer->getElo($this->getKit())->getElo() . "]" : "");
				$againstMessage .= $otherPlayer->getName() . $addElo . ", ";
			}

			$againstMessage = rtrim($againstMessage, ", ");
			$againstMessage .= " with kit " . $this->getKit()->getName();
			$player->sendMessage($againstMessage);
			if ($this->isRanked()) $player->sendMessage(TextFormat::GOLD . "Your Elo: " . $player->getElo($this->getKit())->getElo());

		}
	}

	public function getOtherPlayer(ArenaPlayer $player) {
		foreach ($this->matchPlayers as $arenaPlayer) {
			if ($arenaPlayer === $player) {
				continue;
			}
			return $arenaPlayer;
		}
		return false;
	}

	public function onDamage(EntityDamageEvent $event) {
		$entity = $event->getEntity();
		if($entity instanceof ArenaPlayer) {
			if(!$this->hasStarted()) {
				$event->setCancelled();
			}
			if($event->getFinalDamage() >= $entity->getHealth()) {
				if($event instanceof EntityDamageByEntityEvent) {
					$damager = $event->getDamager();
					if($damager instanceof ArenaPlayer) {
						$this->broadcastMessage(TextFormat::RED . $entity->getName() . " killed by " . $damager->getName() . " (" . ((int)$damager->getHealth() / 2) . " hearts)");
					}
				} else {
					$lastCause = $entity->getLastDamageCause();
					if($lastCause instanceof EntityDamageByEntityEvent) {
						$damager = $lastCause->getDamager();
						if($damager instanceof ArenaPlayer) {
							$this->broadcastMessage(TextFormat::RED . $entity->getName() . " killed by " . $damager->getName() . " (" . ((int)$damager->getHealth() / 2) . " hearts)");
						}
					} else {
						$this->broadcastMessage(TextFormat::RED . $entity->getName() . " died");
					}
				}
				$this->handleDeath($entity);
			} else {
				if($event instanceof EntityDamageByEntityEvent) {
					$damager = $event->getDamager();
					if($this->getKit()->isKit("Combo") and $entity->getGamemode() !== ArenaPlayer::CREATIVE and $damager instanceof ArenaPlayer and !$damager->isSpectating()) {
						$newKnockback = $event->getKnockBack() - ($event->getKnockBack() / 3.5);
						$event->setKnockBack($newKnockback);
						$event->setCancelled(false);
					}
				}
				if ($event instanceof EntityDamageByChildEntityEvent) {
					$child = $event->getChild();
					if ($child instanceof Arrow) {
						$entity->knockBack($event->getChild(), 0, $event->getChild()->getMotion()->getX(), $event->getChild()->getMotion()->getZ());
						$shooter = $child->getOwningEntity();
						if($shooter instanceof ArenaPlayer) {
							if($this->getKit()->isKit("BuildUHC") or $this->getKit()->isKit("Archer")) {
								$shooter->sendArgsMessage(TextFormat::GREEN . $entity->getName() . " has {0} hearts left!", (round(($entity->getHealth() - $event->getFinalDamage()) / 2)));
							}
							$shooter->getLevel()->addSound(new AnvilFallSound($shooter->getPosition()), [$shooter]);
						}
					}

				}
			}
		}
	}

	public function onRegainHealth(EntityRegainHealthEvent $event) {
		$entity = $event->getEntity();
		if ($entity instanceof ArenaPlayer and $this->getKit()->shouldRegen()) {
			if ($event->getRegainReason() == EntityRegainHealthEvent::CAUSE_SATURATION) {
				$event->setCancelled();
			}
		}
	}

	public function onRemove(EntityEffectRemoveEvent $event) {
		$player = $event->getEntity();
		if ($player instanceof ArenaPlayer and $player->inMatch()) {
			$effect = $event->getEffect();
			if (count($this->getKit()->getEffects()) > 0) {
				foreach ($this->getKit()->getEffects() as $kitEffect) {
					if ($effect->getId() == $kitEffect->getId()) {
						$player->addEffect($effect);
					}
				}
			}
		}
	}

	public function onInteract(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		$item = $event->getItem();
		switch ($item->getId()) {
			case Item::BEETROOT_SOUP:
				if ($this->getKit()->isKit("BuffedSoup")) {
					if ($player->getHealth() < $player->getMaxHealth()) {
						$player->getInventory()->setItemInHand(Item::get(Item::AIR));
						$player->addEffect(Effect::getEffect(Effect::ABSORPTION)->setDuration(20 * 90));
						$player->addEffect(Effect::getEffect(Effect::REGENERATION)->setDuration(20 * 6)->setAmplifier(2));
					}
				}
				break;
			case Item::MUSHROOM_STEW:
				if ($this->getKit()->isKit("IronSoup")) {
					if ($player->getHealth() < $player->getMaxHealth()) {
						$player->getInventory()->setItemInHand(Item::get(Item::AIR));
						$player->heal(new EntityRegainHealthEvent($player, 5, EntityRegainHealthEvent::CAUSE_CUSTOM));
					}
				}
				break;
			case Item::FLINT_AND_STEEL:
				if ($this->getKit()->isKit("SG")) {
					$item->setDamage($item->getDamage() + $item->getMaxDurability() / 3);
					if ($item->getDamage() >= $item->getMaxDurability()) {
						$item->pop();
					}
				}
		}
	}

	public function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		if ($player instanceof ArenaPlayer) {
			$block = $event->getBlock();
			if ($block->getY() > $this->getArena()->getMaxBuildHeight()) {
				$event->setCancelled();
				$player->sendMessage(TextFormat::RED . "You can't build over the height limit!");
			}
		}
	}

	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		if ($this->getKit()->canBuild() and $player instanceof ArenaPlayer and $player->inMatch()) {
			$id = $event->getBlock()->getId();
			if ($id !== Block::COBBLESTONE and $id !== Block::WOODEN_PLANKS) { //TODO: Whitelisted block lists
				$event->setCancelled();
			} else if ($id == Block::WOODEN_PLANKS and $event->getBlock()->getDamage() > Planks::OAK) {
				$event->setCancelled();
			}
		} else {
			$event->setCancelled();
		}
	}

	/**
	 * @param PlayerQuitEvent|PlayerKickEvent $event
	 */
	public function onLeave($event) {
		$player = $event->getPlayer();
		if($player instanceof ArenaPlayer) {
			if ($this->isSpectator($player)) {
				$player->removeFromSpectating();
				$this->removeSpectator($player);
			} else {
				$lastCause = $player->getLastDamageCause();
				if ($lastCause instanceof EntityDamageByEntityEvent) {
					$damager = $lastCause->getDamager();
					if($damager instanceof ArenaPlayer) {
						$this->broadcastArgsMessage(TextFormat::RED . "{0} killed by {1} ({3} hearts)", $player->getName(), $damager->getName(), floor($damager->getHealth() / 2));
					}
				} else {
					$this->broadcastArgsMessage(TextFormat::RED . "{0} died", $player->getName());
				}
				$this->handleDeath($player, true);
			}
		}
	}

	public function broadcastSound(Sound $sound) {
		foreach ($this->getAll() as $player) {
			$sound->setComponents($player->getX(), $player->getY(), $player->getZ());
			$player->getLevel()->addSound($sound, [$player]);
		}
	}

	public function broadcastTitle(string $title, string $subtitle = "", int $fadeIn = -1, int $stay = -1, int $fadeOut = -1) {
		foreach ($this->getAll() as $player) {
			$player->addTitle($title, $subtitle, $fadeIn, $stay, $fadeOut);
		}
	}

	/**
	 * @param string $message
	 */
	public function broadcastMessage(string $message) {
		foreach ($this->getAll() as $player) {
			$player->sendMessage($message);
		}
	}

	/**
	 * @param string $message
	 * @param string[] ...$args
	 */
	public function broadcastArgsMessage(string $message, ...$args) {
		for($i = 0; $i < count($args); $i++) {
			$message = str_replace("{" . $i . "}", $args[$i], $message);
		}
		$this->broadcastMessage($message);
	}

	/**
	 * @param string $message
	 */
	public function broadcastPlayerMessage(string $message) {
		foreach($this->matchPlayers as $matchPlayer) {
			$matchPlayer->sendMessage($message);
		}
	}

	/**
	 * @param string $message
	 */
	public function broadcastPopup(string $message) {
		foreach ($this->getAll() as $player) {
			$player->sendPopup($message);
		}
	}

	/**
	 * @param string $message
	 * @param int $fadeIn
	 * @param int $stay
	 * @param int $fadeOut
	 */
	public function broadcastActionBar(string $message, int $fadeIn = -1, int $stay = -1, int $fadeOut = -1) {
		foreach ($this->getAll() as $player) {
			$player->setTitleDuration($fadeIn, $stay, $fadeOut);
			$player->addActionBarMessage($message);
		}
	}

	/**
	 * @param ArenaPlayer $player
	 */
	public function setWinner(ArenaPlayer $player) {
		$this->winner = $player;
	}

	/**
	 * @return ArenaPlayer
	 */
	public function getWinner(): ArenaPlayer {
		return $this->winner;
	}

	/**
	 * @param ArenaPlayer|null $player
	 * @param bool $leaving
	 */
	public function handleDeath(ArenaPlayer $player, $leaving = false) {
		if ($this->isPlayer($player)) {
			$this->removePlayer($player);
			if (!$leaving) {
				$this->addSpectator($player, true);
				$player->setHealth($player->getMaxHealth());
			}
			if (count($this->getPlayers()) > 1) {
				$player->dropAllItems();
			} else {
				$this->setEnded();
				$player->getLevel()->dropItem($player->getPosition(), $player->getInventory()->getItemInHand());
				foreach ($this->getPlayers() as $arenaPlayer) {
					$arenaPlayer->setHealth($player->getMaxHealth());
					$arenaPlayer->setGamemode(ArenaPlayer::CREATIVE);
					$this->setWinner($arenaPlayer);
				}
				foreach ($this->getAll() as $player) {
					$player->sendArgsMessage(TextFormat::GREEN . "Winner: {0}", $this->getWinner()->getName());
				}
			}
		}
	}

	public function reset() {
		foreach ($this->getAll() as $player) {
			if ($player->isSpectating()) {
				$player->removeFromSpectating();
			}
			$player->removeMatch();
			$player->setInMatch(false);
			$player->reset();
			$this->getBossBar()->removeBossBar($player);
			if ($player->isFlying()) {
				$player->setFlying(false);
				$player->setAllowFlight(false);
			}
			//TODO: Add customization
			$player->teleport($this->getPlugin()->getServer()->getDefaultLevel()->getSafeSpawn());
			$this->getPlugin()->getArenaManager()->addLobbyItems($player);
		}
		$this->getArena()->resetArena();

		$this->kill();
	}

	public function kill() {
		$this->getPlugin()->getMatchManager()->removeMatch($this);

		if ($this->isRanked()) {
			$loser = $this->getOtherPlayer($this->getWinner());
			$this->getWinner()->getElo($this->getKit())->calculateNewElo($this->getWinner(), $loser);
			$loser->getElo($this->getKit())->calculateNewElo($this->getWinner(), $loser);
		}
	}

	public function nullify() {
		$this->arena = null;
		$this->blocksPlaced = null;
		$this->countdown = null;
		$this->ended = null;
		$this->finishTime = null;
		$this->kit = null;
		$this->matchPlayers = null;
		$this->players = null;
		$this->plugin = null;
		$this->positions = null;
		$this->ranked = null;
		$this->spectators = null;
		$this->started = null;
		$this->time = null;
		$this->winner = null;
	}

}