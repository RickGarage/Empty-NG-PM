<?php
declare(strict_types=1);

namespace pocketmine\form;

use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

class ModalForm implements Form {

	/** @var array<string, mixed> */
	protected array $data = [];

	private string $content = "";
	/** @var callable(Player $player)|null */
	private $onCompletion;
	/** @var int|null */
	private $maxRetries;
	/** @var string|null */
	private $kickMessage;
	/** @var bool */
	private $blocking;


	public function __construct(?callable $onCompletion = null, ?int $maxRetries = null, ?string $kickMessage = null, bool $blocking = false) {
		$this->data["type"] = "modal";
		$this->data["title"] = "";
		$this->data["content"] = "";
		$this->data["button1"] = "";
		$this->data["button2"] = "";
		$this->onCompletion = $onCompletion;
		$this->maxRetries = $maxRetries;
		$this->kickMessage = $kickMessage;
		$this->blocking = $blocking;
	}

	/**
	 * Validates and normalizes the raw response data.
	 * Subclasses which need access to the submitted answer should override {@link self::handleResponse()}
	 * and call parent::handleResponse() first to benefit from this validation.
	 *
	 * @throws FormValidationException if the data could not be processed
	 */
	public function handleResponse(Player $player, $data) : void {
		$this->processData($data);
	}

	public function getOnCompletion() : ?callable {
		return $this->onCompletion;
	}

	public function setOnCompletion(?callable $onCompletion) : self {
		$this->onCompletion = $onCompletion;
		return $this;
	}


	public function processData(&$data) : void {
		//A null response means the player closed the form without choosing a button
		if($data !== null && !is_bool($data)) {
			throw new FormValidationException("Expected a boolean response, got " . gettype($data));
		}
	}


	public function setTitle(string $title) : self {
		$this->data["title"] = $title;
		return $this;
	}

	public function getTitle() : string {
		return $this->data["title"];
	}

	public function setContent(string $content) : self {
		$this->data["content"] = $content;
		return $this;
	}

	public function getContent() : string {
		return $this->data["content"];
	}

	public function setButton1(string $text) : self {
		$this->data["button1"] = $text;
		return $this;
	}

	public function getButton1() : string {
		return $this->data["button1"];
	}

	public function setButton2(string $text) : self {
		$this->data["button2"] = $text;
		return $this;
	}

	public function getButton2() : string {
		return $this->data["button2"];
	}

	public function getMaxRetries() : ?int {
		return $this->maxRetries;
	}

	public function setMaxRetries(?int $maxRetries) : self {
		$this->maxRetries = $maxRetries;
		return $this;
	}

	public function getKickMessage() : ?string {
		return $this->kickMessage;
	}

	public function setKickMessage(?string $kickMessage) : self {
		$this->kickMessage = $kickMessage;
		return $this;
	}

	public function isBlocking() : bool {
		return $this->blocking;
	}

	public function setBlocking(bool $blocking) : self {
		$this->blocking = $blocking;
		return $this;
	}

	public function jsonSerialize() : array {
		return $this->data;
	}
}