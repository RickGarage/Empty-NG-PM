<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\form;

use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

/**
 * Form implementations must implement this interface to be able to utilize the Player form-sending mechanism.
 * There is no restriction on custom implementations other than that they must implement this.
 */
interface Form extends \JsonSerializable{

	/**
	 * Handles a form response from a player.
	 *
	 * @param mixed $data
	 *
	 * @throws FormValidationException if the data could not be processed
	 */
	public function handleResponse(Player $player, $data) : void;

	/**
	 * Gets the on completion callback for this form.
	 *
	 * @return callable(Player $player)|null
	 */
	public function getOnCompletion() : ?callable;

	/**
	 * Gets the retry attempts setting for this form.
	 * When the form is closed without submission, it will be re-sent up to this many times.
	 * Set to null or 0 to disable auto-resend.
	 *
	 * @return int|null
	 */
	public function getMaxRetries() : ?int;

	/**
	 * Sets the max retries setting for this form.
	 * When the form is closed without submission, it will be re-sent up to this many times.
	 * Set to null or 0 to disable auto-resend.
	 *
	 * @param int|null $maxRetries
	 * @return $this
	 */
	public function setMaxRetries(?int $maxRetries) : self;

	/**
	 * Gets the kick message for this form.
	 * When max retries is exceeded, the player will be kicked with this message.
	 *
	 * @return string|null
	 */
	public function getKickMessage() : ?string;

	/**
	 * Sets the kick message for this form.
	 * When max retries is exceeded, the player will be kicked with this message.
	 *
	 * @param string|null $kickMessage
	 * @return $this
	 */
	public function setKickMessage(?string $kickMessage) : self;

	/**
	 * Gets whether this form blocks all player events.
	 * When true, the player cannot interact with anything while the form is open.
	 *
	 * @return bool
	 */
	public function isBlocking() : bool;

	/**
	 * Sets whether this form blocks all player events.
	 * When true, the player cannot interact with anything while the form is open.
	 *
	 * @param bool $blocking
	 * @return $this
	 */
	public function setBlocking(bool $blocking) : self;
}