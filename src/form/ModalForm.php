<?php
declare(strict_types=1);

namespace pocketmine\form;

use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

class ModalForm extends Form implements \JsonSerializable {

	private string $content = "";
	/** @var callable(Player $player)|null */
	private $onCompletion;


	public function __construct(?callable $onCompletion = null) {
		parent::__construct(null);
		$this->data["type"] = "modal";
		$this->data["title"] = "";
		$this->data["content"] = "";
		$this->data["button1"] = "";
		$this->data["button2"] = "";
		$this->onCompletion = $onCompletion;
	}

	/**
	 * @return callable(Player $player)|null
	 */
	public function getOnCompletion() : ?callable{
		return $this->onCompletion;
	}


	public function processData(&$data) : void {
		if(!is_bool($data)) {
			throw new FormValidationException("Expected a boolean response, got " . gettype($data));
		}
		// Call onCompletion after processing
		if($this->onCompletion !== null) {
			// Will be called from handleResponse
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


	public function jsonSerialize() : array {
		return $this->data;
	}
}