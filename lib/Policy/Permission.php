<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Policy;

enum Permission: int {
	case ViewFiles = 1;
	case WriteFiles = 2;
	case DeleteFiles = 4;
	case ShareFiles = 8;
	case ChangePassword = 16;
	case FullAccess = 32;
	case HideSideNavigation = 64;
}
