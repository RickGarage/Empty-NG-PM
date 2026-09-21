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

namespace pocketmine\network;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\Server;
use function count;
use function microtime;
use function trim;

/**
 * Protects the server against login floods and bot attacks, and smooths out
 * legitimate login surges (e.g. hundreds of players reconnecting at once).
 *
 * This enforces, per IP address, concurrent connection caps and sliding-window
 * connection/login rate limits, plus a server-wide login rate limit. Repeated
 * offenders are temporarily blocked at the network layer.
 *
 * Addresses listed under antibot.trusted-proxies (e.g. a local WaterDogPE
 * proxy, through which all players share one IP) are exempt from per-IP
 * limits, while still counting towards the server-wide limits.
 *
 * All bookkeeping is pruned lazily, so this manager needs no per-tick updates
 * and adds negligible overhead when no attack is happening.
 */
class AntiBotManager{
	/**
	 * Maximum number of tracked timestamps kept per bucket. Bounds memory usage
	 * while under extreme floods (older entries are expired by the window anyway).
	 */
	private const MAX_TRACKED_TIMESTAMPS = 1024;

	private bool $enabled;
	private int $maxConnectionsPerIp;
	private int $maxConnectionAttemptsPerIp;
	private int $connectionRateWindow;
	private int $maxLoginAttemptsPerIp;
	private int $loginRateWindow;
	private int $maxLoginsPerSecond;
	private int $maxPendingLogins;
	private int $maxViolations;
	private int $violationWindow;
	private int $blockDuration;
	/**
	 * IPs exempt from per-IP limits (e.g. proxies like WaterDogPE, where all
	 * players arrive from a single local address).
	 *
	 * @var array<string, true>
	 * @phpstan-var array<string, true>
	 */
	private array $trustedProxies = [];

	/**
	 * @var \SplQueue[] IP => connection attempt timestamps (oldest first)
	 * @phpstan-var array<string, \SplQueue<float>>
	 */
	private array $connectionAttempts = [];
	/**
	 * @var \SplQueue[] IP => login attempt timestamps (oldest first)
	 * @phpstan-var array<string, \SplQueue<float>>
	 */
	private array $loginAttempts = [];
	/** @phpstan-var \SplQueue<float> */
	private \SplQueue $globalLoginAttempts;
	/**
	 * @var int[] IP => currently open sessions
	 * @phpstan-var array<string, int>
	 */
	private array $activeConnections = [];
	/**
	 * @var array{int, float}[] IP => [violation count, first violation time]
	 * @phpstan-var array<string, array{int, float}>
	 */
	private array $violations = [];

	private float $lastSweep = 0.0;

	private \Logger $logger;

