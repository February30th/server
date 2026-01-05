<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Sharing\Listener;

use OCA\Files_Sharing\ViewOnly;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\BeforeZipCreatedEvent;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<BeforeZipCreatedEvent|Event>
 */
class BeforeZipCreatedListener implements IEventListener {

	public function __construct(
		private IUserSession $userSession,
		private IRootFolder $rootFolder,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeZipCreatedEvent)) {
			return;
		}

		$user = $this->userSession->getUser();
		if (!$user) {
			// fixme: do we need check anything for anonymous users?
			$event->setSuccessful(true);
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$viewOnlyHandler = new ViewOnly($userFolder);
		$this->checkSelectedFilesCanBeDownloaded($event, $viewOnlyHandler);
		$this->checkNodesCanBeDownloaded($event, $viewOnlyHandler);
	}

	private function checkSelectedFilesCanBeDownloaded(BeforeZipCreatedEvent $event, ViewOnly $viewOnlyHandler): void {
		// Check only for user/group shares. Don't restrict e.g. share links
		$dir = $event->getDirectory();
		$pathsToCheck = [$dir];
		foreach ($event->getFiles() as $file) {
			$pathsToCheck[] = $dir . '/' . $file;
		}

		if (!$viewOnlyHandler->check($pathsToCheck)) {
			$event->setErrorMessage('Access to this resource or one of its sub-items has been denied.');
			$event->setSuccessful(false);
		} else {
			$event->setSuccessful(true);
		}
	}

	private function checkNodesCanBeDownloaded(BeforeZipCreatedEvent $event, ViewOnly $viewOnlyHandler): void {
		$nodes = array_values(array_filter($event->getNodes(),
			static fn ($node) => $viewOnlyHandler->checkNode($node)));

		$event->setNodes($nodes);
	}
}
