<?php
declare(strict_types=1);

namespace pocketmine\form;

use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

class SimpleForm extends Form implements \JsonSerializable {

	const IMAGE_TYPE_PATH = 0;
	const IMAGE_TYPE_URL = 1;

	private string $content = "";
	private array $labelMap = [];
	private array $buttons = [];

	public function __construct() {
		parent::__construct(null);
		$this->data["type"] = "form";
		$this->data["title"] = "";
		$this->data["content"] = "";
		$this->data["buttons"] = [];
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

	public function jsonSerialize() : array {
		return $this->data;
	}
}