	public function __construct(
		private Server $server
	){
		$this->logger = new \PrefixedLogger($server->getLogger(), "AntiBot");
		$config = $server->getConfigGroup();
		$this->enabled = $config->getPropertyBool("antibot.enabled", true);
		$this->maxConnectionsPerIp = $config->getPropertyInt("antibot.max-connections-per-ip", 5);
		$this->maxConnectionAttemptsPerIp = $config->getPropertyInt("antibot.max-connection-attempts-per-ip", 30);
		$this->connectionRateWindow = $config->getPropertyInt("antibot.connection-rate-window", 60);
		$this->maxLoginAttemptsPerIp = $config->getPropertyInt("antibot.max-login-attempts-per-ip", 30);
		$this->loginRateWindow = $config->getPropertyInt("antibot.login-rate-window", 60);
		$this->maxLoginsPerSecond = $config->getPropertyInt("antibot.max-logins-per-second", 20);
		$this->maxPendingLogins = $config->getPropertyInt("antibot.max-pending-logins", 100);
		$this->maxViolations = $config->getPropertyInt("antibot.max-violations", 3);
		$this->violationWindow = $config->getPropertyInt("antibot.violation-window", 600);
		$this->blockDuration = $config->getPropertyInt("antibot.block-duration", 300);
		foreach((array) $config->getProperty("antibot.trusted-proxies", ["127.0.0.1", "::1"]) as $proxyIp){
			$proxyIp = trim((string) $proxyIp);
			if($proxyIp !== ""){
				$this->trustedProxies[$proxyIp] = true;
			}
		}

		$this->globalLoginAttempts = new \SplQueue();
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	/**
	 * Returns whether the given IP belongs to a trusted proxy. Trusted proxies
	 * are exempt from per-IP limits, since many legitimate players may share
	 * their address (e.g. WaterDogPE running on localhost).
	 */
	public function isTrustedProxy(string $ip) : bool{
		return isset($this->trustedProxies[$ip]);
	}

	/**
	 * Checks whether a new RakNet connection from the given IP may proceed.
	 * This must be called before the session is created.
	 *
	 * @return string|null disconnect reason if the connection must be rejected, null if allowed
	 */
	public function checkConnection(string $ip) : ?string{
		if(!$this->enabled){
			return null;
		}
		$now = microtime(true);

		if($this->maxPendingLogins > 0){
			$manager = $this->server->getNetwork()->getSessionManager();
			if($manager->getSessionCount() - $manager->getValidSessionCount() >= $this->maxPendingLogins){
				return "The server is currently busy. Please try again in a moment.";
			}
		}

		//trusted proxies (e.g. WaterDogPE) funnel many legitimate players through
		//a single address, so per-IP limits must not apply to them
		if($this->isTrustedProxy($ip)){
			return null;
		}

		if($this->maxConnectionsPerIp > 0 && ($this->activeConnections[$ip] ?? 0) >= $this->maxConnectionsPerIp){
			return "Too many connections from your IP address. Please wait and try again.";
		}

		if($this->maxConnectionAttemptsPerIp > 0){
			$attempts = $this->connectionAttempts[$ip] ??= new \SplQueue();
			$this->pruneQueue($attempts, $now - $this->connectionRateWindow);
			$attempts->enqueue($now);
			$this->capQueue($attempts);
			if(count($attempts) > $this->maxConnectionAttemptsPerIp){
				$this->addViolation($ip, $now);
				return "You are connecting too often. Please wait before retrying.";
			}
		}

		return null;
	}

	/**
	 * Checks whether a login packet from the given IP may be processed.
	 * This must be called before any expensive authentication work is done.
	 *
	 * @return string|null disconnect reason if the login must be rejected, null if allowed
	 */
	public function checkLogin(string $ip) : ?string{
		if(!$this->enabled){
			return null;
		}
		$now = microtime(true);

		if($this->maxLoginsPerSecond > 0){
			$this->pruneQueue($this->globalLoginAttempts, $now - 1.0);
			if(count($this->globalLoginAttempts) >= $this->maxLoginsPerSecond){
				return "The server is currently busy. Please try again in a moment.";
			}
			$this->globalLoginAttempts->enqueue($now);
			$this->capQueue($this->globalLoginAttempts);
		}

		//trusted proxies (e.g. WaterDogPE) funnel many legitimate players through
		//a single address, so per-IP limits must not apply to them
		if($this->isTrustedProxy($ip)){
			return null;
		}

		if($this->maxLoginAttemptsPerIp > 0){
			$attempts = $this->loginAttempts[$ip] ??= new \SplQueue();
			$this->pruneQueue($attempts, $now - $this->loginRateWindow);
			$attempts->enqueue($now);
			$this->capQueue($attempts);
			if(count($attempts) > $this->maxLoginAttemptsPerIp){
				$this->addViolation($ip, $now);
				return "You are logging in too often. Please wait before retrying.";
			}
		}

		return null;
	}

	/**
	 * Starts tracking an accepted session for the per-IP concurrent connection cap.
	 * The counter is released automatically when the session is disposed.
	 */
	public function trackSession(NetworkSession $session) : void{
		if(!$this->enabled){
			return;
		}
		$ip = $session->getIp();
		if($this->isTrustedProxy($ip)){
			return;
		}
		$this->activeConnections[$ip] = ($this->activeConnections[$ip] ?? 0) + 1;
		$session->getDisposeHooks()->add(function() use ($ip) : void{
			if(isset($this->activeConnections[$ip]) && --$this->activeConnections[$ip] <= 0){
				unset($this->activeConnections[$ip]);
			}
		});
	}

	/**
	 * @phpstan-param \SplQueue<float> $queue
	 */
	private function pruneQueue(\SplQueue $queue, float $minTime) : void{
		while(!$queue->isEmpty()){
			/** @var float $oldest */
			$oldest = $queue->bottom();
			if($oldest >= $minTime){
				break;
			}
			$queue->dequeue();
		}
	}

	/**
	 * @phpstan-param \SplQueue<float> $queue
	 */
	private function capQueue(\SplQueue $queue) : void{
		while(count($queue) > self::MAX_TRACKED_TIMESTAMPS){
			$queue->dequeue();
		}
	}

	private function addViolation(string $ip, float $now) : void{
		$this->sweep($now);
		[$count, $first] = $this->violations[$ip] ?? [0, $now];
		if($now - $first >= $this->violationWindow){
			$count = 0;
			$first = $now;
		}
		$this->violations[$ip] = [++$count, $first];

		if($this->maxViolations > 0 && $count >= $this->maxViolations){
			unset($this->violations[$ip]);
			$this->logger->warning("Temporarily blocking IP $ip for {$this->blockDuration} seconds (connection flooding)");
			$this->server->getNetwork()->blockAddress($ip, $this->blockDuration);
		}else{
			$this->logger->debug("AntiBot violation from $ip ($count/{$this->maxViolations})");
		}
	}

	private function sweep(float $now) : void{
		if($now - $this->lastSweep < 60.0){
			return;
		}
		$this->lastSweep = $now;
		foreach($this->violations as $ip => [$count, $first]){
			if($now - $first >= $this->violationWindow){
				unset($this->violations[$ip]);
			}
		}
		foreach($this->connectionAttempts as $ip => $queue){
			if($queue->isEmpty()){
				unset($this->connectionAttempts[$ip]);
			}
		}
		foreach($this->loginAttempts as $ip => $queue){
			if($queue->isEmpty()){
				unset($this->loginAttempts[$ip]);
			}
		}
	}
}
