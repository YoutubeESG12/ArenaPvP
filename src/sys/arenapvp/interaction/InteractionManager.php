<?php
/**
 *
 * This file was created by Matt on 7/18/2017
 * Any attempts to copy, steal, or use this code
 * without permission will result in various consequences.
 *
 */

namespace sys\arenapvp\interaction;


use pocketmine\item\Item;
use sys\arenapvp\ArenaPlayer;
use sys\arenapvp\ArenaPvP;
use sys\arenapvp\interaction\defaults\LobbyInteraction;
use sys\arenapvp\interaction\defaults\QueueInteraction;
use sys\arenapvp\interaction\defaults\SpectatingInteraction;

class InteractionManager {

	/** @var Interaction[] */
	private $interactions = [];

	/** @var ArenaPvP */
	private $plugin;

	public function __construct(ArenaPvP $plugin) {
		$this->plugin = $plugin;
		$this->initInteractions();
	}

	/**
	 * @return ArenaPvP
	 */
	public function getPlugin(): ArenaPvP {
		return $this->plugin;
	}

	/**
	 * @return Interaction[]
	 */
	public function getInteractions(): array {
		return $this->interactions;
	}

	public function initInteractions() {
		$queueInteraction = new QueueInteraction($this->getPlugin(), [
			Item::get(Item::REDSTONE_DUST)->setCustomName("Leave Queue"),
			Item::get(Item::PAPER)->setCustomName("Queue Info")
		]);
		$this->addInteraction($queueInteraction);

		$lobbyInteraction = new LobbyInteraction($this->getPlugin(), [
			Item::get(Item::EMPTY_MAP)->setCustomName("Start Party Event"),
			Item::get(Item::GOLDEN_SWORD)->setCustomName("Join Unranked Queue"),
			Item::get(Item::DIAMOND_SWORD)->setCustomName("Join Ranked Queue")
		]);
		$this->addInteraction($lobbyInteraction);

		$spectatingInteraction = new SpectatingInteraction($this->getPlugin(), [
			Item::get(Item::REDSTONE_TORCH)->setCustomName("Spectator Toggle Off"),
		]);
		$this->addInteraction($spectatingInteraction);

	}

	/**
	 * @param Item $item
	 * @param ArenaPlayer $player
	 * @return bool
	 */
	public function matchesInteraction(Item $item, ArenaPlayer $player): bool {
		foreach ($this->getPlugin()->getInteractionManager()->getInteractions() as $interaction) {
			if ($interaction->exists($item)) {
				$interaction->onInteract($player, $item);
				return true;
			}
		}
		return false;
	}

	public function addInteraction(Interaction $interaction) {
		$this->interactions[spl_object_hash($interaction)] = $interaction;
	}

	public function removeInteraction(Interaction $interaction) {
		if (isset($this->interactions[spl_object_hash($interaction)])) unset($this->interactions[spl_object_hash($interaction)]);
	}



}