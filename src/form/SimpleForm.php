<?php
declare(strict_types=1);

namespace pocketmine\form;

use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

class SimpleForm implements Form {

	const IMAGE_TYPE_PATH = 0;
	const IMAGE_TYPE_URL = 1;

	/** @var array<string, mixed> */
	protected array $data = [];

	private string $content = "";
	private array $labelMap = [];
	private array $buttons = [];
	/** @var callable(Player $player)|null */
	private $onCompletion;
	/** @var int|null */
	private $maxRetries;
	/** @var string|null */
	private $kickMessage;
	/** @var bool */
	private $blocking;


	public function __construct(?callable $onCompletion = null, ?int $maxRetries = null, ?string $kickMessage = null, bool $blocking = false) {
		$this->data["type"] = "form";
		$this->data["title"] = "";
		$this->data["content"] = "";
		$this->data["buttons"] = [];
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
		if($data !== null) {
			if(!is_int($data)) {
				throw new FormValidationException("Expected an integer response, got " . gettype($data));
			}
			$count = count($this->data["buttons"]);
			if($data >= $count || $data < 0) {
				throw new FormValidationException("Button $data does not exist");
			}
			$data = $this->labelMap[$data] ?? null;
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


	public function addButton(string $text, int $imageType = -1, string $imagePath = "", ?string $label = null) : self {
		$content = ["text" => $text];
		if($imageType !== -1) {
			$content["image"]["type"] = $imageType === 0 ? "path" : "url";
			$content["image"]["data"] = $imagePath;
		}
		$this->data["buttons"][] = $content;
		$this->labelMap[] = $label ?? count($this->labelMap);
		return $this;
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