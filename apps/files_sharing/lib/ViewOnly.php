<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2019 ownCloud GmbH
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Files_Sharing;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;

/**
 * Handles restricting for download of files
 */
class ViewOnly {

	public function __construct(
		private Folder $userFolder,
	) {
	}

	public function checkNode(Node $node): bool {
		return match (true) {
			// access to filecache is expensive in the loop
			$node instanceof File => $this->checkFileInfo($node),
			// get directory content is a rather cheap query
			$node instanceof Folder => $this->dirRecursiveCheck($node),
		};
	}

	/**
	 * // fixme: inline this method to listener
	 * @param string[] $pathsToCheck
	 * @return bool
	 */
	public function check(array $pathsToCheck): bool {
		// If any of elements cannot be downloaded, prevent whole download
		foreach ($pathsToCheck as $file) {
			try {
				$info = $this->userFolder->get($file);
				return $this->checkNode($info);
			} catch (NotFoundException $e) {
				continue;
			}
		}
		return true;
	}

	/**
	 * @param Folder $dirInfo
	 * @return bool
	 * @throws NotFoundException
	 */
	private function dirRecursiveCheck(Folder $dirInfo): bool {
		if (!$this->checkFileInfo($dirInfo)) {
			return false;
		}
		// If any of elements cannot be downloaded, prevent whole download
		$files = $dirInfo->getDirectoryListing();
		foreach ($files as $file) {
			if ($file instanceof File) {
				if (!$this->checkFileInfo($file)) {
					return false;
				}
			} elseif ($file instanceof Folder) {
				return $this->dirRecursiveCheck($file);
			}
		}

		return true;
	}

	/**
	 * @param Node $fileInfo
	 * @return bool
	 * @throws NotFoundException
	 */
	private function checkFileInfo(Node $fileInfo): bool {
		// Restrict view-only to nodes which are shared
		$storage = $fileInfo->getStorage();
		if (!$storage->instanceOfStorage(SharedStorage::class)) {
			return true;
		}

		// Extract extra permissions
		/** @var SharedStorage $storage */
		$share = $storage->getShare();

		// Check whether download-permission was denied (granted if not set)
		$attributes = $share->getAttributes();
		$canDownload = $attributes?->getAttribute('permissions', 'download');

		return $canDownload !== false;
	}
}
